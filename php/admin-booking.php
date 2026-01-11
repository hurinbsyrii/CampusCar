<?php
session_start();

// Ensure admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Database connection
$servername = "127.0.0.1:3301";
$username = "root";
$password = "";
$dbname = "campuscar";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle Export CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'export_csv') {
    // TUKAR SEMUA $_GET KEPADA $_POST DI SINI
    $search_term = isset($_POST['search']) ? $conn->real_escape_string($_POST['search']) : '';
    $status_filter = isset($_POST['status']) ? $conn->real_escape_string($_POST['status']) : '';
    $date_from = isset($_POST['date_from']) ? $conn->real_escape_string($_POST['date_from']) : '';
    $date_to = isset($_POST['date_to']) ? $conn->real_escape_string($_POST['date_to']) : '';
    $payment_method = isset($_POST['payment_method']) ? $conn->real_escape_string($_POST['payment_method']) : '';
    $min_price = isset($_POST['min_price']) ? floatval($_POST['min_price']) : '';
    $max_price = isset($_POST['max_price']) ? floatval($_POST['max_price']) : '';

    // Build export query with same filters as main query
    $export_sql = "SELECT 
        b.*,
        u.FullName as UserName,
        u.MatricNo as UserMatric,
        u.Email as UserEmail,
        u.PhoneNumber as UserPhone,
        r.FromLocation,
        r.ToLocation,
        r.RideDate,
        r.DepartureTime,
        r.PricePerSeat,
        r.Status as RideStatus,
        d.FullName as DriverName,
        p.PaymentMethod,
        p.PaymentStatus,
        p.ProofPath,
        p.TransactionID,
        p.PaymentDate,
        de.Amount as DriverEarnings,
        de.PaymentDate as DriverPaymentDate
        FROM booking b
        LEFT JOIN user u ON b.UserID = u.UserID
        LEFT JOIN rides r ON b.RideID = r.RideID
        LEFT JOIN driver dr ON r.DriverID = dr.DriverID
        LEFT JOIN user d ON dr.UserID = d.UserID
        LEFT JOIN payments p ON b.BookingID = p.BookingID
        LEFT JOIN driver_earnings de ON b.BookingID = de.BookingID
        WHERE 1=1";

    if (!empty($search_term)) {
        $export_sql .= " AND (u.FullName LIKE '%$search_term%' 
                          OR u.MatricNo LIKE '%$search_term%'
                          OR r.FromLocation LIKE '%$search_term%'
                          OR r.ToLocation LIKE '%$search_term%'
                          OR d.FullName LIKE '%$search_term%')";
    }

    if (!empty($status_filter)) {
        $export_sql .= " AND b.BookingStatus = '$status_filter'";
    }

    if (!empty($date_from)) {
        $export_sql .= " AND DATE(b.BookingDateTime) >= '$date_from'";
    }

    if (!empty($date_to)) {
        $export_sql .= " AND DATE(b.BookingDateTime) <= '$date_to'";
    }

    if (!empty($payment_method)) {
        $export_sql .= " AND p.PaymentMethod = '$payment_method'";
    }

    if (!empty($min_price) && is_numeric($min_price)) {
        $export_sql .= " AND b.TotalPrice >= $min_price";
    }

    if (!empty($max_price) && is_numeric($max_price)) {
        $export_sql .= " AND b.TotalPrice <= $max_price";
    }

    $export_sql .= " ORDER BY b.BookingDateTime DESC";

    $export_result = $conn->query($export_sql);

    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="bookings_export_' . date('Y-m-d_H-i-s') . '.csv"');

    // Create output stream
    $output = fopen('php://output', 'w');

    // Add BOM for UTF-8
    fwrite($output, "\xEF\xBB\xBF");

    // CSV headers
    $headers = [
        'Booking ID',
        'Ride ID',
        'User ID',
        'Booking Date Time',
        'No of Seats',
        'Total Price',
        'Booking Status',
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
        'Ride Status',
        'Driver Name',
        'Payment Method',
        'Payment Status',
        'Proof Path',
        'Transaction ID',
        'Payment Date',
        'Driver Earnings',
        'Driver Payment Date'
    ];

    fputcsv($output, $headers);

    // Add data rows
    while ($row = $export_result->fetch_assoc()) {
        $csv_row = [
            $row['BookingID'],
            $row['RideID'],
            $row['UserID'],
            $row['BookingDateTime'],
            $row['NoOfSeats'],
            $row['TotalPrice'],
            $row['BookingStatus'],
            $row['CancellationReason'],
            $row['UserName'],
            $row['UserMatric'],
            $row['UserEmail'],
            $row['UserPhone'],
            $row['FromLocation'],
            $row['ToLocation'],
            $row['RideDate'],
            $row['DepartureTime'],
            $row['PricePerSeat'],
            $row['RideStatus'],
            $row['DriverName'],
            $row['PaymentMethod'],
            $row['PaymentStatus'],
            $row['ProofPath'],
            $row['TransactionID'],
            $row['PaymentDate'],
            $row['DriverEarnings'],
            $row['DriverPaymentDate']
        ];

        fputcsv($output, $csv_row);
    }

    fclose($output);
    exit();
}

