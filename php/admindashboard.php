<?php
session_start();

// Ensure admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../php/login.php");
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

// Set timezone
date_default_timezone_set('Asia/Kuala_Lumpur');
$conn->query("SET time_zone = '+08:00'");

// Get total counts for dashboard
$total_users = $conn->query("SELECT COUNT(*) as count FROM user WHERE Role = 'user'")->fetch_assoc()['count'];
$total_drivers = $conn->query("SELECT COUNT(*) as count FROM driver")->fetch_assoc()['count'];
$total_rides = $conn->query("SELECT COUNT(*) as count FROM rides")->fetch_assoc()['count'];
$total_bookings = $conn->query("SELECT COUNT(*) as count FROM booking")->fetch_assoc()['count'];

// Get weekly rides (using RideDate from rides table)
$weekly_rides_sql = "SELECT 
    YEARWEEK(RideDate, 1) as week_num,
    COUNT(*) as ride_count
    FROM rides 
    WHERE RideDate >= DATE_SUB(CURDATE(), INTERVAL 8 WEEK)
    GROUP BY YEARWEEK(RideDate, 1)
    ORDER BY week_num DESC
    LIMIT 8";

$weekly_rides_result = $conn->query($weekly_rides_sql);
$weekly_rides_data = [];
$weekly_labels = [];
while ($row = $weekly_rides_result->fetch_assoc()) {
    $weekly_labels[] = 'Week ' . substr($row['week_num'], 4);
    $weekly_rides_data[] = $row['ride_count'];
}

// Get recent users (using UserID as timestamp proxy since no CreatedAt)
$recent_users_sql = "SELECT * FROM user WHERE Role = 'user' ORDER BY UserID DESC LIMIT 5";
$recent_users = $conn->query($recent_users_sql);

// Get recent bookings
$recent_bookings_sql = "SELECT 
    b.*, 
    u.FullName as PassengerName,
    r.FromLocation, 
    r.ToLocation
    FROM booking b
    JOIN user u ON b.UserID = u.UserID
    JOIN rides r ON b.RideID = r.RideID
    ORDER BY b.BookingDateTime DESC
    LIMIT 5";
$recent_bookings = $conn->query($recent_bookings_sql);

// Get gender distribution
$gender_sql = "SELECT Gender, COUNT(*) as count FROM user WHERE Role = 'user' GROUP BY Gender";
$gender_result = $conn->query($gender_sql);
$gender_data = [];
$gender_labels = [];
while ($row = $gender_result->fetch_assoc()) {
    $gender_labels[] = ucfirst($row['Gender']);
    $gender_data[] = $row['count'];
}

// Get ride status distribution
$ride_status_sql = "SELECT Status, COUNT(*) as count FROM rides GROUP BY Status";
$ride_status_result = $conn->query($ride_status_sql);
$ride_status_labels = [];
$ride_status_data = [];
while ($row = $ride_status_result->fetch_assoc()) {
    $ride_status_labels[] = $row['Status'];
    $ride_status_data[] = $row['count'];
}

// Get user registration trend (last 30 days) - Since no CreatedAt, use last 30 users
$registration_trend_sql = "SELECT 
    UserID,
    FullName,
    MatricNo
    FROM user 
    WHERE Role = 'user' 
    ORDER BY UserID DESC
    LIMIT 30";

$registration_result = $conn->query($registration_trend_sql);
$user_counts_by_day = [];

// Simulate registration trend (since no actual dates)
$today = date('Y-m-d');
for ($i = 0; $i < 30; $i++) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $user_counts_by_day[$date] = rand(0, 3); // Random data for demo
}

$reg_dates = array_keys($user_counts_by_day);
$reg_counts = array_values($user_counts_by_day);

// Get faculty distribution
$faculty_sql = "SELECT Faculty, COUNT(*) as count FROM user WHERE Role = 'user' AND Faculty != '' GROUP BY Faculty";
$faculty_result = $conn->query($faculty_sql);
$faculty_labels = [];
$faculty_data = [];
while ($row = $faculty_result->fetch_assoc()) {
    $faculty_labels[] = $row['Faculty'];
    $faculty_data[] = $row['count'];
}

// Get today's stats
$today_rides = $conn->query("SELECT COUNT(*) as count FROM rides WHERE RideDate = CURDATE()")->fetch_assoc()['count'];
$today_bookings = $conn->query("SELECT COUNT(*) as count FROM booking WHERE DATE(BookingDateTime) = CURDATE()")->fetch_assoc()['count'];

