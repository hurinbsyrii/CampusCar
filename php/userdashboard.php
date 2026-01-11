<?php
session_start();

// ensure PHP uses Malaysia timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
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

// ensure MySQL uses Malaysia timezone for this connection
$conn->query("SET time_zone = '+08:00'");

// Check if user is already a driver
$user_id = $_SESSION['user_id'];
$is_driver = false;
$driver_id = null;
$user_gender = '';
$driver_status = '';

// Get user gender
$user_sql = "SELECT Gender FROM user WHERE UserID = ?";
$stmt = $conn->prepare($user_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
if ($user_result->num_rows > 0) {
    $user_data = $user_result->fetch_assoc();
    $user_gender = $user_data['Gender'];
    $_SESSION['user_gender'] = $user_gender;
}
$stmt->close();

// Check Driver Status & Pending Requests (Logic Asal)
$driver_check_sql = "SELECT DriverID, Status, RejectionReason FROM driver WHERE UserID = ?";
$stmt = $conn->prepare($driver_check_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$driver_pending_count = 0; // Initialize variable

if ($result->num_rows > 0) {
    $is_driver = true;
    $driver_data = $result->fetch_assoc();
    $driver_id = $driver_data['DriverID'];
    $driver_status = $driver_data['Status'];
    $rejection_reason = $driver_data['RejectionReason'] ?? 'Contact admin for details.';

    // Count Pending Requests for this driver
    if ($driver_status === 'approved') {
        $pending_sql = "SELECT COUNT(*) as count FROM booking b 
                        JOIN rides r ON b.RideID = r.RideID 
                        WHERE r.DriverID = ? AND b.BookingStatus = 'Pending'";
        $p_stmt = $conn->prepare($pending_sql);
        $p_stmt->bind_param("i", $driver_id);
        $p_stmt->execute();
        $p_result = $p_stmt->get_result();
        $driver_pending_count = $p_result->fetch_assoc()['count'];
        $p_stmt->close();
    }
}
$stmt->close();

// --- TAMBAHAN BARU: Kira status passenger yang berubah (IsSeenByPassenger = 0) ---
$passenger_update_count = 0;
$passenger_updates_sql = "SELECT COUNT(*) as update_count 
                          FROM booking 
                          WHERE UserID = ? AND IsSeenByPassenger = 0";
$stmt = $conn->prepare($passenger_updates_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$update_result = $stmt->get_result();
$passenger_update_count = $update_result->fetch_assoc()['update_count'];
$stmt->close();
// --------------------------------------------------------------------------------

// Get user's booking count for stats
$booking_count_sql = "SELECT COUNT(*) as booking_count FROM booking WHERE UserID = ?";
$stmt = $conn->prepare($booking_count_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$booking_result = $stmt->get_result();
$booking_count = $booking_result->fetch_assoc()['booking_count'];
$stmt->close();

// Get search parameters
$from_location = isset($_GET['from_location']) ? $_GET['from_location'] : '';
$to_location = isset($_GET['to_location']) ? $_GET['to_location'] : '';
$ride_date = isset($_GET['ride_date']) ? $_GET['ride_date'] : '';
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : '';

// Build dynamic SQL query based on filters
$rides_sql = "SELECT r.*, u.FullName AS DriverName, u.PhoneNumber, d.UserID as DriverUserID
              FROM rides r
              LEFT JOIN driver d ON r.DriverID = d.DriverID
              LEFT JOIN user u ON d.UserID = u.UserID
              WHERE r.Status = 'available'
                AND r.AvailableSeats > 0
                AND (r.RideDate > CURDATE()
                     OR (r.RideDate = CURDATE() AND r.DepartureTime > CURTIME()))";

// Apply filters if provided
$params = [];
$param_types = "";

if (!empty($from_location)) {
    $rides_sql .= " AND r.FromLocation LIKE ?";
    $params[] = "%" . $from_location . "%";
    $param_types .= "s";
}

if (!empty($to_location)) {
    $rides_sql .= " AND r.ToLocation LIKE ?";
    $params[] = "%" . $to_location . "%";
    $param_types .= "s";
}

if (!empty($ride_date)) {
    $rides_sql .= " AND r.RideDate = ?";
    $params[] = $ride_date;
    $param_types .= "s";
} elseif (!empty($filter_type)) {
    switch ($filter_type) {
        case 'today':
            $rides_sql .= " AND r.RideDate = CURDATE()";
            break;
        case 'tomorrow':
            $rides_sql .= " AND r.RideDate = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'week':
            $rides_sql .= " AND YEARWEEK(r.RideDate, 1) = YEARWEEK(CURDATE(), 1)";
            break;
    }
}

$rides_sql .= " ORDER BY r.RideDate, r.DepartureTime";

if (count($params) > 0) {
    $stmt = $conn->prepare($rides_sql);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $rides_result = $stmt->get_result();
} else {
    $rides_result = $conn->query($rides_sql);
}

// Get total count for stats (without filters)
$total_rides_sql = "SELECT COUNT(*) as total FROM rides 
                    WHERE Status = 'available' 
                    AND AvailableSeats > 0
                    AND (RideDate > CURDATE() 
                         OR (RideDate = CURDATE() AND DepartureTime > CURTIME()))";
$total_result = $conn->query($total_rides_sql);
$total_rides = $total_result->fetch_assoc()['total'];

$monday = date('Y-m-d', strtotime('monday this week'));
$sunday = date('Y-m-d', strtotime('sunday this week'));
$week_range = date('M j', strtotime($monday)) . ' - ' . date('M j, Y', strtotime($sunday));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CampusCar</title>
    <link rel="stylesheet" href="../css/userdashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/profile.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Girls Only Indicator */
        .girls-only-indicator {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #e75480;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 8px rgba(231, 84, 128, 0.3);
            z-index: 1;
        }

        .girls-only-indicator i {
            font-size: 0.9rem;
        }

        .ride-card {
            position: relative;
        }

        /* Active filter indicator */
        .filter-active {
            background: var(--primary-color) !important;
            color: white !important;
            border-color: var(--primary-color) !important;
        }

        .current-filter-info {
            background: #e8f4fd;
            border-left: 4px solid var(--primary-color);
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.95rem;
            color: #2d3748;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .current-filter-info i {
            color: var(--primary-color);
        }
    </style>
</head>

<body>
    <div class="dashboard-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="user-avatar">
                    <i class="fa-solid fa-user-circle"></i>
                </div>
                <div class="user-details">
                    <h3><?php echo $_SESSION['full_name'] ?? 'User'; ?></h3>

                    <span class="user-role">
                        <?php
                        if ($is_driver && $driver_status === 'approved') {
                            echo 'Driver';
                        } else {
                            echo 'Passenger';
                        }
                        ?>
                    </span>

                    <span class="user-gender">(<?php echo ucfirst($user_gender); ?>)</span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <ul>
                    <li class="nav-item active" data-section="findride" data-count="<?php echo $rides_result->num_rows; ?>">
                        <a href="userdashboard.php" class="nav-link">
                            <i class="fa-solid fa-gauge"></i>
                            <span>Find Ride</span>
                        </a>
                    </li>

                    <li class="nav-item" data-section="profile" data-count="0">
                        <a href="userprofile.php" class="nav-link">
                            <i class="fa-solid fa-user"></i>
                            <span>My Profile</span>
                        </a>
                    </li>

                    <?php if ($is_driver && $driver_status === 'approved'): ?>
                        <li class="nav-item" data-section="driver" data-count="0">
                            <a href="driverdashboard.php" class="nav-link">
                                <i class="fa-solid fa-car-side"></i>
                                <span>Driver Dashboard</span>
                            </a>
                        </li>
                        <li class="nav-item" data-section="offer" data-count="0">
                            <a href="rideoffer.php" class="nav-link">
                                <i class="fa-solid fa-plus"></i>
                                <span>Offer Ride</span>
                            </a>
                        </li>

                    <?php elseif (!$is_driver): ?>
                        <li class="nav-item" data-section="become" data-count="0">
                            <a href="driverregistration.php" class="nav-link">
                                <i class="fa-solid fa-user-plus"></i>
                                <span>Become Driver</span>
                            </a>
                        </li>
                    <?php endif; ?>

                    <li class="nav-item" data-section="bookings" data-count="<?php echo $booking_count; ?>">
                        <a href="mybookings.php" class="nav-link">
                            <div class="nav-link-content">
                                <i class="fa-solid fa-ticket"></i>
                                <span>My Bookings</span>
                            </div>

                            <?php
                            // LOGIC DOT GABUNGAN: Driver Request + Passenger Updates
                            $total_notifications = 0;

                            // Jika driver, tambah pending request
                            if ($is_driver && $driver_status === 'approved') {
                                $total_notifications += $driver_pending_count;
                            }

                            // Tambah update status untuk passenger
                            $total_notifications += $passenger_update_count;
                            ?>

                            <?php if ($total_notifications > 0): ?>
                                <span class="notification-dot" title="<?php echo $total_notifications; ?> updates"></span>
                            <?php endif; ?>
                        </a>
                    </li>

                    <li class="nav-item" data-section="todays" data-count="0">
                        <a href="todaysride.php" class="nav-link">
                            <i class="fa-solid fa-calendar-day"></i>
                            <span>Today's Rides</span>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="logout.php" class="nav-link logout">
                            <i class="fa-solid fa-right-from-bracket"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <div class="main-content">
            <header class="dashboard-header">
                <div class="header-content">
                    <button id="sidebarToggle" class="sidebar-toggle" aria-label="Toggle sidebar" title="Toggle sidebar">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <div class="logo">
                        <i class="fa-solid fa-car-side"></i>
                        <span>CampusCar</span>
                    </div>
                    <div class="header-actions">
                        <div class="user-welcome">
                            <i class="fa-solid fa-user-circle"></i>
                            <span>Welcome, <?php echo $_SESSION['full_name'] ?? 'User'; ?></span>
                            <span class="user-gender-badge">(<?php echo ucfirst($user_gender); ?>)</span>
                        </div>
                        <a href="userprofile.php" class="profile-btn">
                            <i class="fa-solid fa-user"></i>
                            My Profile
                        </a>
                    </div>
                </div>
            </header>

            <main class="dashboard-main">
                <section class="status-section">
                    <div class="status-card <?php
                                            if (!$is_driver) echo 'driver-inactive';
                                            elseif ($driver_status === 'approved') echo 'driver-active';
                                            elseif ($driver_status === 'rejected') echo 'driver-rejected';
                                            else echo 'driver-pending';
                                            ?>">
                        <div class="status-icon">
                            <i class="fa-solid <?php
                                                if (!$is_driver) echo 'fa-user-plus';
                                                elseif ($driver_status === 'approved') echo 'fa-id-card';
                                                elseif ($driver_status === 'rejected') echo 'fa-ban';
                                                else echo 'fa-clock';
                                                ?>"></i>
                        </div>

                        <div class="status-content">
                            <?php if (!$is_driver): ?>
                                <h3>Become a Driver</h3>
                                <p>Register as a driver to start offering rides</p>
                            <?php elseif ($driver_status === 'pending'): ?>
                                <h3>Verification Pending</h3>
                                <p>Your application is being reviewed by admin.</p>
                            <?php elseif ($driver_status === 'rejected'): ?>
                                <h3>Application Rejected</h3>
                                <p>Your driver application was not approved.</p>
                            <?php else: ?>
                                <h3>Registered Driver</h3>
                                <p>You can now offer rides to other students</p>
                            <?php endif; ?>
                        </div>

                        <?php if (!$is_driver): ?>
                            <button class="status-btn btn-primary" onclick="registerAsDriver()">
                                <i class="fa-solid fa-user-plus"></i> Register as Driver
                            </button>
                        <?php elseif ($driver_status === 'pending'): ?>
                            <button class="status-btn btn-warning" onclick="showPendingAlert()">
                                <i class="fa-solid fa-clock"></i> Status: Pending
                            </button>
                        <?php elseif ($driver_status === 'rejected'): ?>
                            <button class="status-btn btn-danger" onclick="showRejectedAlert('<?php echo htmlspecialchars(addslashes($rejection_reason)); ?>')">
                                <i class="fa-solid fa-circle-exclamation"></i> View Reason
                            </button>
                        <?php else: ?>
                            <button class="status-btn btn-success" onclick="offerRide()">
                                <i class="fa-solid fa-plus"></i> Offer Ride
                            </button>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="stats-section">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fa-solid fa-car"></i>
                            </div>
                            <div class="stat-info">
                                <h4>Available Rides</h4>
                                <span class="stat-number">
                                    <?php echo $rides_result->num_rows; ?>
                                    <?php if (!empty($from_location) || !empty($to_location) || !empty($ride_date) || !empty($filter_type)): ?>
                                        <small class="filtered-count">/ <?php echo $total_rides; ?> total</small>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fa-solid fa-ticket"></i>
                            </div>
                            <div class="stat-info">
                                <h4>My Bookings</h4>
                                <span class="stat-number"><?php echo $booking_count; ?></span>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fa-solid fa-users"></i>
                            </div>
                            <div class="stat-info">
                                <h4>Active Drivers</h4>
                                <span class="stat-number">50+</span>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="search-section">
                    <div class="section-header">
                        <h2><i class="fa-solid fa-search"></i> Find Rides</h2>
                        <p>Search for rides by location or filter by date</p>
                    </div>

                    <div class="search-container">
                        <form method="GET" action="" class="search-form">
                            <input type="hidden" name="filter_type" id="filter_type" value="<?php echo htmlspecialchars($filter_type); ?>">

                            <div class="search-input-group">
                                <div class="input-with-icon">
                                    <i class="fa-solid fa-location-dot"></i>
                                    <input type="text"
                                        name="from_location"
                                        placeholder="From location..."
                                        value="<?php echo htmlspecialchars($from_location); ?>"
                                        class="search-input">
                                </div>

                                <div class="input-with-icon">
                                    <i class="fa-solid fa-location-crosshairs"></i>
                                    <input type="text"
                                        name="to_location"
                                        placeholder="To location..."
                                        value="<?php echo htmlspecialchars($to_location); ?>"
                                        class="search-input">
                                </div>

                                <div class="input-with-icon">
                                    <i class="fa-solid fa-calendar-day"></i>
                                    <input type="date"
                                        name="ride_date"
                                        value="<?php echo htmlspecialchars($ride_date); ?>"
                                        class="search-input date-input"
                                        min="<?php echo date('Y-m-d'); ?>">
                                </div>

                                <button type="submit" class="search-btn">
                                    <i class="fa-solid fa-search"></i>
                                    Search
                                </button>

                                <?php if (!empty($from_location) || !empty($to_location) || !empty($ride_date) || !empty($filter_type)): ?>
                                    <a href="userdashboard.php" class="clear-btn">
                                        <i class="fa-solid fa-times"></i>
                                        Clear Filters
                                    </a>
                                <?php endif; ?>
                            </div>

                            <div class="quick-filters">
                                <span class="filter-label">Quick Filter:</span>
                                <button type="button"
                                    class="quick-filter-btn <?php echo ($filter_type == 'today') ? 'filter-active' : ''; ?>"
                                    onclick="filterByDate('today')">
                                    <i class="fa-solid fa-sun"></i> Today
                                </button>
                                <button type="button"
                                    class="quick-filter-btn <?php echo ($filter_type == 'tomorrow') ? 'filter-active' : ''; ?>"
                                    onclick="filterByDate('tomorrow')">
                                    <i class="fa-solid fa-calendar-plus"></i> Tomorrow
                                </button>
                                <button type="button"
                                    class="quick-filter-btn <?php echo ($filter_type == 'week') ? 'filter-active' : ''; ?>"
                                    onclick="filterByDate('week')">
                                    <i class="fa-solid fa-calendar-week"></i> This Week
                                </button>
                                <button type="button" class="quick-filter-btn" onclick="clearFilters()">
                                    <i class="fa-solid fa-filter-circle-xmark"></i> Show All
                                </button>
                            </div>
                        </form>

                        <?php if (!empty($filter_type)): ?>
                            <div class="current-filter-info">
                                <i class="fa-solid fa-info-circle"></i>
                                <?php if ($filter_type == 'today'): ?>
                                    Showing rides for today (<?php echo date('M j, Y'); ?>)
                                <?php elseif ($filter_type == 'tomorrow'): ?>
                                    Showing rides for tomorrow (<?php echo date('M j, Y', strtotime('+1 day')); ?>)
                                <?php elseif ($filter_type == 'week'): ?>
                                    Showing rides for this week (<?php echo $week_range; ?>)
                                <?php endif; ?>
                            </div>
                        <?php elseif (!empty($ride_date)): ?>
                            <div class="current-filter-info">
                                <i class="fa-solid fa-calendar"></i>
                                Showing rides for <?php echo date('M j, Y', strtotime($ride_date)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="rides-section">
                    <div class="section-header">
                        <h2><i class="fa-solid fa-list"></i> Available Rides</h2>
                        <p>
                            <?php if (!empty($from_location) || !empty($to_location) || !empty($ride_date) || !empty($filter_type)): ?>
                                Showing <?php echo $rides_result->num_rows; ?> filtered rides
                                <?php if (!empty($filter_type) && $filter_type == 'week'): ?>
                                    for this week
                                <?php endif; ?>
                            <?php else: ?>
                                Find your perfect ride across campus
                            <?php endif; ?>
                        </p>
                    </div>

                    <?php if ($rides_result->num_rows > 0): ?>
                        <div class="rides-grid">
                            <?php while ($ride = $rides_result->fetch_assoc()):
                                $is_own_ride = ($is_driver && $ride['DriverUserID'] == $user_id);
                                $is_girls_only = isset($ride['FemaleOnly']) && $ride['FemaleOnly'] == 1;
                            ?>
                                <div class="ride-card">
                                    <div class="ride-header">
                                        <div class="route-info">
                                            <h3><?php echo htmlspecialchars($ride['FromLocation']); ?> <i class="fa-solid fa-arrow-right"></i> <?php echo htmlspecialchars($ride['ToLocation']); ?></h3>
                                            <?php if ($is_girls_only): ?>
                                                <span class="girls-only-badge"><i class="fa-solid fa-venus"></i> Girls Only</span>
                                            <?php endif; ?>
                                            <span class="ride-date">
                                                <i class="fa-solid fa-calendar"></i>
                                                <?php echo date('M j, Y', strtotime($ride['RideDate'])); ?>
                                            </span>
                                        </div>
                                        <div class="price-tag">
                                            <span class="price">RM<?php echo $ride['PricePerSeat']; ?></span>
                                            <small>per pax</small>
                                        </div>
                                    </div>

                                    <div class="ride-details">
                                        <div class="detail-item">
                                            <i class="fa-solid fa-clock"></i>
                                            <span><?php echo date('g:i A', strtotime($ride['DepartureTime'])); ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <i class="fa-solid fa-user-friends"></i>
                                            <span><?php echo $ride['AvailableSeats']; ?> pax left</span>
                                        </div>
                                        <div class="detail-item">
                                            <i class="fa-solid fa-user"></i>
                                            <span><?php echo !empty($ride['DriverName']) ? htmlspecialchars($ride['DriverName']) : 'Driver not available'; ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <i class="fa-solid fa-phone"></i>
                                            <span><?php echo !empty($ride['PhoneNumber']) ? htmlspecialchars($ride['PhoneNumber']) : 'N/A'; ?></span>
                                        </div>
                                    </div>

                                    <?php if (!empty($ride['RideDescription'])): ?>
                                        <div class="ride-description">
                                            <p><?php echo htmlspecialchars($ride['RideDescription']); ?></p>
                                        </div>
                                    <?php endif; ?>

                                    <div class="ride-actions">
                                        <?php if ($is_own_ride): ?>
                                            <button class="btn-book disabled" onclick="showOwnRideError()">
                                                <i class="fa-solid fa-ban"></i>
                                                Your Own Ride
                                            </button>
                                        <?php elseif ($is_girls_only && $user_gender !== 'female'): ?>
                                            <button class="btn-book disabled" onclick="showGirlsOnlyError()">
                                                <i class="fa-solid fa-venus"></i>
                                                Girls Only Ride
                                            </button>
                                        <?php else: ?>
                                            <button class="btn-book" onclick="bookRide(<?php echo $ride['RideID']; ?>)">
                                                <i class="fa-solid fa-ticket"></i>
                                                Book This Ride
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-rides">
                            <div class="no-rides-icon">
                                <i class="fa-solid fa-car-side"></i>
                            </div>
                            <h3>No Rides Found</h3>
                            <p>
                                <?php if (!empty($from_location) || !empty($to_location) || !empty($ride_date) || !empty($filter_type)): ?>
                                    No rides match your search criteria. Try different filters or clear filters to see all available rides.
                                <?php else: ?>
                                    Check back later for new ride offers or become a driver to offer rides yourself!
                                <?php endif; ?>
                            </p>
                            <?php if (!$is_driver): ?>
                                <button class="btn-primary" onclick="registerAsDriver()">
                                    <i class="fa-solid fa-user-plus"></i>
                                    Become a Driver
                                </button>
                            <?php endif; ?>
                            <?php if (!empty($from_location) || !empty($to_location) || !empty($ride_date) || !empty($filter_type)): ?>
                                <a href="userdashboard.php" class="clear-btn" style="margin-top: 15px;">
                                    <i class="fa-solid fa-times"></i>
                                    Clear Filters
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </main>
        </div>
    </div>

    <script src="../js/userdashboard.js?v=<?php echo time(); ?>"></script>
    <script src="../js/profile.js?v=<?php echo time(); ?>"></script>
</body>

</html>
<?php $conn->close(); ?>