// Handle status update (existing code remains the same)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['booking_id'])) {
    $booking_id = intval($_POST['booking_id']);
    $action = $_POST['action'];

    if ($action === 'update_status' && isset($_POST['new_status'])) {
        $new_status = $conn->real_escape_string($_POST['new_status']);
        $cancellation_reason = isset($_POST['cancellation_reason']) ? $conn->real_escape_string($_POST['cancellation_reason']) : '';

        // Get current booking info
        $booking_info = $conn->query("
            SELECT b.*, r.AvailableSeats, r.RideID 
            FROM booking b 
            LEFT JOIN rides r ON b.RideID = r.RideID 
            WHERE b.BookingID = $booking_id
        ")->fetch_assoc();

        // Validate status transition
        $valid_transitions = [
            'Pending' => ['Confirmed', 'Cancelled'],
            'Confirmed' => ['Paid', 'Cancelled'],
            'Paid' => ['Completed', 'Cancelled'],
            'Completed' => [],
            'Cancelled' => []
        ];

        $current_status = $booking_info['BookingStatus'];

        if (in_array($new_status, $valid_transitions[$current_status])) {
            // Start transaction
            $conn->begin_transaction();

            try {
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

                // If status changed from Cancelled to something else, update ride seats
                if ($current_status === 'Cancelled' && $new_status !== 'Cancelled') {
                    $update_seats = $conn->prepare("UPDATE rides SET AvailableSeats = AvailableSeats - ? WHERE RideID = ?");
                    $update_seats->bind_param("ii", $booking_info['NoOfSeats'], $booking_info['RideID']);
                    $update_seats->execute();
                    $update_seats->close();
                }
                // If status changed to Cancelled, add seats back
                elseif ($new_status === 'Cancelled' && $current_status !== 'Cancelled') {
                    $update_seats = $conn->prepare("UPDATE rides SET AvailableSeats = AvailableSeats + ? WHERE RideID = ?");
                    $update_seats->bind_param("ii", $booking_info['NoOfSeats'], $booking_info['RideID']);
                    $update_seats->execute();
                    $update_seats->close();
                }

                // If marking as Paid, check/create payment record
                if ($new_status === 'Paid' || $new_status === 'Completed') {
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

                // If marking as Completed, check/create driver earnings
                if ($new_status === 'Completed') {
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

                // Send notification to user
                $notification_msg = "Your booking status has been updated to: $new_status";
                if ($new_status === 'Cancelled' && !empty($cancellation_reason)) {
                    $notification_msg .= ". Reason: $cancellation_reason";
                }

                $conn->query("
                    INSERT INTO notifications (UserID, Title, Message, Type, CreatedAt, RelatedID, RelatedType) 
                    VALUES ({$booking_info['UserID']}, 'Booking Status Updated', 
                    '$notification_msg', 'info', NOW(), $booking_id, 'booking')
                ");

                $conn->commit();

                $_SESSION['notification'] = [
                    'message' => "Booking status updated to $new_status successfully",
                    'type' => 'success'
                ];
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['notification'] = [
                    'message' => "Error updating booking: " . $e->getMessage(),
                    'type' => 'error'
                ];
            }
        } else {
            $_SESSION['notification'] = [
                'message' => "Invalid status transition from $current_status to $new_status",
                'type' => 'error'
            ];
        }

        header("Location: admin-booking.php");
        exit();
    }
}

// Get search and filter parameters (existing code remains the same)
$search_term = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? $conn->real_escape_string($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? $conn->real_escape_string($_GET['date_to']) : '';
$payment_method = isset($_GET['payment_method']) ? $conn->real_escape_string($_GET['payment_method']) : '';
$min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : '';
$max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : '';

// Build query with joins (existing code remains the same)
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
    r.PricePerSeat,
    r.Status as RideStatus,
    d.FullName as DriverName,
    p.PaymentMethod,
    p.PaymentStatus,
    p.ProofPath,
    p.TransactionID,
    p.PaymentDate,
    de.Amount as DriverEarnings,
    de.PaymentDate as DriverPaymentDate
    FROM booking b
    LEFT JOIN user u ON b.UserID = u.UserID
    LEFT JOIN rides r ON b.RideID = r.RideID
    LEFT JOIN driver dr ON r.DriverID = dr.DriverID
    LEFT JOIN user d ON dr.UserID = d.UserID
    LEFT JOIN payments p ON b.BookingID = p.BookingID
    LEFT JOIN driver_earnings de ON b.BookingID = de.BookingID
    WHERE 1=1";

if (!empty($search_term)) {
    $sql .= " AND (u.FullName LIKE '%$search_term%' 
                  OR u.MatricNo LIKE '%$search_term%'
                  OR r.FromLocation LIKE '%$search_term%'
                  OR r.ToLocation LIKE '%$search_term%'
                  OR d.FullName LIKE '%$search_term%')";
}

if (!empty($status_filter)) {
    $sql .= " AND b.BookingStatus = '$status_filter'";
}

if (!empty($date_from)) {
    $sql .= " AND DATE(b.BookingDateTime) >= '$date_from'";
}

if (!empty($date_to)) {
    $sql .= " AND DATE(b.BookingDateTime) <= '$date_to'";
}

if (!empty($payment_method)) {
    $sql .= " AND p.PaymentMethod = '$payment_method'";
}

if (!empty($min_price) && is_numeric($min_price)) {
    $sql .= " AND b.TotalPrice >= $min_price";
}

if (!empty($max_price) && is_numeric($max_price)) {
    $sql .= " AND b.TotalPrice <= $max_price";
}

$sql .= " ORDER BY b.BookingDateTime DESC";

$result = $conn->query($sql);

// Get statistics (existing code remains the same)
$total_bookings = $conn->query("SELECT COUNT(*) as count FROM booking")->fetch_assoc()['count'];
$pending_bookings = $conn->query("SELECT COUNT(*) as count FROM booking WHERE BookingStatus = 'Pending'")->fetch_assoc()['count'];
$confirmed_bookings = $conn->query("SELECT COUNT(*) as count FROM booking WHERE BookingStatus = 'Confirmed'")->fetch_assoc()['count'];
$paid_bookings = $conn->query("SELECT COUNT(*) as count FROM booking WHERE BookingStatus = 'Paid'")->fetch_assoc()['count'];
$completed_bookings = $conn->query("SELECT COUNT(*) as count FROM booking WHERE BookingStatus = 'Completed'")->fetch_assoc()['count'];
$cancelled_bookings = $conn->query("SELECT COUNT(*) as count FROM booking WHERE BookingStatus = 'Cancelled'")->fetch_assoc()['count'];

// Revenue statistics
$total_revenue = $conn->query("SELECT COALESCE(SUM(TotalPrice), 0) as total FROM booking WHERE BookingStatus IN ('Paid', 'Completed')")->fetch_assoc()['total'];
$today_revenue = $conn->query("SELECT COALESCE(SUM(TotalPrice), 0) as total FROM booking WHERE DATE(BookingDateTime) = CURDATE() AND BookingStatus IN ('Paid', 'Completed')")->fetch_assoc()['total'];
$month_revenue = $conn->query("SELECT COALESCE(SUM(TotalPrice), 0) as total FROM booking WHERE MONTH(BookingDateTime) = MONTH(CURDATE()) AND YEAR(BookingDateTime) = YEAR(CURDATE()) AND BookingStatus IN ('Paid', 'Completed')")->fetch_assoc()['total'];

// Analytics data
// Popular routes - KEPT AS REQUESTED
$popular_routes = $conn->query("
    SELECT CONCAT(r.FromLocation, ' → ', r.ToLocation) as route, 
           COUNT(b.BookingID) as booking_count,
           AVG(b.TotalPrice) as avg_price,
           SUM(b.TotalPrice) as total_revenue
    FROM booking b
    JOIN rides r ON b.RideID = r.RideID
    WHERE b.BookingStatus IN ('Paid', 'Completed')
    GROUP BY r.FromLocation, r.ToLocation
    ORDER BY booking_count DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Booking trends (last 7 days)
$booking_trends = $conn->query("
    SELECT DATE(BookingDateTime) as date,
           COUNT(*) as total_bookings,
           SUM(CASE WHEN BookingStatus IN ('Paid', 'Completed') THEN TotalPrice ELSE 0 END) as daily_revenue,
           SUM(CASE WHEN BookingStatus = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_count
    FROM booking
    WHERE BookingDateTime >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(BookingDateTime)
    ORDER BY date DESC
")->fetch_all(MYSQLI_ASSOC);

// Payment method distribution
$payment_distribution = $conn->query("
    SELECT 
        COALESCE(p.PaymentMethod, 'Not Paid') as payment_method,
        COUNT(*) as booking_count,
        SUM(b.TotalPrice) as total_amount
    FROM booking b
    LEFT JOIN payments p ON b.BookingID = p.BookingID
    WHERE b.BookingStatus IN ('Paid', 'Completed')
    GROUP BY p.PaymentMethod
    ORDER BY booking_count DESC
")->fetch_all(MYSQLI_ASSOC);

// Get unique statuses for filter
$statuses_result = $conn->query("SELECT DISTINCT BookingStatus FROM booking ORDER BY BookingStatus");
$statuses = [];
while ($row = $statuses_result->fetch_assoc()) {
    $statuses[] = $row['BookingStatus'];
}

// Get unique payment methods for filter
$payment_methods_result = $conn->query("SELECT DISTINCT PaymentMethod FROM payments WHERE PaymentMethod IS NOT NULL ORDER BY PaymentMethod");
$payment_methods = [];
while ($row = $payment_methods_result->fetch_assoc()) {
    $payment_methods[] = $row['PaymentMethod'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Management - CampusCar Admin</title>
    <link rel="stylesheet" href="../css/admin-booking.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admindashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
    <div class="admin-container">
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <div class="admin-avatar">
                    <i class="fa-solid fa-user-shield"></i>
                </div>
                <div class="admin-details">
                    <h3>Admin Panel</h3>
                    <span class="admin-role"><?php echo $_SESSION['full_name'] ?? 'Admin'; ?></span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <ul>
                    <li class="nav-item">
                        <a href="admindashboard.php" class="nav-link">
                            <i class="fa-solid fa-gauge-high"></i>
                            <span>Admin Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="admin-user.php" class="nav-link">
                            <i class="fa-solid fa-users"></i>
                            <span>Users</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="admin-driver.php" class="nav-link">
                            <i class="fa-solid fa-id-card"></i>
                            <span>Drivers</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="admin-rides.php" class="nav-link">
                            <i class="fa-solid fa-car"></i>
                            <span>Rides</span>
                        </a>
                    </li>
                    <li class="nav-item active">
                        <a href="admin-booking.php" class="nav-link">
                            <i class="fa-solid fa-ticket"></i>
                            <span>Bookings</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../php/logout.php" class="nav-link logout">
                            <i class="fa-solid fa-right-from-bracket"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <div class="admin-main">
            <header class="admin-header">
                <div class="header-content">
                    <button id="sidebarToggle" class="sidebar-toggle">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <div class="logo">
                        <i class="fa-solid fa-car-side"></i>
                        <span>CampusCar <span class="admin-badge">Booking Management</span></span>
                    </div>
                    <div class="header-info">
                        <div class="today-stats">
                            <span class="stat-badge">
                                <i class="fa-solid fa-ticket"></i> <?php echo $total_bookings; ?> total
                            </span>
                            <span class="stat-badge">
                                <i class="fa-solid fa-money-bill-wave"></i> RM <?php echo number_format($today_revenue, 2); ?> today
                            </span>
                        </div>
                    </div>
                </div>
            </header>

            <main class="dashboard-content">
                <section class="stats-section">
                    <h2><i class="fa-solid fa-chart-line"></i> Booking Analytics</h2>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon total">
                                <i class="fa-solid fa-ticket"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Total Bookings</h3>
                                <span class="stat-number"><?php echo $total_bookings; ?></span>
                                <span class="stat-change">All-time bookings</span>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon revenue">
                                <i class="fa-solid fa-money-bill-wave"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Total Revenue</h3>
                                <span class="stat-number">RM <?php echo number_format($total_revenue, 2); ?></span>
                                <span class="stat-change">RM <?php echo number_format($month_revenue, 2); ?> this month</span>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon completed">
                                <i class="fa-solid fa-check-circle"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Completed</h3>
                                <span class="stat-number"><?php echo $completed_bookings; ?></span>
                                <span class="stat-change"><?php echo $paid_bookings; ?> paid bookings</span>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon cancelled">
                                <i class="fa-solid fa-times-circle"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Cancelled</h3>
                                <span class="stat-number"><?php echo $cancelled_bookings; ?></span>
                                <?php
                                $cancellation_rate = $total_bookings > 0 ? ($cancelled_bookings / $total_bookings * 100) : 0;
                                ?>
                                <span class="stat-change"><?php echo number_format($cancellation_rate, 1); ?>% cancellation rate</span>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="analytics-section">
                    <div class="section-header">
                        <h3><i class="fa-solid fa-chart-bar"></i> Booking Insights</h3>
                    </div>

                    <div class="analytics-grid">
                        <div class="analytics-card">
                            <div class="analytics-header">
                                <h4><i class="fa-solid fa-route"></i> Popular Routes</h4>
                            </div>
                            <div class="analytics-body">
                                <?php if (!empty($popular_routes)): ?>
                                    <table class="analytics-table">
                                        <thead>
                                            <tr>
                                                <th>Route</th>
                                                <th>Bookings</th>
                                                <th>Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($popular_routes as $route): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($route['route']); ?></td>
                                                    <td><?php echo $route['booking_count']; ?></td>
                                                    <td>RM <?php echo number_format($route['total_revenue'], 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <p class="no-data">No booking data available</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="analytics-card">
                            <div class="analytics-header">
                                <h4><i class="fa-solid fa-credit-card"></i> Payment Methods</h4>
                            </div>
                            <div class="analytics-body">
                                <?php if (!empty($payment_distribution)): ?>
                                    <table class="analytics-table">
                                        <thead>
                                            <tr>
                                                <th>Method</th>
                                                <th>Bookings</th>
                                                <th>Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($payment_distribution as $payment): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                                    <td><?php echo $payment['booking_count']; ?></td>
                                                    <td>RM <?php echo number_format($payment['total_amount'], 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <p class="no-data">No payment data available</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- <div class="analytics-card">
                            <div class="analytics-header">
                                <h4><i class="fa-solid fa-chart-line"></i> Recent Trends</h4>
                            </div>
                            <div class="analytics-body">
                                <?php if (!empty($booking_trends)): ?>
                                    <table class="analytics-table">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Bookings</th>
                                                <th>Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($booking_trends as $trend): ?>
                                                <tr>
                                                    <td><?php echo date('M d', strtotime($trend['date'])); ?></td>
                                                    <td><?php echo $trend['total_bookings']; ?></td>
                                                    <td>RM <?php echo number_format($trend['daily_revenue'], 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <p class="no-data">No trend data available</p>
                                <?php endif; ?>
                            </div>
                        </div> -->

                    </div>
                </section>

                <section class="bookings-section">
                    <div class="section-header">
                        <h3><i class="fa-solid fa-list"></i> All Bookings</h3>
                        <div class="section-actions">
                            <form method="GET" class="search-filter-form" id="filterForm">
                                <div class="search-box">
                                    <i class="fa-solid fa-search"></i>
                                    <input type="text" name="search" placeholder="Search bookings..."
                                        value="<?php echo htmlspecialchars($search_term); ?>">
                                </div>

                                <div class="filter-controls">
                                    <select name="status" class="filter-select">
                                        <option value="">All Status</option>
                                        <?php foreach ($statuses as $status): ?>
                                            <option value="<?php echo $status; ?>"
                                                <?php echo $status_filter === $status ? 'selected' : ''; ?>>
                                                <?php echo $status; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <select name="payment_method" class="filter-select">
                                        <option value="">All Payments</option>
                                        <?php foreach ($payment_methods as $method): ?>
                                            <option value="<?php echo $method; ?>"
                                                <?php echo $payment_method === $method ? 'selected' : ''; ?>>
                                                <?php echo ucfirst(str_replace('_', ' ', $method)); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <div class="date-range">
                                        <input type="date" name="date_from" placeholder="From Date"
                                            value="<?php echo $date_from; ?>" class="date-input">
                                        <span>to</span>
                                        <input type="date" name="date_to" placeholder="To Date"
                                            value="<?php echo $date_to; ?>" class="date-input">
                                    </div>

                                    <div class="price-range">
                                        <input type="number" name="min_price" placeholder="Min Price"
                                            value="<?php echo $min_price; ?>" class="price-input" min="0" step="0.01">
                                        <span>to</span>
                                        <input type="number" name="max_price" placeholder="Max Price"
                                            value="<?php echo $max_price; ?>" class="price-input" min="0" step="0.01">
                                    </div>

                                    <button type="submit" class="filter-btn">
                                        <i class="fa-solid fa-filter"></i> Filter
                                    </button>

                                    <button type="button" id="exportCsvBtn" class="export-btn">
                                        <i class="fa-solid fa-file-export"></i> Export CSV
                                    </button>

                                    <a href="admin-booking.php" class="clear-btn">
                                        <i class="fa-solid fa-times"></i> Clear
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <?php if (isset($_SESSION['notification'])): ?>
                        <div class="notification <?php echo $_SESSION['notification']['type']; ?>">
                            <i class="fa-solid fa-<?php echo $_SESSION['notification']['type'] === 'success' ? 'check' : 'exclamation-triangle'; ?>"></i>
                            <span><?php echo $_SESSION['notification']['message']; ?></span>
                            <button class="close-notification"><i class="fa-solid fa-times"></i></button>
                        </div>
                        <?php unset($_SESSION['notification']); ?>
                    <?php endif; ?>

                    <div id="exportLoading" class="export-loading" style="display: none;">
                        <div class="loading-spinner">
                            <i class="fa-solid fa-spinner fa-spin"></i>
                            <span>Preparing CSV export...</span>
                        </div>
                    </div>

                    <div class="bookings-table-container">
                        <table class="bookings-table">
                            <thead>
                                <tr>
                                    <th>Booking & User</th>
                                    <th>Ride Details</th>
                                    <th>Payment Info</th>
                                    <th>Date & Time</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result->num_rows > 0): ?>
                                    <?php while ($booking = $result->fetch_assoc()): ?>
                                        <?php
                                        // Calculate time status
                                        $booking_time = strtotime($booking['BookingDateTime']);
                                        $current_time = time();
                                        $time_diff = $current_time - $booking_time;
                                        $is_recent = $time_diff < 3600; // Within last hour
                                        ?>
                                        <tr class="<?php echo $is_recent ? 'recent-booking' : ''; ?>">
                                            <td>
                                                <div class="booking-user-info">
                                                    <div class="booking-header">
                                                        <span class="booking-id">#<?php echo $booking['BookingID']; ?></span>
                                                        <span class="booking-seats">
                                                            <i class="fa-solid fa-chair"></i>
                                                            <?php echo $booking['NoOfSeats']; ?> seat<?php echo $booking['NoOfSeats'] > 1 ? 's' : ''; ?>
                                                        </span>
                                                    </div>
                                                    <div class="user-info">
                                                        <p class="user-name">
                                                            <i class="fa-solid fa-user"></i>
                                                            <?php echo htmlspecialchars($booking['UserName']); ?>
                                                        </p>
                                                        <p>
                                                            <i class="fa-solid fa-id-card"></i>
                                                            <?php echo htmlspecialchars($booking['UserMatric']); ?>
                                                        </p>
                                                        <p>
                                                            <i class="fa-solid fa-phone"></i>
                                                            <?php echo htmlspecialchars($booking['UserPhone']); ?>
                                                        </p>
                                                        <p>
                                                            <i class="fa-solid fa-envelope"></i>
                                                            <?php echo htmlspecialchars($booking['UserEmail']); ?>
                                                        </p>
                                                    </div>
                                                    <div class="booking-price">
                                                        <strong>Total:</strong>
                                                        RM <?php echo number_format($booking['TotalPrice'], 2); ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="ride-info">
                                                    <div class="route-info">
                                                        <p>
                                                            <i class="fa-solid fa-location-dot"></i>
                                                            <?php echo htmlspecialchars($booking['FromLocation']); ?>
                                                        </p>
                                                        <div class="route-arrow">
                                                            <i class="fa-solid fa-arrow-right"></i>
                                                        </div>
                                                        <p>
                                                            <i class="fa-solid fa-map-marker-alt"></i>
                                                            <?php echo htmlspecialchars($booking['ToLocation']); ?>
                                                        </p>
                                                    </div>
                                                    <div class="ride-details">
                                                        <p>
                                                            <i class="fa-solid fa-calendar"></i>
                                                            <?php echo date('M d, Y', strtotime($booking['RideDate'])); ?>
                                                        </p>
                                                        <p>
                                                            <i class="fa-solid fa-clock"></i>
                                                            <?php echo date('h:i A', strtotime($booking['DepartureTime'])); ?>
                                                        </p>
                                                        <?php if (!empty($booking['DriverName'])): ?>
                                                            <p>
                                                                <i class="fa-solid fa-car"></i>
                                                                Driver: <?php echo htmlspecialchars($booking['DriverName']); ?>
                                                            </p>
                                                        <?php endif; ?>
                                                        <p>
                                                            <i class="fa-solid fa-money-bill-wave"></i>
                                                            RM <?php echo number_format($booking['PricePerSeat'], 2); ?> per seat
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="payment-info">
                                                    <?php if (!empty($booking['PaymentMethod'])): ?>
                                                        <p>
                                                            <strong>Method:</strong>
                                                            <span class="payment-method"><?php echo ucfirst(str_replace('_', ' ', $booking['PaymentMethod'])); ?></span>
                                                        </p>
                                                        <p>
                                                            <strong>Status:</strong>
                                                            <span class="payment-status <?php echo strtolower($booking['PaymentStatus']); ?>">
                                                                <?php echo $booking['PaymentStatus']; ?>
                                                            </span>
                                                        </p>
                                                        <?php if (!empty($booking['TransactionID'])): ?>
                                                            <!-- <p>
                                                                <strong>Transaction:</strong>
                                                                <span class="transaction-id"><?php echo $booking['TransactionID']; ?></span>
                                                            </p> -->
                                                        <?php endif; ?>
                                                        <?php if (!empty($booking['ProofPath'])): ?>
                                                            <p>
                                                                <strong>Proof:</strong>
                                                                <a href="../<?php echo htmlspecialchars($booking['ProofPath']); ?>"
                                                                    target="_blank" class="proof-link">
                                                                    <i class="fa-solid fa-receipt"></i> View Proof
                                                                </a>
                                                            </p>
                                                        <?php endif; ?>
                                                        <?php if (!empty($booking['PaymentDate'])): ?>
                                                            <p>
                                                                <strong>Paid on:</strong>
                                                                <?php echo date('M d, Y H:i', strtotime($booking['PaymentDate'])); ?>
                                                            </p>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <p class="no-payment">No payment recorded</p>
                                                    <?php endif; ?>
                                                    <?php if (!empty($booking['DriverEarnings'])): ?>
                                                        <p>
                                                            <strong>Driver Earned:</strong>
                                                            RM <?php echo number_format($booking['DriverEarnings'], 2); ?>
                                                        </p>
                                                        <?php if (!empty($booking['DriverPaymentDate'])): ?>
                                                            <p>
                                                                <strong>Paid on:</strong>
                                                                <?php echo date('M d, Y H:i', strtotime($booking['DriverPaymentDate'])); ?>
                                                            </p>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="datetime-info">
                                                    <p class="date">
                                                        <i class="fa-solid fa-calendar-day"></i>
                                                        <?php echo date('M d, Y', strtotime($booking['BookingDateTime'])); ?>
                                                    </p>
                                                    <p class="time">
                                                        <i class="fa-solid fa-clock"></i>
                                                        <?php echo date('h:i A', strtotime($booking['BookingDateTime'])); ?>
                                                    </p>
                                                    <?php if ($is_recent): ?>
                                                        <p class="time-status recent">
                                                            <i class="fa-solid fa-bolt"></i> Just Now
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower($booking['BookingStatus']); ?>">
                                                    <?php echo $booking['BookingStatus']; ?>
                                                </span>
                                                <?php if ($booking['BookingStatus'] === 'Cancelled' && !empty($booking['CancellationReason'])): ?>
                                                    <div class="cancellation-reason">
                                                        <small>
                                                            <i class="fa-solid fa-exclamation-circle"></i>
                                                            <?php echo htmlspecialchars($booking['CancellationReason']); ?>
                                                        </small>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <?php if ($booking['BookingStatus'] !== 'Completed' && $booking['BookingStatus'] !== 'Cancelled'): ?>
                                                        <button class="action-btn update-btn"
                                                            data-id="<?php echo $booking['BookingID']; ?>"
                                                            data-current-status="<?php echo $booking['BookingStatus']; ?>">
                                                            <i class="fa-solid fa-sync-alt"></i> Update
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="no-data-cell">
                                            <div class="no-data">
                                                <i class="fa-solid fa-ticket-slash"></i>
                                                <p>No bookings found</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>

                        <div class="pagination">
                            <button class="pagination-btn disabled">
                                <i class="fa-solid fa-chevron-left"></i> Previous
                            </button>
                            <span class="page-info">Page 1 of 1</span>
                            <button class="pagination-btn disabled">
                                Next <i class="fa-solid fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </section>

                <div id="statusModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Update Booking Status</h3>
                            <button class="close-modal">&times;</button>
                        </div>
                        <div class="modal-body">
                            <form id="statusForm" method="POST">
                                <input type="hidden" name="booking_id" id="modalBookingId">
                                <input type="hidden" name="action" value="update_status">

                                <div class="form-section">
                                    <div class="form-group">
                                        <label for="new_status">
                                            <i class="fa-solid fa-sync-alt"></i>
                                            Select New Status *
                                        </label>
                                        <select name="new_status" id="new_status" class="status-select" required>
                                            <option value="">-- Select Status --</option>
                                        </select>
                                        <small class="help-text" id="statusHelp"></small>
                                    </div>

                                    <div class="current-status-info">
                                        <p><strong>Current Status:</strong> <span id="currentStatusDisplay"></span></p>
                                    </div>

                                    <div id="cancellationSection" style="display: none;">
                                        <div class="form-group">
                                            <label for="cancellation_reason">
                                                <i class="fa-solid fa-exclamation-circle"></i>
                                                Cancellation Reason *
                                            </label>
                                            <textarea id="cancellation_reason" name="cancellation_reason"
                                                rows="4" placeholder="Please provide a reason for cancellation..."
                                                required></textarea>
                                            <small class="help-text">This will be shown to the user</small>
                                        </div>
                                    </div>

                                    <div class="status-options" id="statusOptions">
                                    </div>
                                </div>

                                <div class="modal-actions">
                                    <button type="button" class="btn-secondary close-modal-btn">Cancel</button>
                                    <button type="submit" class="btn-primary">Update Status</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="../js/admin-booking.js?v=<?php echo time(); ?>"></script>
</body>

</html>
<?php $conn->close(); ?>