<?php
session_start();

// Ensure PHP uses Malaysia timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

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

// Ensure MySQL connection uses Malaysia timezone (+08:00)
$conn->query("SET time_zone = '+08:00'");

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$current_time = date('H:i:s'); // Current time for time checking

// --- AUTO-EXPIRE RIDES LOGIC ---
$currentDateTimeFull = date('Y-m-d H:i:s');

$expire_sql = "UPDATE rides 
               SET Status = 'expired' 
               WHERE TIMESTAMP(RideDate, DepartureTime) < '$currentDateTimeFull' 
               AND Status = 'available'
               AND RideID NOT IN (
                   SELECT RideID FROM booking 
                   WHERE BookingStatus IN ('Confirmed', 'Paid', 'Completed')
               )";

$conn->query($expire_sql);
// ------------------------------------

// Check if user is a driver
$is_driver = false;
$driver = null;
$driver_pending_count = 0; // Initialize count

// KEMASKINI: Ambil Status sekali
$driver_check_sql = "SELECT DriverID, Status FROM driver WHERE UserID = ?";
$stmt = $conn->prepare($driver_check_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $is_driver = true;
    $driver = $result->fetch_assoc();

    // Only count pending requests if approved
    if ($driver['Status'] === 'approved') {
        $pending_sql = "SELECT COUNT(*) as count FROM booking b 
                        JOIN rides r ON b.RideID = r.RideID 
                        WHERE r.DriverID = ? AND b.BookingStatus = 'Pending'";
        $p_stmt = $conn->prepare($pending_sql);
        $p_stmt->bind_param("i", $driver['DriverID']);
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

// Get today's rides where user is involved - EXCLUDE CANCELLED BOOKINGS
$today_rides_sql = "SELECT r.*, u.FullName as DriverName, d.CarModel, d.CarPlateNumber,
                           b.BookingID, b.BookingStatus, b.NoOfSeats, b.TotalPrice,
                           b.UserID as PassengerID,
                           (SELECT FullName FROM user WHERE UserID = b.UserID) as PassengerName
                    FROM rides r
                    JOIN driver d ON r.DriverID = d.DriverID
                    JOIN user u ON d.UserID = u.UserID
                    LEFT JOIN booking b ON r.RideID = b.RideID AND b.UserID = ? 
                        AND b.BookingStatus != 'Cancelled'  -- EXCLUDE CANCELLED BOOKINGS
                    WHERE r.RideDate = ? AND r.Status != 'Cancelled'
                    AND (
                        r.DriverID = ? 
                        OR (b.UserID = ? AND b.BookingStatus != 'Cancelled')
                    )
                    ORDER BY FIELD(r.Status, 'in_progress', 'available', 'completed'), 
                             r.DepartureTime";

$stmt = $conn->prepare($today_rides_sql);
// Only use driver_id in query if approved
$driver_id_param = ($is_driver && $driver['Status'] === 'approved') ? $driver['DriverID'] : 0;

$stmt->bind_param("isii", $user_id, $today, $driver_id_param, $user_id);
$stmt->execute();
$today_rides = $stmt->get_result();

// Group rides by RideID to avoid duplicates
$rides_by_id = [];
while ($ride = $today_rides->fetch_assoc()) {
    $ride_id = $ride['RideID'];
    if (!isset($rides_by_id[$ride_id])) {
        $rides_by_id[$ride_id] = $ride;
    }
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Today's Ride - CampusCar</title>
    <link rel="stylesheet" href="../css/userdashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/todaysride.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
                    <span class="user-role"><?php echo ($is_driver && $driver['Status'] === 'approved') ? 'Driver' : 'Passenger'; ?></span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <ul>
                    <li class="nav-item">
                        <a href="userdashboard.php" class="nav-link" data-section="findride">
                            <i class="fa-solid fa-gauge"></i>
                            <span>Find Ride</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="userprofile.php" class="nav-link" data-section="profile">
                            <i class="fa-solid fa-user"></i>
                            <span>My Profile</span>
                        </a>
                    </li>

                    <?php if ($is_driver && $driver['Status'] === 'approved'): ?>
                        <li class="nav-item">
                            <a href="driverdashboard.php" class="nav-link" data-section="driver">
                                <i class="fa-solid fa-car-side"></i>
                                <span>Driver Dashboard</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="rideoffer.php" class="nav-link">
                                <i class="fa-solid fa-plus"></i>
                                <span>Offer Ride</span>
                            </a>
                        </li>
                    <?php elseif (!$is_driver): ?>
                        <li class="nav-item">
                            <a href="driverregistration.php" class="nav-link">
                                <i class="fa-solid fa-user-plus"></i>
                                <span>Become Driver</span>
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a href="mybookings.php" class="nav-link" data-section="bookings">
                            <div class="nav-link-content">
                                <i class="fa-solid fa-ticket"></i>
                                <span>My Bookings</span>
                            </div>

                            <?php
                            // LOGIC DOT GABUNGAN: Driver Request + Passenger Updates
                            $total_notifications = 0;

                            // Jika driver approved, tambah pending request
                            if ($is_driver && isset($driver['Status']) && $driver['Status'] === 'approved') {
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
                    <li class="nav-item active">
                        <a href="todaysride.php" class="nav-link" data-section="todays" data-count="<?php echo count($rides_by_id); ?>">
                            <i class="fa-solid fa-calendar-day"></i>
                            <span>Today's Ride</span>
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
                        </div>
                        <a href="userprofile.php" class="profile-btn">
                            <i class="fa-solid fa-user"></i>
                            My Profile
                        </a>
                    </div>
                </div>
            </header>

            <main class="dashboard-main">
                <div class="todays-rides-container">
                    <div class="page-header">
                        <h1><i class="fa-solid fa-calendar-day"></i> Today's Rides</h1>
                        <p>Manage your rides and bookings for today - <?php echo date('F j, Y'); ?></p>
                        <p class="current-time"><i class="fa-solid fa-clock"></i> Current time: <span id="currentTime"><?php echo date('g:i A'); ?></span></p>

                        <div class="status-legend">
                            <div class="legend-item">
                                <span class="legend-dot status-available"></span>
                                <span>Available</span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-dot status-in-progress"></span>
                                <span>In Progress</span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-dot status-completed"></span>
                                <span>Completed</span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-dot status-not-yet-started"></span>
                                <span>Not Yet Started</span>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($rides_by_id)): ?>
                        <div class="rides-grid">
                            <?php foreach ($rides_by_id as $ride):
                                // KEMASKINI: Hanya anggap user sebagai driver card ini jika status approved
                                $is_user_driver = $is_driver && isset($driver['DriverID']) &&
                                    $driver['Status'] === 'approved' &&
                                    $driver['DriverID'] == $ride['DriverID'];

                                // Check if current time is after departure time
                                $departure_time = $ride['DepartureTime'];
                                $can_start_ride = ($current_time >= $departure_time);

                                // Determine status badge
                                $status_class = '';
                                $status_text = '';
                                $status_icon = 'fa-circle';

                                if ($ride['Status'] == 'expired') {
                                    $status_class = 'status-expired';
                                    $status_text = 'Expired';
                                    $status_icon = 'fa-times-circle';
                                } elseif ($ride['Status'] == 'in_progress') {
                                    $status_class = 'status-in-progress';
                                    $status_text = 'In Progress';
                                } elseif ($ride['Status'] == 'completed') {
                                    $status_class = 'status-completed';
                                    $status_text = 'Completed';
                                } else {
                                    $status_class = $can_start_ride ? 'status-available' : 'status-not-yet-started';
                                    $status_text = $can_start_ride ? 'Available' : 'Not Yet Started';
                                }
                            ?>
                                <div class="ride-card" data-ride-id="<?php echo $ride['RideID']; ?>" data-status="<?php echo $ride['Status']; ?>" data-departure-time="<?php echo $departure_time; ?>" data-booking-id="<?php echo htmlspecialchars($ride['BookingID'] ?? ''); ?>">
                                    <div class="ride-header">
                                        <div class="route-info">
                                            <h3>
                                                <i class="fa-solid fa-route"></i>
                                                <?php echo htmlspecialchars($ride['FromLocation']); ?>
                                                <i class="fa-solid fa-arrow-right"></i>
                                                <?php echo htmlspecialchars($ride['ToLocation']); ?>
                                            </h3>
                                            <div class="ride-meta">
                                                <span class="ride-time">
                                                    <i class="fa-solid fa-clock"></i>
                                                    <?php echo date('g:i A', strtotime($ride['DepartureTime'])); ?>
                                                    <?php if (!$can_start_ride && $ride['Status'] == 'available'): ?>
                                                        <span class="time-info">(Starts in <?php echo timeUntil($departure_time, $current_time); ?>)</span>
                                                    <?php endif; ?>
                                                </span>
                                                <span class="car-info">
                                                    <i class="fa-solid fa-car"></i>
                                                    <?php echo htmlspecialchars($ride['CarModel']); ?> (<?php echo htmlspecialchars($ride['CarPlateNumber']); ?>)
                                                </span>
                                            </div>
                                        </div>
                                        <div class="ride-status">
                                            <span class="status-badge <?php echo $status_class; ?>">
                                                <i class="fa-solid <?php echo $status_icon; ?>"></i>
                                                <?php echo $status_text; ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="ride-details">
                                        <div class="detail-section">
                                            <h4><i class="fa-solid fa-user-tie"></i> Driver</h4>
                                            <p><?php echo htmlspecialchars($ride['DriverName']); ?></p>
                                        </div>
                                        <div class="detail-section">
                                            <h4><i class="fa-solid fa-chair"></i> Available Seats</h4>
                                            <p><?php echo $ride['AvailableSeats']; ?> seats remaining</p>
                                        </div>
                                        <div class="detail-section">
                                            <h4><i class="fa-solid fa-tag"></i> Price</h4>
                                            <p class="price">RM<?php echo $ride['PricePerSeat']; ?> per seat</p>
                                        </div>
                                    </div>

                                    <?php if ($ride['RideDescription']): ?>
                                        <div class="ride-description">
                                            <p><i class="fa-solid fa-info-circle"></i> <?php echo htmlspecialchars($ride['RideDescription']); ?></p>
                                        </div>
                                    <?php endif; ?>

                                    <div class="ride-actions">
                                        <?php if ($is_user_driver): ?>
                                            <div class="driver-actions">
                                                <?php if ($ride['Status'] == 'expired'): ?>
                                                    <button class="btn btn-end-ride disabled" disabled style="background-color: #dc3545; opacity: 0.7;">
                                                        <i class="fa-solid fa-ban"></i> Ride Expired
                                                    </button>

                                                <?php elseif ($ride['Status'] == 'available'): ?>
                                                    <?php if ($can_start_ride): ?>
                                                        <button class="btn btn-start-ride" onclick="startRide(<?php echo $ride['RideID']; ?>)">
                                                            <i class="fa-solid fa-play"></i>
                                                            Start Ride
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-start-ride disabled" disabled title="Ride starts at <?php echo date('g:i A', strtotime($departure_time)); ?>">
                                                            <i class="fa-solid fa-clock"></i>
                                                            Start Ride (Not Yet)
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-end-ride" onclick="endRide(<?php echo $ride['RideID']; ?>)" disabled>
                                                        <i class="fa-solid fa-flag-checkered"></i>
                                                        End Ride
                                                    </button>
                                                <?php elseif ($ride['Status'] == 'in_progress'): ?>
                                                    <button class="btn btn-start-ride" style="display: none;">
                                                        <i class="fa-solid fa-play"></i>
                                                        Start Ride
                                                    </button>
                                                    <button class="btn btn-end-ride" onclick="endRide(<?php echo $ride['RideID']; ?>)">
                                                        <i class="fa-solid fa-flag-checkered"></i>
                                                        End Ride
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn btn-start-ride" style="display: none;">
                                                        <i class="fa-solid fa-play"></i>
                                                        Start Ride
                                                    </button>
                                                    <button class="btn btn-end-ride" style="display: none;">
                                                        <i class="fa-solid fa-flag-checkered"></i>
                                                        End Ride
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-view-passengers" onclick="viewPassengers(<?php echo $ride['RideID']; ?>)">
                                                    <i class="fa-solid fa-users"></i>
                                                    View Passengers
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <div class="passenger-actions">
                                                <?php if ($ride['BookingID']): ?>
                                                    <?php if ($ride['BookingStatus'] == 'Confirmed'): ?>
                                                        <?php if ($ride['Status'] == 'completed' && $ride['BookingStatus'] != 'Paid'): ?>
                                                            <span class="status-pending-payment">Payment Due</span>
                                                            <a class="btn btn-payment" href="payment.php?booking_id=<?php echo $ride['BookingID']; ?>">
                                                                <i class="fa-solid fa-credit-card"></i> Make Payment
                                                            </a>
                                                            <button class="btn btn-outline" onclick="contactDriver('<?php echo htmlspecialchars($ride['DriverName']); ?>')">
                                                                <i class="fa-solid fa-phone"></i> Contact Driver
                                                            </button>
                                                        <?php elseif ($ride['Status'] == 'in_progress'): ?>
                                                            <span class="status-in-progress-text">Ride in Progress</span>
                                                            <button class="btn btn-outline" onclick="contactDriver('<?php echo htmlspecialchars($ride['DriverName']); ?>')">
                                                                <i class="fa-solid fa-phone"></i> Contact Driver
                                                            </button>
                                                        <?php elseif ($ride['Status'] == 'completed' && $ride['BookingStatus'] == 'Paid'): ?>
                                                            <span class="status-paid-text">Payment Completed</span>
                                                            <button class="btn btn-outline" onclick="contactDriver('<?php echo htmlspecialchars($ride['DriverName']); ?>')">
                                                                <i class="fa-solid fa-phone"></i> Contact Driver
                                                            </button>
                                                        <?php else: ?>
                                                            <span class="status-available-text">Ride Available</span>
                                                            <button class="btn btn-outline" onclick="contactDriver('<?php echo htmlspecialchars($ride['DriverName']); ?>')">
                                                                <i class="fa-solid fa-phone"></i> Contact Driver
                                                            </button>
                                                        <?php endif; ?>

                                                    <?php elseif ($ride['BookingStatus'] == 'Pending'): ?>
                                                        <?php if ($ride['Status'] == 'expired'): ?>
                                                            <span class="status-cancelled-text" style="color: #dc3545;">Ride Expired (Booking Void)</span>
                                                            <button class="btn btn-outline disabled" disabled>
                                                                <i class="fa-solid fa-ban"></i> Request Failed
                                                            </button>
                                                        <?php else: ?>
                                                            <span class="status-pending">Waiting for driver confirmation</span>
                                                            <button class="btn btn-outline" onclick="cancelBooking(<?php echo $ride['BookingID']; ?>)">
                                                                <i class="fa-solid fa-times"></i> Cancel Request
                                                            </button>
                                                        <?php endif; ?>

                                                    <?php elseif ($ride['BookingStatus'] == 'Completed'): ?>
                                                        <span class="status-pending-payment">Payment Due</span>
                                                        <a class="btn btn-payment" href="payment.php?booking_id=<?php echo $ride['BookingID']; ?>">
                                                            <i class="fa-solid fa-credit-card"></i> Make Payment
                                                        </a>
                                                        <button class="btn btn-outline" onclick="contactDriver('<?php echo htmlspecialchars($ride['DriverName']); ?>')">
                                                            <i class="fa-solid fa-phone"></i> Contact Driver
                                                        </button>

                                                    <?php elseif ($ride['BookingStatus'] == 'Paid'): ?>
                                                        <span class="status-paid-text">Ride Completed & Paid</span>

                                                    <?php elseif ($ride['BookingStatus'] == 'Cancelled'): ?>
                                                        <span class="status-cancelled-text">Booking Cancelled</span>
                                                    <?php endif; ?>

                                                <?php else: ?>
                                                    <?php if ($ride['Status'] == 'expired'): ?>
                                                        <button class="btn btn-primary disabled" disabled style="background-color: #6c757d; border-color: #6c757d; cursor: not-allowed; opacity: 0.8;">
                                                            <i class="fa-solid fa-ban"></i> Ride Expired
                                                        </button>
                                                    <?php elseif ($ride['Status'] == 'available'): ?>
                                                        <button class="btn btn-primary" onclick="bookRide(<?php echo $ride['RideID']; ?>)">
                                                            <i class="fa-solid fa-ticket"></i> Book This Ride
                                                        </button>
                                                    <?php elseif ($ride['Status'] == 'in_progress'): ?>
                                                        <span class="status-in-progress-text">Ride in Progress</span>
                                                        <button class="btn btn-outline" onclick="contactDriver('<?php echo htmlspecialchars($ride['DriverName']); ?>')">
                                                            <i class="fa-solid fa-phone"></i> Contact Driver
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="status-completed-text">Ride Completed</span>
                                                    <?php endif; ?>

                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($is_user_driver): ?>
                                        <div class="passengers-section" id="passengers-<?php echo $ride['RideID']; ?>" style="display: none;">
                                            <h4><i class="fa-solid fa-users"></i> Passengers</h4>
                                            <div class="passengers-list">
                                                <div class="loading-passengers">
                                                    <i class="fa-solid fa-spinner fa-spin"></i>
                                                    Loading passengers...
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="fa-solid fa-calendar-times"></i>
                            </div>
                            <h3>No Rides Today</h3>
                            <p>You don't have any active rides scheduled for today.</p>
                            <?php if ($is_driver && $driver['Status'] === 'approved'): ?>
                                <a href="rideoffer.php" class="btn btn-primary">
                                    <i class="fa-solid fa-plus"></i>
                                    Offer a Ride
                                </a>
                            <?php else: ?>
                                <a href="userdashboard.php" class="btn btn-primary">
                                    <i class="fa-solid fa-car"></i>
                                    Find Rides
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="../js/userdashboard.js?v=<?php echo time(); ?>"></script>
    <script src="../js/todaysride.js?v=<?php echo time(); ?>"></script>
</body>

</html>

<?php
// Helper function to calculate time until departure
function timeUntil($departure_time, $current_time)
{
    $departure = strtotime($departure_time);
    $current = strtotime($current_time);

    if ($current >= $departure) {
        return "0 minutes";
    }

    $diff = $departure - $current;
    $hours = floor($diff / 3600);
    $minutes = floor(($diff % 3600) / 60);

    if ($hours > 0) {
        return $hours . " hour" . ($hours > 1 ? "s" : "") . " " . $minutes . " minute" . ($minutes > 1 ? "s" : "");
    } else {
        return $minutes . " minute" . ($minutes > 1 ? "s" : "");
    }
}
?>