// Get booking status breakdown
$booking_status_sql = "SELECT BookingStatus, COUNT(*) as count FROM booking GROUP BY BookingStatus";
$booking_status_result = $conn->query($booking_status_sql);
$booking_status_data = [];
$booking_status_labels = [];
while ($row = $booking_status_result->fetch_assoc()) {
    $booking_status_labels[] = $row['BookingStatus'];
    $booking_status_data[] = $row['count'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - CampusCar</title>
    <link rel="stylesheet" href="../css/admindashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    <li class="nav-item active">
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
                        <span>CampusCar <span class="admin-badge">Admin</span></span>
                    </div>
                    <div class="header-info">
                        <div class="current-time">
                            <i class="fa-solid fa-clock"></i>
                            <span id="currentTime"></span>
                        </div>
                        <div class="today-stats">
                            <span class="stat-badge">
                                <i class="fa-solid fa-car"></i> <?php echo $today_rides; ?> rides today
                            </span>
                            <span class="stat-badge">
                                <i class="fa-solid fa-ticket"></i> <?php echo $today_bookings; ?> bookings today
                            </span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Dashboard Content -->
            <main class="dashboard-content">
                <!-- Quick Stats -->
                <section class="stats-section">
                    <h2><i class="fa-solid fa-chart-line"></i> System Overview</h2>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon users">
                                <i class="fa-solid fa-users"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Total Users</h3>
                                <span class="stat-number"><?php echo $total_users; ?></span>
                                <span class="stat-change">All registered passengers</span>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon drivers">
                                <i class="fa-solid fa-id-card"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Total Drivers</h3>
                                <span class="stat-number"><?php echo $total_drivers; ?></span>
                                <span class="stat-change">Verified drivers</span>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon rides">
                                <i class="fa-solid fa-car"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Total Rides</h3>
                                <span class="stat-number"><?php echo $total_rides; ?></span>
                                <span class="stat-change">All rides offered</span>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon bookings">
                                <i class="fa-solid fa-ticket"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Total Bookings</h3>
                                <span class="stat-number"><?php echo $total_bookings; ?></span>
                                <span class="stat-change">All ride bookings</span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Charts Section -->
                <section class="charts-section">
                    <div class="chart-row">
                        <!-- Weekly Rides Chart -->
                        <div class="chart-card">
                            <div class="chart-header">
                                <h3><i class="fa-solid fa-calendar-week"></i> Weekly Rides Trend</h3>
                                <p>Last 8 weeks</p>
                            </div>
                            <div class="chart-container">
                                <canvas id="weeklyRidesChart"></canvas>
                            </div>
                        </div>

                        <!-- Gender Distribution -->
                        <div class="chart-card">
                            <div class="chart-header">
                                <h3><i class="fa-solid fa-venus-mars"></i> Gender Distribution</h3>
                                <p>User demographics</p>
                            </div>
                            <div class="chart-container">
                                <canvas id="genderChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="chart-row">
                        <!-- Ride Status Distribution -->
                        <div class="chart-card">
                            <div class="chart-header">
                                <h3><i class="fa-solid fa-chart-pie"></i> Ride Status</h3>
                                <p>Current ride statuses</p>
                            </div>
                            <div class="chart-container">
                                <canvas id="rideStatusChart"></canvas>
                            </div>
                        </div>

                        <!-- Booking Status Distribution -->
                        <div class="chart-card">
                            <div class="chart-header">
                                <h3><i class="fa-solid fa-ticket"></i> Booking Status</h3>
                                <p>All booking statuses</p>
                            </div>
                            <div class="chart-container">
                                <canvas id="bookingStatusChart"></canvas>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Recent Activity -->
                <section class="recent-section">
                    <div class="recent-grid">
                        <!-- Recent Users -->
                        <div class="recent-card">
                            <div class="recent-header">
                                <h3><i class="fa-solid fa-user-plus"></i> Recent Users</h3>
                                <a href="admin-users.php" class="view-all">View All →</a>
                            </div>
                            <div class="recent-list">
                                <?php if ($recent_users->num_rows > 0): ?>
                                    <?php while ($user = $recent_users->fetch_assoc()): ?>
                                        <div class="recent-item">
                                            <div class="user-avatar-small">
                                                <i class="fa-solid fa-user-circle"></i>
                                            </div>
                                            <div class="user-info">
                                                <h4><?php echo htmlspecialchars($user['FullName']); ?></h4>
                                                <p><?php echo htmlspecialchars($user['MatricNo']); ?> | <?php echo htmlspecialchars($user['Gender']); ?></p>
                                            </div>
                                            <span class="user-faculty"><?php echo htmlspecialchars($user['Faculty']); ?></span>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="no-data">No recent users</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Recent Bookings -->
                        <div class="recent-card">
                            <div class="recent-header">
                                <h3><i class="fa-solid fa-clock-rotate-left"></i> Recent Bookings</h3>
                                <a href="admin-bookings.php" class="view-all">View All →</a>
                            </div>
                            <div class="recent-list">
                                <?php if ($recent_bookings->num_rows > 0): ?>
                                    <?php while ($booking = $recent_bookings->fetch_assoc()): ?>
                                        <div class="recent-item booking-item">
                                            <div class="booking-icon">
                                                <i class="fa-solid fa-ticket"></i>
                                            </div>
                                            <div class="booking-info">
                                                <h4><?php echo htmlspecialchars($booking['PassengerName']); ?></h4>
                                                <p><?php echo htmlspecialchars($booking['FromLocation']); ?> → <?php echo htmlspecialchars($booking['ToLocation']); ?></p>
                                                <small><?php echo date('M d, H:i', strtotime($booking['BookingDateTime'])); ?></small>
                                            </div>
                                            <span class="booking-status <?php echo strtolower($booking['BookingStatus']); ?>">
                                                <?php echo $booking['BookingStatus']; ?>
                                            </span>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="no-data">No recent bookings</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Quick Stats Summary -->
                <section class="summary-section">
                    <div class="summary-grid">
                        <div class="summary-card">
                            <h3><i class="fa-solid fa-graduation-cap"></i> Faculty Distribution</h3>
                            <div class="faculty-list">
                                <?php if (!empty($faculty_labels)): ?>
                                    <?php foreach ($faculty_labels as $index => $faculty): ?>
                                        <div class="faculty-item">
                                            <span class="faculty-name"><?php echo $faculty; ?></span>
                                            <span class="faculty-count"><?php echo $faculty_data[$index]; ?> users</span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="no-data">No faculty data available</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="summary-card">
                            <h3><i class="fa-solid fa-car"></i> Top Routes</h3>
                            <?php
                            // Get popular routes
                            $popular_routes_sql = "SELECT FromLocation, ToLocation, COUNT(*) as count 
                                                   FROM rides 
                                                   GROUP BY FromLocation, ToLocation 
                                                   ORDER BY count DESC 
                                                   LIMIT 5";
                            $popular_routes = $conn->query($popular_routes_sql);
                            ?>
                            <div class="routes-list">
                                <?php if ($popular_routes->num_rows > 0): ?>
                                    <?php while ($route = $popular_routes->fetch_assoc()): ?>
                                        <div class="route-item">
                                            <div class="route-info">
                                                <i class="fa-solid fa-route"></i>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($route['FromLocation']); ?></strong>
                                                    <span>→</span>
                                                    <strong><?php echo htmlspecialchars($route['ToLocation']); ?></strong>
                                                </div>
                                            </div>
                                            <span class="route-count"><?php echo $route['count']; ?> rides</span>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="no-data">No route data available</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <script src="../js/admindashboard.js?v=<?php echo time(); ?>"></script>
    <script>
        // Weekly Rides Chart
        const weeklyCtx = document.getElementById('weeklyRidesChart').getContext('2d');
        const weeklyRidesChart = new Chart(weeklyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_reverse($weekly_labels)); ?>,
                datasets: [{
                    label: 'Number of Rides',
                    data: <?php echo json_encode(array_reverse($weekly_rides_data)); ?>,
                    borderColor: '#7c9bc9',
                    backgroundColor: 'rgba(124, 155, 201, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                }
            }
        });

        // Gender Distribution Chart
        const genderCtx = document.getElementById('genderChart').getContext('2d');
        const genderChart = new Chart(genderCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($gender_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($gender_data); ?>,
                    backgroundColor: [
                        '#7c9bc9', // Male
                        '#e75480', // Female
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });

        // Ride Status Chart
        const rideStatusCtx = document.getElementById('rideStatusChart').getContext('2d');
        const rideStatusChart = new Chart(rideStatusCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($ride_status_labels); ?>,
                datasets: [{
                    label: 'Number of Rides',
                    data: <?php echo json_encode($ride_status_data); ?>,
                    backgroundColor: [
                        '#a8d5ba', // available
                        '#7c9bc9', // completed
                        '#ffb347', // expired
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Booking Status Chart
        const bookingStatusCtx = document.getElementById('bookingStatusChart').getContext('2d');
        const bookingStatusChart = new Chart(bookingStatusCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($booking_status_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($booking_status_data); ?>,
                    backgroundColor: [
                        '#7c9bc9', // Pending
                        '#a8d5ba', // Confirmed
                        '#28a745', // Completed
                        '#ffb347', // Cancelled
                        '#9d4edd', // Paid
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right',
                    }
                }
            }
        });
    </script>
</body>

</html>
<?php $conn->close(); ?>