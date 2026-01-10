<?php
// admin-bookingdb.php - Database functions for booking management

function getBookingDetails($conn, $booking_id)
{
    $sql = "SELECT 
        b.*,
        u.FullName as UserName,
        u.MatricNo as UserMatric,
        u.Email as UserEmail,
        u.PhoneNumber as UserPhone,
        u.Gender as UserGender,
        u.Faculty as UserFaculty,
        r.*,
        d.FullName as DriverName,
        d.Email as DriverEmail,
        d.PhoneNumber as DriverPhone,
        dr.CarModel,
        dr.CarPlateNumber,
        p.*,
        de.Amount as DriverEarnings
        FROM booking b
        LEFT JOIN user u ON b.UserID = u.UserID
        LEFT JOIN rides r ON b.RideID = r.RideID
        LEFT JOIN driver dr ON r.DriverID = dr.DriverID
        LEFT JOIN user d ON dr.UserID = d.UserID
        LEFT JOIN payments p ON b.BookingID = p.BookingID
        LEFT JOIN driver_earnings de ON b.BookingID = de.BookingID
        WHERE b.BookingID = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $booking = $result->fetch_assoc();
    $stmt->close();

    return $booking;
}

function updateBookingStatus($conn, $booking_id, $new_status, $cancellation_reason = null)
{
    $conn->begin_transaction();

    try {
        // Get current booking info
        $booking_info = getBookingDetails($conn, $booking_id);

        if (!$booking_info) {
            throw new Exception("Booking not found");
        }

        $current_status = $booking_info['BookingStatus'];

        // Validate status transition
        $valid_transitions = [
            'Pending' => ['Confirmed', 'Cancelled'],
            'Confirmed' => ['Paid', 'Cancelled'],
            'Paid' => ['Completed', 'Cancelled'],
            'Completed' => [],
            'Cancelled' => []
        ];

        if (!in_array($new_status, $valid_transitions[$current_status])) {
            throw new Exception("Invalid status transition from $current_status to $new_status");
        }

        // Update booking status
        if ($new_status === 'Cancelled') {
            $stmt = $conn->prepare("UPDATE booking SET BookingStatus = ?, CancellationReason = ? WHERE BookingID = ?");
            $stmt->bind_param("ssi", $new_status, $cancellation_reason, $booking_id);
        } else {
            $stmt = $conn->prepare("UPDATE booking SET BookingStatus = ?, CancellationReason = '' WHERE BookingID = ?");
            $stmt->bind_param("si", $new_status, $booking_id);
        }

        $stmt->execute();
        $stmt->close();

        // Handle seat management
        if ($current_status === 'Cancelled' && $new_status !== 'Cancelled') {
            // Adding back seats that were returned
            $update_seats = $conn->prepare("UPDATE rides SET AvailableSeats = AvailableSeats - ? WHERE RideID = ?");
            $update_seats->bind_param("ii", $booking_info['NoOfSeats'], $booking_info['RideID']);
            $update_seats->execute();
            $update_seats->close();
        } elseif ($new_status === 'Cancelled' && $current_status !== 'Cancelled') {
            // Returning seats
            $update_seats = $conn->prepare("UPDATE rides SET AvailableSeats = AvailableSeats + ? WHERE RideID = ?");
            $update_seats->bind_param("ii", $booking_info['NoOfSeats'], $booking_info['RideID']);
            $update_seats->execute();
            $update_seats->close();
        }

        // Handle payment and earnings
        if ($new_status === 'Paid' || $new_status === 'Completed') {
            // Check if payment record exists
            $check_payment = $conn->query("SELECT * FROM payments WHERE BookingID = $booking_id");
            if ($check_payment->num_rows === 0) {
                $insert_payment = $conn->prepare("
                    INSERT INTO payments (BookingID, UserID, Amount, PaymentMethod, PaymentStatus, CreatedAt, UpdatedAt) 
                    VALUES (?, ?, ?, 'cash', 'paid', NOW(), NOW())
                ");
                $insert_payment->bind_param("iid", $booking_id, $booking_info['UserID'], $booking_info['TotalPrice']);
                $insert_payment->execute();
                $insert_payment->close();
            }
        }

        if ($new_status === 'Completed') {
            // Check if driver earnings already recorded
            $check_earnings = $conn->query("
                SELECT de.* FROM driver_earnings de
                JOIN rides r ON de.RideID = r.RideID
                WHERE de.BookingID = $booking_id
            ");

            if ($check_earnings->num_rows === 0) {
                $insert_earnings = $conn->prepare("
                    INSERT INTO driver_earnings (DriverID, RideID, BookingID, Amount, PaymentDate, CreatedAt) 
                    SELECT r.DriverID, r.RideID, ?, b.TotalPrice, NOW(), NOW()
                    FROM booking b
                    JOIN rides r ON b.RideID = r.RideID
                    WHERE b.BookingID = ?
                ");
                $insert_earnings->bind_param("ii", $booking_id, $booking_id);
                $insert_earnings->execute();
                $insert_earnings->close();
            }
        }

        // Send notification
        sendBookingNotification($conn, $booking_info['UserID'], $booking_id, $new_status, $cancellation_reason);

        // Log activity
        logBookingActivity($conn, $booking_id, $current_status, $new_status, $cancellation_reason);

        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Booking update error: " . $e->getMessage());
        return false;
    }
}

function sendBookingNotification($conn, $user_id, $booking_id, $new_status, $reason = null)
{
    $messages = [
        'Confirmed' => [
            'title' => 'Booking Confirmed',
            'message' => 'Your booking has been confirmed by the driver.',
            'type' => 'success'
        ],
        'Paid' => [
            'title' => 'Payment Verified',
            'message' => 'Your payment has been verified. Your booking is now confirmed.',
            'type' => 'success'
        ],
        'Completed' => [
            'title' => 'Ride Completed',
            'message' => 'Your ride has been marked as completed. Thank you for using CampusCar!',
            'type' => 'success'
        ],
        'Cancelled' => [
            'title' => 'Booking Cancelled',
            'message' => 'Your booking has been cancelled.' . ($reason ? " Reason: $reason" : ""),
            'type' => 'warning'
        ]
    ];

    $message = $messages[$new_status] ?? [
        'title' => 'Booking Status Updated',
        'message' => "Your booking status has been updated to: $new_status",
        'type' => 'info'
    ];

    $sql = "INSERT INTO notifications (UserID, Title, Message, Type, CreatedAt, RelatedID, RelatedType) 
            VALUES (?, ?, ?, ?, NOW(), ?, 'booking')";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssi", $user_id, $message['title'], $message['message'], $message['type'], $booking_id);
    $result = $stmt->execute();
    $stmt->close();

    return $result;
}

function getBookingStatistics($conn)
{
    $stats = [];

    // Total counts
    $stats['total'] = $conn->query("SELECT COUNT(*) as count FROM booking")->fetch_assoc()['count'];
    $stats['pending'] = $conn->query("SELECT COUNT(*) as count FROM booking WHERE BookingStatus = 'Pending'")->fetch_assoc()['count'];
    $stats['confirmed'] = $conn->query("SELECT COUNT(*) as count FROM booking WHERE BookingStatus = 'Confirmed'")->fetch_assoc()['count'];
    $stats['paid'] = $conn->query("SELECT COUNT(*) as count FROM booking WHERE BookingStatus = 'Paid'")->fetch_assoc()['count'];
    $stats['completed'] = $conn->query("SELECT COUNT(*) as count FROM booking WHERE BookingStatus = 'Completed'")->fetch_assoc()['count'];
    $stats['cancelled'] = $conn->query("SELECT COUNT(*) as count FROM booking WHERE BookingStatus = 'Cancelled'")->fetch_assoc()['count'];

    // Revenue statistics
    $stats['total_revenue'] = $conn->query("SELECT COALESCE(SUM(TotalPrice), 0) as total FROM booking WHERE BookingStatus IN ('Paid', 'Completed')")->fetch_assoc()['total'];
    $stats['today_revenue'] = $conn->query("SELECT COALESCE(SUM(TotalPrice), 0) as total FROM booking WHERE DATE(BookingDateTime) = CURDATE() AND BookingStatus IN ('Paid', 'Completed')")->fetch_assoc()['total'];
    $stats['month_revenue'] = $conn->query("SELECT COALESCE(SUM(TotalPrice), 0) as total FROM booking WHERE MONTH(BookingDateTime) = MONTH(CURDATE()) AND YEAR(BookingDateTime) = YEAR(CURDATE()) AND BookingStatus IN ('Paid', 'Completed')")->fetch_assoc()['total'];
    $stats['year_revenue'] = $conn->query("SELECT COALESCE(SUM(TotalPrice), 0) as total FROM booking WHERE YEAR(BookingDateTime) = YEAR(CURDATE()) AND BookingStatus IN ('Paid', 'Completed')")->fetch_assoc()['total'];

    // Average booking value
    $stats['avg_booking'] = $conn->query("SELECT COALESCE(AVG(TotalPrice), 0) as avg FROM booking WHERE BookingStatus IN ('Paid', 'Completed')")->fetch_assoc()['avg'];

    // Cancellation rate
    $stats['cancellation_rate'] = $stats['total'] > 0 ? ($stats['cancelled'] / $stats['total'] * 100) : 0;

    // Top users by bookings
    $stats['top_users'] = $conn->query("
        SELECT 
            u.FullName,
            u.MatricNo,
            COUNT(b.BookingID) as total_bookings,
            SUM(b.TotalPrice) as total_spent,
            AVG(b.TotalPrice) as avg_spent
        FROM booking b
        JOIN user u ON b.UserID = u.UserID
        WHERE b.BookingStatus IN ('Paid', 'Completed')
        GROUP BY b.UserID
        ORDER BY total_spent DESC
        LIMIT 10
    ")->fetch_all(MYSQLI_ASSOC);

    // Popular routes
    $stats['popular_routes'] = $conn->query("
        SELECT 
            CONCAT(r.FromLocation, ' → ', r.ToLocation) as route,
            COUNT(b.BookingID) as bookings,
            SUM(b.TotalPrice) as revenue,
            AVG(b.TotalPrice) as avg_price,
            COUNT(DISTINCT b.UserID) as unique_users
        FROM booking b
        JOIN rides r ON b.RideID = r.RideID
        WHERE b.BookingStatus IN ('Paid', 'Completed')
        GROUP BY r.FromLocation, r.ToLocation
        ORDER BY bookings DESC
        LIMIT 10
    ")->fetch_all(MYSQLI_ASSOC);

    // Payment method distribution
    $stats['payment_methods'] = $conn->query("
        SELECT 
            COALESCE(p.PaymentMethod, 'Not Paid') as method,
            COUNT(b.BookingID) as bookings,
            SUM(b.TotalPrice) as revenue,
            AVG(b.TotalPrice) as avg_amount
        FROM booking b
        LEFT JOIN payments p ON b.BookingID = p.BookingID
        WHERE b.BookingStatus IN ('Paid', 'Completed')
        GROUP BY p.PaymentMethod
        ORDER BY bookings DESC
    ")->fetch_all(MYSQLI_ASSOC);

    return $stats;
}

function searchBookings($conn, $filters = [])
{
    $sql = "SELECT 
        b.*,
        u.FullName as UserName,
        u.MatricNo as UserMatric,
        u.Email as UserEmail,
        u.PhoneNumber as UserPhone,
        r.FromLocation,
        r.ToLocation,
        r.RideDate,
        r.DepartureTime,
        r.Status as RideStatus,
        d.FullName as DriverName,
        p.PaymentMethod,
        p.PaymentStatus,
        p.ProofPath,
        p.TransactionID
        FROM booking b
        LEFT JOIN user u ON b.UserID = u.UserID
        LEFT JOIN rides r ON b.RideID = r.RideID
        LEFT JOIN driver dr ON r.DriverID = dr.DriverID
        LEFT JOIN user d ON dr.UserID = d.UserID
        LEFT JOIN payments p ON b.BookingID = p.BookingID
        WHERE 1=1";

    $params = [];
    $types = "";

    if (!empty($filters['search'])) {
        $sql .= " AND (u.FullName LIKE ? OR u.MatricNo LIKE ? OR r.FromLocation LIKE ? OR r.ToLocation LIKE ? OR d.FullName LIKE ?)";
        $search_param = "%{$filters['search']}%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
        $types .= "sssss";
    }

    if (!empty($filters['status'])) {
        $sql .= " AND b.BookingStatus = ?";
        $params[] = $filters['status'];
        $types .= "s";
    }

    if (!empty($filters['date_from'])) {
        $sql .= " AND DATE(b.BookingDateTime) >= ?";
        $params[] = $filters['date_from'];
        $types .= "s";
    }

    if (!empty($filters['date_to'])) {
        $sql .= " AND DATE(b.BookingDateTime) <= ?";
        $params[] = $filters['date_to'];
        $types .= "s";
    }

    if (!empty($filters['payment_method'])) {
        $sql .= " AND p.PaymentMethod = ?";
        $params[] = $filters['payment_method'];
        $types .= "s";
    }

    if (!empty($filters['min_price']) && is_numeric($filters['min_price'])) {
        $sql .= " AND b.TotalPrice >= ?";
        $params[] = $filters['min_price'];
        $types .= "d";
    }

    if (!empty($filters['max_price']) && is_numeric($filters['max_price'])) {
        $sql .= " AND b.TotalPrice <= ?";
        $params[] = $filters['max_price'];
        $types .= "d";
    }

    if (!empty($filters['user_id'])) {
        $sql .= " AND b.UserID = ?";
        $params[] = $filters['user_id'];
        $types .= "i";
    }

    if (!empty($filters['ride_id'])) {
        $sql .= " AND b.RideID = ?";
        $params[] = $filters['ride_id'];
        $types .= "i";
    }

    if (!empty($filters['driver_id'])) {
        $sql .= " AND r.DriverID = ?";
        $params[] = $filters['driver_id'];
        $types .= "i";
    }

    $sql .= " ORDER BY b.BookingDateTime DESC";

    // Add pagination
    $limit = $filters['limit'] ?? 50;
    $offset = $filters['offset'] ?? 0;
    $sql .= " LIMIT ? OFFSET ?";
    $params = array_merge($params, [$limit, $offset]);
    $types .= "ii";

    $stmt = $conn->prepare($sql);

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $bookings = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $bookings;
}

function logBookingActivity($conn, $booking_id, $old_status, $new_status, $reason = null)
{
    // This would log to an activity table if it exists
    // For now, we'll just return true
    return true;
}

function exportBookingsData($conn, $format = 'csv')
{
    $sql = "SELECT 
        b.BookingID,
        b.BookingDateTime,
        b.NoOfSeats,
        b.TotalPrice,
        b.BookingStatus,
        b.CancellationReason,
        u.FullName as UserName,
        u.MatricNo as UserMatric,
        u.Email as UserEmail,
        u.PhoneNumber as UserPhone,
        r.FromLocation,
        r.ToLocation,
        r.RideDate,
        r.DepartureTime,
        r.PricePerSeat,
        d.FullName as DriverName,
        p.PaymentMethod,
        p.PaymentStatus,
        p.TransactionID,
        p.PaymentDate,
        de.Amount as DriverEarnings
        FROM booking b
        LEFT JOIN user u ON b.UserID = u.UserID
        LEFT JOIN rides r ON b.RideID = r.RideID
        LEFT JOIN driver dr ON r.DriverID = dr.DriverID
        LEFT JOIN user d ON dr.UserID = d.UserID
        LEFT JOIN payments p ON b.BookingID = p.BookingID
        LEFT JOIN driver_earnings de ON b.BookingID = de.BookingID
        ORDER BY b.BookingDateTime DESC";

    $result = $conn->query($sql);

    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="bookings_export_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');

        // Headers
        fputcsv($output, [
            'Booking ID',
            'Booking Date',
            'Seats',
            'Total Price',
            'Status',
            'Cancellation Reason',
            'User Name',
            'User Matric',
            'User Email',
            'User Phone',
            'From Location',
            'To Location',
            'Ride Date',
            'Departure Time',
            'Price Per Seat',
            'Driver Name',
            'Payment Method',
            'Payment Status',
            'Transaction ID',
            'Payment Date',
            'Driver Earnings'
        ]);

        // Data
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, $row);
        }

        fclose($output);
    } elseif ($format === 'json') {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="bookings_export_' . date('Y-m-d') . '.json"');

        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }

        echo json_encode($data, JSON_PRETTY_PRINT);
    }

    exit();
}

function getBookingsCount($conn, $filters = [])
{
    $sql = "SELECT COUNT(DISTINCT b.BookingID) as count
            FROM booking b
            LEFT JOIN user u ON b.UserID = u.UserID
            LEFT JOIN rides r ON b.RideID = r.RideID
            LEFT JOIN driver dr ON r.DriverID = dr.DriverID
            LEFT JOIN user d ON dr.UserID = d.UserID
            LEFT JOIN payments p ON b.BookingID = p.BookingID
            WHERE 1=1";

    $params = [];
    $types = "";

    if (!empty($filters['search'])) {
        $sql .= " AND (u.FullName LIKE ? OR u.MatricNo LIKE ? OR r.FromLocation LIKE ? OR r.ToLocation LIKE ? OR d.FullName LIKE ?)";
        $search_param = "%{$filters['search']}%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
        $types .= "sssss";
    }

    if (!empty($filters['status'])) {
        $sql .= " AND b.BookingStatus = ?";
        $params[] = $filters['status'];
        $types .= "s";
    }

    if (!empty($filters['date_from'])) {
        $sql .= " AND DATE(b.BookingDateTime) >= ?";
        $params[] = $filters['date_from'];
        $types .= "s";
    }

    if (!empty($filters['date_to'])) {
        $sql .= " AND DATE(b.BookingDateTime) <= ?";
        $params[] = $filters['date_to'];
        $types .= "s";
    }

    if (!empty($filters['payment_method'])) {
        $sql .= " AND p.PaymentMethod = ?";
        $params[] = $filters['payment_method'];
        $types .= "s";
    }

    $stmt = $conn->prepare($sql);

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    $stmt->close();

    return $count;
}
