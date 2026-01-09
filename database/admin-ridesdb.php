<?php
// admin-ridesdb.php - Database functions for ride management

function getRideDetails($conn, $ride_id)
{
    $sql = "SELECT 
        r.*,
        d.DriverID,
        d.CarModel,
        d.CarPlateNumber,
        d.LicenseNumber,
        u.FullName as DriverName,
        u.PhoneNumber as DriverPhone,
        u.Email as DriverEmail,
        u.MatricNo as DriverMatric,
        COUNT(DISTINCT b.BookingID) as TotalBookings,
        COUNT(DISTINCT CASE WHEN b.BookingStatus = 'Confirmed' THEN b.BookingID END) as ConfirmedBookings,
        COALESCE(SUM(de.Amount), 0) as TotalEarnings,
        GROUP_CONCAT(DISTINCT CONCAT(u2.FullName, ' (', b2.NoOfSeats, ' seats)') SEPARATOR '; ') as Passengers
        FROM rides r
        LEFT JOIN driver d ON r.DriverID = d.DriverID
        LEFT JOIN user u ON d.UserID = u.UserID
        LEFT JOIN booking b ON r.RideID = b.RideID
        LEFT JOIN booking b2 ON r.RideID = b2.RideID AND b2.BookingStatus = 'Confirmed'
        LEFT JOIN user u2 ON b2.UserID = u2.UserID
        LEFT JOIN driver_earnings de ON r.RideID = de.RideID
        WHERE r.RideID = ?
        GROUP BY r.RideID";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $ride_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    return $result->fetch_assoc();
}

function updateRideStatus($conn, $ride_id, $new_status)
{
    $sql = "UPDATE rides SET Status = ? WHERE RideID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $new_status, $ride_id);
    $result = $stmt->execute();
    $stmt->close();

    if ($result) {
        // Log the status change
        logRideActivity($conn, $ride_id, "status_change", "Status changed to $new_status");

        // If marking as completed, check if driver earnings should be recorded
        if ($new_status === 'completed') {
            recordDriverEarnings($conn, $ride_id);
        }
    }

    return $result;
}

function recordDriverEarnings($conn, $ride_id)
{
    // Check if earnings already recorded
    $check_sql = "SELECT COUNT(*) as count FROM driver_earnings WHERE RideID = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("i", $ride_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    $stmt->close();

    if ($count > 0) {
        return true; // Already recorded
    }

    // Calculate total earnings from confirmed bookings
    $earnings_sql = "SELECT 
        b.RideID,
        d.DriverID,
        SUM(b.TotalPrice) as TotalAmount
        FROM booking b
        JOIN rides r ON b.RideID = r.RideID
        JOIN driver d ON r.DriverID = d.DriverID
        WHERE b.RideID = ? AND b.BookingStatus IN ('Confirmed', 'Paid', 'Completed')
        GROUP BY b.RideID, d.DriverID";

    $stmt = $conn->prepare($earnings_sql);
    $stmt->bind_param("i", $ride_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $earnings = $result->fetch_assoc();

        $insert_sql = "INSERT INTO driver_earnings (DriverID, RideID, BookingID, Amount, PaymentDate, CreatedAt) 
                      VALUES (?, ?, NULL, ?, NOW(), NOW())";
        $stmt2 = $conn->prepare($insert_sql);
        $stmt2->bind_param("iid", $earnings['DriverID'], $earnings['RideID'], $earnings['TotalAmount']);
        $stmt2->execute();
        $stmt2->close();
    }

    $stmt->close();
    return true;
}

