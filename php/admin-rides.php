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

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    exportRidesToCSV($conn);
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['ride_id'])) {
    $ride_id = intval($_POST['ride_id']);
    $action = $_POST['action'];

    if ($action === 'update_status' && isset($_POST['new_status'])) {
        $new_status = $conn->real_escape_string($_POST['new_status']);

        // Validate status transition
        $current_status = $conn->query("SELECT Status FROM rides WHERE RideID = $ride_id")->fetch_assoc()['Status'];

        $valid_transitions = [
            'available' => ['in_progress', 'cancelled'],
            'in_progress' => ['completed', 'cancelled'],
            'completed' => [],
            'cancelled' => [],
            'expired' => []
        ];

        if (in_array($new_status, $valid_transitions[$current_status])) {
            $stmt = $conn->prepare("UPDATE rides SET Status = ? WHERE RideID = ?");
            $stmt->bind_param("si", $new_status, $ride_id);

            if ($stmt->execute()) {
                $_SESSION['notification'] = [
                    'message' => "Ride status updated to " . ucfirst(str_replace('_', ' ', $new_status)),
                    'type' => 'success'
                ];
            }
            $stmt->close();
        } else {
            $_SESSION['notification'] = [
                'message' => "Invalid status transition from " . ucfirst(str_replace('_', ' ', $current_status)) . " to " . ucfirst(str_replace('_', ' ', $new_status)),
                'type' => 'error'
            ];
        }

        header("Location: admin-rides.php");
        exit();
    }
}

// Get search and filter parameters
$search_term = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
$date_filter = isset($_GET['date']) ? $conn->real_escape_string($_GET['date']) : '';
$female_only = isset($_GET['female_only']) ? $_GET['female_only'] : '';

// Build query with joins
$sql = "SELECT 
    r.*,
    d.DriverID,
    d.CarModel,
    d.CarPlateNumber,
    u.FullName as DriverName,
    u.PhoneNumber as DriverPhone,
    COUNT(b.BookingID) as TotalBookings,
    COALESCE(SUM(de.Amount), 0) as TotalEarnings
    FROM rides r
    LEFT JOIN driver d ON r.DriverID = d.DriverID
    LEFT JOIN user u ON d.UserID = u.UserID
    LEFT JOIN booking b ON r.RideID = b.RideID AND b.BookingStatus NOT IN ('Cancelled')
    LEFT JOIN driver_earnings de ON r.RideID = de.RideID
    WHERE 1=1";

if (!empty($search_term)) {
    $sql .= " AND (r.FromLocation LIKE '%$search_term%' 
                  OR r.ToLocation LIKE '%$search_term%'
                  OR u.FullName LIKE '%$search_term%'
                  OR d.CarPlateNumber LIKE '%$search_term%')";
}

if (!empty($status_filter)) {
    if ($status_filter === 'upcoming') {
        $sql .= " AND (r.Status = 'available' OR r.Status = 'in_progress') 
                  AND (r.RideDate > CURDATE() OR (r.RideDate = CURDATE() AND r.DepartureTime > CURTIME()))";
    } elseif ($status_filter === 'past') {
        $sql .= " AND (r.Status IN ('completed', 'expired') 
                  OR (r.RideDate < CURDATE()) 
                  OR (r.RideDate = CURDATE() AND r.DepartureTime < CURTIME()))";
    } else {
        $sql .= " AND r.Status = '$status_filter'";
    }
}

if (!empty($date_filter)) {
    $sql .= " AND r.RideDate = '$date_filter'";
}

if ($female_only === '1') {
    $sql .= " AND r.FemaleOnly = 1";
} elseif ($female_only === '0') {
    $sql .= " AND r.FemaleOnly = 0";
}

$sql .= " GROUP BY r.RideID ORDER BY r.RideDate DESC, r.DepartureTime DESC";

$result = $conn->query($sql);

// Get statistics
$total_rides = $conn->query("SELECT COUNT(*) as count FROM rides")->fetch_assoc()['count'];
$available_rides = $conn->query("SELECT COUNT(*) as count FROM rides WHERE Status = 'available'")->fetch_assoc()['count'];
$in_progress_rides = $conn->query("SELECT COUNT(*) as count FROM rides WHERE Status = 'in_progress'")->fetch_assoc()['count'];
$completed_rides = $conn->query("SELECT COUNT(*) as count FROM rides WHERE Status = 'completed'")->fetch_assoc()['count'];
$female_only_rides = $conn->query("SELECT COUNT(*) as count FROM rides WHERE FemaleOnly = 1")->fetch_assoc()['count'];