function getRideStatistics($conn)
{
    $stats = [];

    // Total counts
    $stats['total'] = $conn->query("SELECT COUNT(*) as count FROM rides")->fetch_assoc()['count'];
    $stats['available'] = $conn->query("SELECT COUNT(*) as count FROM rides WHERE Status = 'available'")->fetch_assoc()['count'];
    $stats['in_progress'] = $conn->query("SELECT COUNT(*) as count FROM rides WHERE Status = 'in_progress'")->fetch_assoc()['count'];
    $stats['completed'] = $conn->query("SELECT COUNT(*) as count FROM rides WHERE Status = 'completed'")->fetch_assoc()['count'];
    $stats['expired'] = $conn->query("SELECT COUNT(*) as count FROM rides WHERE Status = 'expired'")->fetch_assoc()['count'];
    $stats['female_only'] = $conn->query("SELECT COUNT(*) as count FROM rides WHERE FemaleOnly = 1")->fetch_assoc()['count'];

    // Revenue statistics
    $stats['total_revenue'] = $conn->query("SELECT COALESCE(SUM(TotalPrice), 0) as total FROM booking WHERE BookingStatus NOT IN ('Cancelled')")->fetch_assoc()['total'];
    $stats['today_revenue'] = $conn->query("SELECT COALESCE(SUM(TotalPrice), 0) as total FROM booking WHERE DATE(BookingDateTime) = CURDATE() AND BookingStatus NOT IN ('Cancelled')")->fetch_assoc()['total'];
    $stats['month_revenue'] = $conn->query("SELECT COALESCE(SUM(TotalPrice), 0) as total FROM booking WHERE MONTH(BookingDateTime) = MONTH(CURDATE()) AND YEAR(BookingDateTime) = YEAR(CURDATE()) AND BookingStatus NOT IN ('Cancelled')")->fetch_assoc()['total'];

    // Popular routes
    $stats['popular_routes'] = $conn->query("
        SELECT CONCAT(FromLocation, ' → ', ToLocation) as route, 
               COUNT(*) as ride_count,
               COUNT(DISTINCT DriverID) as driver_count,
               AVG(PricePerSeat) as avg_price
        FROM rides 
        WHERE Status = 'completed'
        GROUP BY FromLocation, ToLocation
        ORDER BY ride_count DESC
        LIMIT 5
    ")->fetch_all(MYSQLI_ASSOC);

    // Rides by day of week
    $stats['by_weekday'] = $conn->query("
        SELECT DAYNAME(RideDate) as weekday, 
               COUNT(*) as ride_count
        FROM rides 
        GROUP BY DAYOFWEEK(RideDate), weekday
        ORDER BY DAYOFWEEK(RideDate)
    ")->fetch_all(MYSQLI_ASSOC);

    // Recent rides (last 7 days)
    $stats['recent_rides'] = $conn->query("
        SELECT DATE(RideDate) as date, 
               COUNT(*) as ride_count,
               SUM(CASE WHEN Status = 'completed' THEN 1 ELSE 0 END) as completed_count
        FROM rides 
        WHERE RideDate >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(RideDate)
        ORDER BY date DESC
    ")->fetch_all(MYSQLI_ASSOC);

    return $stats;
}

function searchRides($conn, $filters = [])
{
    $sql = "SELECT 
        r.*,
        d.CarModel,
        d.CarPlateNumber,
        u.FullName as DriverName,
        COUNT(b.BookingID) as TotalBookings,
        COALESCE(SUM(de.Amount), 0) as TotalEarnings
        FROM rides r
        LEFT JOIN driver d ON r.DriverID = d.DriverID
        LEFT JOIN user u ON d.UserID = u.UserID
        LEFT JOIN booking b ON r.RideID = b.RideID AND b.BookingStatus NOT IN ('Cancelled')
        LEFT JOIN driver_earnings de ON r.RideID = de.RideID
        WHERE 1=1";

    $params = [];
    $types = "";

    if (!empty($filters['search'])) {
        $sql .= " AND (r.FromLocation LIKE ? OR r.ToLocation LIKE ? OR u.FullName LIKE ? OR d.CarPlateNumber LIKE ?)";
        $search_param = "%{$filters['search']}%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
        $types .= "ssss";
    }

    if (!empty($filters['status'])) {
        if ($filters['status'] === 'upcoming') {
            $sql .= " AND (r.Status = 'available' OR r.Status = 'in_progress') 
                      AND (r.RideDate > CURDATE() OR (r.RideDate = CURDATE() AND r.DepartureTime > CURTIME()))";
        } elseif ($filters['status'] === 'past') {
            $sql .= " AND (r.Status IN ('completed', 'expired') 
                      OR (r.RideDate < CURDATE()) 
                      OR (r.RideDate = CURDATE() AND r.DepartureTime < CURTIME()))";
        } else {
            $sql .= " AND r.Status = ?";
            $params[] = $filters['status'];
            $types .= "s";
        }
    }

    if (!empty($filters['date'])) {
        $sql .= " AND r.RideDate = ?";
        $params[] = $filters['date'];
        $types .= "s";
    }

    if (isset($filters['female_only'])) {
        if ($filters['female_only'] === '1') {
            $sql .= " AND r.FemaleOnly = 1";
        } elseif ($filters['female_only'] === '0') {
            $sql .= " AND r.FemaleOnly = 0";
        }
    }

    if (!empty($filters['from_location'])) {
        $sql .= " AND r.FromLocation LIKE ?";
        $params[] = "%{$filters['from_location']}%";
        $types .= "s";
    }

    if (!empty($filters['to_location'])) {
        $sql .= " AND r.ToLocation LIKE ?";
        $params[] = "%{$filters['to_location']}%";
        $types .= "s";
    }

    if (!empty($filters['driver_id'])) {
        $sql .= " AND r.DriverID = ?";
        $params[] = $filters['driver_id'];
        $types .= "i";
    }

    $sql .= " GROUP BY r.RideID ORDER BY r.RideDate DESC, r.DepartureTime DESC";

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
    $rides = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rides;
}

function getRideBookings($conn, $ride_id)
{
    $sql = "SELECT 
        b.*,
        u.FullName as PassengerName,
        u.Email as PassengerEmail,
        u.PhoneNumber as PassengerPhone
        FROM booking b
        JOIN user u ON b.UserID = u.UserID
        WHERE b.RideID = ?
        ORDER BY b.BookingDateTime DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $ride_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $bookings = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $bookings;
}

function logRideActivity($conn, $ride_id, $activity_type, $description)
{
    // This function would log to an activity table if it exists
    // For now, we'll just return true
    return true;
}

function exportRidesData($conn, $filters = [])
{
    // Build query with filters
    $sql = "SELECT 
        r.RideID,
        r.FromLocation,
        r.ToLocation,
        r.RideDate,
        r.DepartureTime,
        r.AvailableSeats,
        r.PricePerSeat,
        r.RideDescription,
        r.Status,
        r.FemaleOnly,
        r.FromLat,
        r.FromLng,
        r.ToLat,
        r.ToLng,
        r.DistanceKm,
        d.DriverID,
        d.CarModel,
        d.CarPlateNumber,
        d.LicenseNumber,
        u.FullName as DriverName,
        u.PhoneNumber as DriverPhone,
        u.Email as DriverEmail,
        COUNT(DISTINCT b.BookingID) as TotalBookings,
        COALESCE(SUM(b.TotalPrice), 0) as TotalRevenue,
        COALESCE(SUM(de.Amount), 0) as DriverEarnings,
        GROUP_CONCAT(DISTINCT CONCAT(u2.FullName, ' (', b2.NoOfSeats, ' seats)') SEPARATOR '; ') as Passengers
        FROM rides r
        LEFT JOIN driver d ON r.DriverID = d.DriverID
        LEFT JOIN user u ON d.UserID = u.UserID
        LEFT JOIN booking b ON r.RideID = b.RideID AND b.BookingStatus NOT IN ('Cancelled')
        LEFT JOIN booking b2 ON r.RideID = b2.RideID AND b2.BookingStatus IN ('Confirmed', 'Paid', 'Completed')
        LEFT JOIN user u2 ON b2.UserID = u2.UserID
        LEFT JOIN driver_earnings de ON r.RideID = de.RideID
        WHERE 1=1";

    // Apply filters
    if (!empty($filters['search'])) {
        $search = $conn->real_escape_string($filters['search']);
        $sql .= " AND (r.FromLocation LIKE '%$search%' 
                      OR r.ToLocation LIKE '%$search%'
                      OR u.FullName LIKE '%$search%'
                      OR d.CarPlateNumber LIKE '%$search%')";
    }

    if (!empty($filters['status'])) {
        $status = $conn->real_escape_string($filters['status']);
        if ($status === 'upcoming') {
            $sql .= " AND (r.Status = 'available' OR r.Status = 'in_progress') 
                      AND (r.RideDate > CURDATE() OR (r.RideDate = CURDATE() AND r.DepartureTime > CURTIME()))";
        } elseif ($status === 'past') {
            $sql .= " AND (r.Status IN ('completed', 'expired') 
                      OR (r.RideDate < CURDATE()) 
                      OR (r.RideDate = CURDATE() AND r.DepartureTime < CURTIME()))";
        } else {
            $sql .= " AND r.Status = '$status'";
        }
    }

    if (!empty($filters['date'])) {
        $date = $conn->real_escape_string($filters['date']);
        $sql .= " AND r.RideDate = '$date'";
    }

    if (isset($filters['female_only'])) {
        if ($filters['female_only'] === '1') {
            $sql .= " AND r.FemaleOnly = 1";
        } elseif ($filters['female_only'] === '0') {
            $sql .= " AND r.FemaleOnly = 0";
        }
    }

    $sql .= " GROUP BY r.RideID ORDER BY r.RideDate DESC, r.DepartureTime DESC";

    $result = $conn->query($sql);

    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=rides_export_' . date('Y-m-d_H-i-s') . '.csv');

    // Create output stream
    $output = fopen('php://output', 'w');

    // Add BOM for UTF-8
    fputs($output, "\xEF\xBB\xBF");

    // CSV headers
    $headers = [
        'Ride ID',
        'From Location',
        'To Location',
        'Ride Date',
        'Departure Time',
        'Available Seats',
        'Price Per Seat',
        'Description',
        'Status',
        'Female Only',
        'From Latitude',
        'From Longitude',
        'To Latitude',
        'To Longitude',
        'Distance (km)',
        'Driver ID',
        'Driver Name',
        'Driver Phone',
        'Driver Email',
        'Car Model',
        'Car Plate Number',
        'License Number',
        'Total Bookings',
        'Total Revenue (RM)',
        'Driver Earnings (RM)',
        'Passengers'
    ];

    fputcsv($output, $headers);

    // Add data rows
    while ($row = $result->fetch_assoc()) {
        $csv_row = [
            $row['RideID'],
            $row['FromLocation'],
            $row['ToLocation'],
            $row['RideDate'],
            $row['DepartureTime'],
            $row['AvailableSeats'],
            $row['PricePerSeat'],
            $row['RideDescription'],
            $row['Status'],
            $row['FemaleOnly'] ? 'Yes' : 'No',
            $row['FromLat'],
            $row['FromLng'],
            $row['ToLat'],
            $row['ToLng'],
            $row['DistanceKm'],
            $row['DriverID'],
            $row['DriverName'],
            $row['DriverPhone'],
            $row['DriverEmail'],
            $row['CarModel'],
            $row['CarPlateNumber'],
            $row['LicenseNumber'],
            $row['TotalBookings'],
            number_format($row['TotalRevenue'], 2),
            number_format($row['DriverEarnings'], 2),
            $row['Passengers'] ?? 'None'
        ];
        fputcsv($output, $csv_row);
    }

    fclose($output);
    exit();
}

function getRidesCount($conn, $filters = [])
{
    $sql = "SELECT COUNT(DISTINCT r.RideID) as count
            FROM rides r
            LEFT JOIN driver d ON r.DriverID = d.DriverID
            LEFT JOIN user u ON d.UserID = u.UserID
            WHERE 1=1";

    $params = [];
    $types = "";

    if (!empty($filters['search'])) {
        $sql .= " AND (r.FromLocation LIKE ? OR r.ToLocation LIKE ? OR u.FullName LIKE ? OR d.CarPlateNumber LIKE ?)";
        $search_param = "%{$filters['search']}%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
        $types .= "ssss";
    }

    if (!empty($filters['status'])) {
        if ($filters['status'] === 'upcoming') {
            $sql .= " AND (r.Status = 'available' OR r.Status = 'in_progress') 
                      AND (r.RideDate > CURDATE() OR (r.RideDate = CURDATE() AND r.DepartureTime > CURTIME()))";
        } elseif ($filters['status'] === 'past') {
            $sql .= " AND (r.Status IN ('completed', 'expired') 
                      OR (r.RideDate < CURDATE()) 
                      OR (r.RideDate = CURDATE() AND r.DepartureTime < CURTIME()))";
        } else {
            $sql .= " AND r.Status = ?";
            $params[] = $filters['status'];
            $types .= "s";
        }
    }

    if (!empty($filters['date'])) {
        $sql .= " AND r.RideDate = ?";
        $params[] = $filters['date'];
        $types .= "s";
    }

    if (isset($filters['female_only'])) {
        if ($filters['female_only'] === '1') {
            $sql .= " AND r.FemaleOnly = 1";
        } elseif ($filters['female_only'] === '0') {
            $sql .= " AND r.FemaleOnly = 0";
        }
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