// Get total revenue
$total_revenue = $conn->query("SELECT COALESCE(SUM(TotalPrice), 0) as total FROM booking WHERE BookingStatus NOT IN ('Cancelled')")->fetch_assoc()['total'];
$today_revenue = $conn->query("SELECT COALESCE(SUM(TotalPrice), 0) as total FROM booking WHERE DATE(BookingDateTime) = CURDATE() AND BookingStatus NOT IN ('Cancelled')")->fetch_assoc()['total'];

// Get unique statuses for filter
$statuses_result = $conn->query("SELECT DISTINCT Status FROM rides ORDER BY Status");
$statuses = [];
while ($row = $statuses_result->fetch_assoc()) {
    $statuses[] = $row['Status'];
}

// Get unique ride dates for filter
$dates_result = $conn->query("SELECT DISTINCT RideDate FROM rides WHERE RideDate >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) ORDER BY RideDate DESC");
$dates = [];
while ($row = $dates_result->fetch_assoc()) {
    $dates[] = $row['RideDate'];
}

// Export CSV function
function exportRidesToCSV($conn)
{
    // Get filter parameters
    $search_term = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
    $status_filter = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
    $date_filter = isset($_GET['date']) ? $conn->real_escape_string($_GET['date']) : '';
    $female_only = isset($_GET['female_only']) ? $_GET['female_only'] : '';

    // Build export query with all columns
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

    if (!empty($search_term)) {
        $sql .= " AND (r.FromLocation LIKE '%$search_term%' 
                      OR r.ToLocation LIKE '%$search_term%'
                      OR u.FullName LIKE '%$search_term%'
                      OR d.CarPlateNumber LIKE '%$search_term%')";
    }

    if (!empty($status_filter)) {
        if ($status_filter === 'upcoming') {
            $sql .= " AND (r.Status = 'available' OR r.Status = 'in_progress') 
                      AND (r.RideDate > CURDATE() OR (r.RideDate = CURDATE() AND r.DepartureTime > CURTIME()))";
        } elseif ($status_filter === 'past') {
            $sql .= " AND (r.Status IN ('completed', 'expired') 
                      OR (r.RideDate < CURDATE()) 
                      OR (r.RideDate = CURDATE() AND r.DepartureTime < CURTIME()))";
        } else {
            $sql .= " AND r.Status = '$status_filter'";
        }
    }

    if (!empty($date_filter)) {
        $sql .= " AND r.RideDate = '$date_filter'";
    }

    if ($female_only === '1') {
        $sql .= " AND r.FemaleOnly = 1";
    } elseif ($female_only === '0') {
        $sql .= " AND r.FemaleOnly = 0";
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
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ride Management - CampusCar Admin</title>
    <link rel="stylesheet" href="../css/admin-rides.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admindashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
    <div class="admin-container">
        <!-- Sidebar -->
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
                    <li class="nav-item active">
                        <a href="admin-rides.php" class="nav-link">
                            <i class="fa-solid fa-car"></i>
                            <span>Rides</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="admin-booking.php" class="nav-link">
                            <i class="fa-solid fa-ticket"></i>
                            <span>Bookings</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="admin-reports.php" class="nav-link">
                            <i class="fa-solid fa-chart-pie"></i>
                            <span>Reports</span>
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

        <!-- Main Content -->
        <div class="admin-main">
            <!-- Header -->
            <header class="admin-header">
                <div class="header-content">
                    <button id="sidebarToggle" class="sidebar-toggle">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <div class="logo">
                        <i class="fa-solid fa-car-side"></i>
                        <span>CampusCar <span class="admin-badge">Ride Management</span></span>
                    </div>
                    <div class="header-info">
                        <div class="today-stats">
                            <span class="stat-badge">
                                <i class="fa-solid fa-car"></i> <?php echo $available_rides; ?> available
                            </span>
                            <span class="stat-badge">
                                <i class="fa-solid fa-money-bill-wave"></i> RM <?php echo number_format($today_revenue, 2); ?> today
                            </span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Ride Management Content -->
            <main class="dashboard-content">
                <!-- Stats Overview -->
                <section class="stats-section">
                    <h2><i class="fa-solid fa-road"></i> Ride Management</h2>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon total">
                                <i class="fa-solid fa-car"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Total Rides</h3>
                                <span class="stat-number"><?php echo $total_rides; ?></span>
                                <span class="stat-change">All rides offered</span>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon available">
                                <i class="fa-solid fa-check-circle"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Available</h3>
                                <span class="stat-number"><?php echo $available_rides; ?></span>
                                <span class="stat-change">Open for booking</span>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon in-progress">
                                <i class="fa-solid fa-spinner"></i>
                            </div>
                            <div class="stat-info">
                                <h3>In Progress</h3>
                                <span class="stat-number"><?php echo $in_progress_rides; ?></span>
                                <span class="stat-change">Currently ongoing</span>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon revenue">
                                <i class="fa-solid fa-money-bill-wave"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Total Revenue</h3>
                                <span class="stat-number">RM <?php echo number_format($total_revenue, 2); ?></span>
                                <span class="stat-change">All-time earnings</span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Rides Section -->
                <section class="rides-section">
                    <div class="section-header">
                        <h3><i class="fa-solid fa-list"></i> All Rides</h3>
                        <div class="section-actions">
                            <form method="GET" class="search-filter-form">
                                <div class="search-box">
                                    <i class="fa-solid fa-search"></i>
                                    <input type="text" name="search" placeholder="Search rides..."
                                        value="<?php echo htmlspecialchars($search_term); ?>">
                                </div>

                                <div class="filter-controls">
                                    <select name="status" class="filter-select">
                                        <option value="">All Status</option>
                                        <option value="available" <?php echo $status_filter === 'available' ? 'selected' : ''; ?>>Available</option>
                                        <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="expired" <?php echo $status_filter === 'expired' ? 'selected' : ''; ?>>Expired</option>
                                        <option value="upcoming" <?php echo $status_filter === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                                        <option value="past" <?php echo $status_filter === 'past' ? 'selected' : ''; ?>>Past</option>
                                    </select>

                                    <select name="date" class="filter-select">
                                        <option value="">All Dates</option>
                                        <?php foreach ($dates as $date): ?>
                                            <option value="<?php echo $date; ?>"
                                                <?php echo $date_filter === $date ? 'selected' : ''; ?>>
                                                <?php echo date('M d, Y', strtotime($date)); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <select name="female_only" class="filter-select">
                                        <option value="">All Types</option>
                                        <option value="1" <?php echo $female_only === '1' ? 'selected' : ''; ?>>Female Only</option>
                                        <option value="0" <?php echo $female_only === '0' ? 'selected' : ''; ?>>Mixed</option>
                                    </select>

                                    <button type="submit" class="filter-btn">
                                        <i class="fa-solid fa-filter"></i> Filter
                                    </button>

                                    <!-- Export CSV Button -->
                                    <button type="button" id="exportCsvBtn" class="export-btn">
                                        <i class="fa-solid fa-file-export"></i> Export CSV
                                    </button>

                                    <a href="admin-rides.php" class="clear-btn">
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

                    <!-- Export Loading Indicator -->
                    <div id="exportLoading" class="export-loading" style="display: none;">
                        <div class="loading-spinner">
                            <i class="fa-solid fa-spinner fa-spin"></i>
                        </div>
                        <p>Preparing CSV export... Please wait</p>
                    </div>

                    <div class="rides-table-container">
                        <table class="rides-table">
                            <thead>
                                <tr>
                                    <th>Ride Details</th>
                                    <th>Driver & Vehicle</th>
                                    <th>Booking Stats</th>
                                    <th>Date & Time</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result->num_rows > 0): ?>
                                    <?php while ($ride = $result->fetch_assoc()): ?>
                                        <?php
                                        // Calculate time status
                                        $ride_datetime = strtotime($ride['RideDate'] . ' ' . $ride['DepartureTime']);
                                        $current_datetime = time();
                                        $is_upcoming = $ride_datetime > $current_datetime;
                                        $is_past = $ride_datetime < $current_datetime;

                                        // Determine status class
                                        $status_class = 'status-' . str_replace('_', '-', $ride['Status']);
                                        if ($ride['Status'] === 'available' && $is_upcoming) {
                                            $status_class = 'status-upcoming';
                                        } elseif (($ride['Status'] === 'available' || $ride['Status'] === 'in_progress') && $is_past) {
                                            $status_class = 'status-overdue';
                                        }
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="ride-info">
                                                    <div class="route-info">
                                                        <h4>
                                                            <i class="fa-solid fa-location-dot"></i>
                                                            <?php echo htmlspecialchars($ride['FromLocation']); ?>
                                                        </h4>
                                                        <div class="route-arrow">
                                                            <i class="fa-solid fa-arrow-right"></i>
                                                        </div>
                                                        <h4>
                                                            <i class="fa-solid fa-map-marker-alt"></i>
                                                            <?php echo htmlspecialchars($ride['ToLocation']); ?>
                                                        </h4>
                                                    </div>
                                                    <div class="ride-details">
                                                        <p>
                                                            <i class="fa-solid fa-money-bill-wave"></i>
                                                            RM <?php echo number_format($ride['PricePerSeat'], 2); ?> per seat
                                                        </p>
                                                        <p>
                                                            <i class="fa-solid fa-chair"></i>
                                                            <?php echo $ride['AvailableSeats']; ?> seats available
                                                        </p>
                                                        <?php if ($ride['FemaleOnly']): ?>
                                                            <p class="female-only">
                                                                <i class="fa-solid fa-venus"></i> Female Only
                                                            </p>
                                                        <?php endif; ?>
                                                        <?php if (!empty($ride['RideDescription'])): ?>
                                                            <p class="ride-description">
                                                                <i class="fa-solid fa-comment"></i>
                                                                <?php echo htmlspecialchars($ride['RideDescription']); ?>
                                                            </p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="driver-info">
                                                    <?php if ($ride['DriverName']): ?>
                                                        <p class="driver-name">
                                                            <i class="fa-solid fa-user"></i>
                                                            <?php echo htmlspecialchars($ride['DriverName']); ?>
                                                        </p>
                                                        <p>
                                                            <i class="fa-solid fa-phone"></i>
                                                            <?php echo htmlspecialchars($ride['DriverPhone']); ?>
                                                        </p>
                                                        <p>
                                                            <i class="fa-solid fa-car"></i>
                                                            <?php echo htmlspecialchars($ride['CarModel']); ?>
                                                        </p>
                                                        <p>
                                                            <i class="fa-solid fa-tag"></i>
                                                            <?php echo htmlspecialchars($ride['CarPlateNumber']); ?>
                                                        </p>
                                                    <?php else: ?>
                                                        <p class="no-data">Driver not found</p>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="stats-info">
                                                    <p>
                                                        <strong>Bookings:</strong>
                                                        <span class="stat-value"><?php echo $ride['TotalBookings']; ?></span>
                                                    </p>
                                                    <p>
                                                        <strong>Revenue:</strong>
                                                        <span class="stat-value revenue">RM <?php echo number_format($ride['TotalEarnings'], 2); ?></span>
                                                    </p>
                                                    <p>
                                                        <strong>Seats Sold:</strong>
                                                        <span class="stat-value">
                                                            <?php
                                                            $total_seats = 4; // Assuming 4 seats per car
                                                            $seats_sold = $total_seats - $ride['AvailableSeats'];
                                                            echo $seats_sold . '/' . $total_seats;
                                                            ?>
                                                        </span>
                                                    </p>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="datetime-info">
                                                    <p class="date">
                                                        <i class="fa-solid fa-calendar"></i>
                                                        <?php echo date('M d, Y', strtotime($ride['RideDate'])); ?>
                                                    </p>
                                                    <p class="time">
                                                        <i class="fa-solid fa-clock"></i>
                                                        <?php echo date('h:i A', strtotime($ride['DepartureTime'])); ?>
                                                    </p>
                                                    <p class="time-status <?php echo $is_upcoming ? 'upcoming' : ($is_past ? 'past' : 'now'); ?>">
                                                        <i class="fa-solid fa-<?php echo $is_upcoming ? 'clock' : ($is_past ? 'history' : 'play'); ?>"></i>
                                                        <?php echo $is_upcoming ? 'Upcoming' : ($is_past ? 'Past' : 'Now'); ?>
                                                    </p>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="status-badge <?php echo $status_class; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $ride['Status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <!-- <button class="action-btn view-btn" data-id="<?php echo $ride['RideID']; ?>">
                                                        <i class="fa-solid fa-eye"></i> View
                                                    </button> -->

                                                    <?php if ($ride['Status'] === 'available' || $ride['Status'] === 'in_progress'): ?>
                                                        <button class="action-btn update-btn"
                                                            data-id="<?php echo $ride['RideID']; ?>"
                                                            data-current-status="<?php echo $ride['Status']; ?>">
                                                            <i class="fa-solid fa-sync-alt"></i> Update Status
                                                        </button>
                                                    <?php endif; ?>

                                                    <!-- <button class="action-btn bookings-btn" data-id="<?php echo $ride['RideID']; ?>">
                                                        <i class="fa-solid fa-ticket"></i> Bookings
                                                    </button> -->
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="no-data-cell">
                                            <div class="no-data">
                                                <i class="fa-solid fa-car-side"></i>
                                                <p>No rides found</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>

                        <!-- Pagination -->
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

                <!-- Status Update Modal -->
                <div id="statusModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Update Ride Status</h3>
                            <button class="close-modal">&times;</button>
                        </div>
                        <div class="modal-body">
                            <form id="statusForm" method="POST">
                                <input type="hidden" name="ride_id" id="modalRideId">
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

                                    <div class="status-options" id="statusOptions">
                                        <!-- Status options will be loaded here -->
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

    <script src="../js/admin-rides.js?v=<?php echo time(); ?>"></script>
</body>

</html>
<?php $conn->close(); ?>