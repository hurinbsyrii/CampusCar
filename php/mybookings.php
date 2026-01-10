<?php
session_start();
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

$user_id = $_SESSION['user_id'];

// Check if user is a driver
$is_driver = false;
$driver = null; // Initialize driver variable
$driver_check_sql = "SELECT * FROM driver WHERE UserID = ?";
$stmt = $conn->prepare($driver_check_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $is_driver = true;
    $driver = $result->fetch_assoc();
}
$stmt->close();

// Get user's bookings (as passenger) - ALL bookings for display (INCLUDING CANCELLED)
$passenger_bookings_sql = "SELECT b.*, r.FromLocation, r.ToLocation, r.RideDate, r.DepartureTime, 
                                  u.FullName as DriverName, u.PhoneNumber as DriverPhone, d.CarModel,
                                  p.PaymentStatus,
                                  rev.ReviewID
                           FROM booking b
                           JOIN rides r ON b.RideID = r.RideID
                           JOIN driver dr ON r.DriverID = dr.DriverID
                           JOIN user u ON dr.UserID = u.UserID
                           JOIN driver d ON dr.DriverID = d.DriverID
                           LEFT JOIN payments p ON b.BookingID = p.BookingID AND p.UserID = b.UserID
                           LEFT JOIN reviews rev ON b.BookingID = rev.BookingID
                           WHERE b.UserID = ?
                           ORDER BY b.BookingDateTime DESC";
$stmt = $conn->prepare($passenger_bookings_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$passenger_bookings = $stmt->get_result();
$stmt->close();

// Count only PENDING bookings for passenger tab (EXCLUDE CANCELLED)
$passenger_pending_count_sql = "SELECT COUNT(*) as pending_count 
                                FROM booking 
                                WHERE UserID = ? AND BookingStatus = 'Pending'";
$stmt = $conn->prepare($passenger_pending_count_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$passenger_pending_result = $stmt->get_result();
$passenger_pending_count = $passenger_pending_result->fetch_assoc()['pending_count'];
$stmt->close();

// Get driver's ride bookings (if user is a driver AND APPROVED)
$driver_bookings = null;
$driver_pending_count = 0;

// KEMASKINI LOGIK: Hanya tarik data jika status approved
if ($is_driver && $driver['Status'] === 'approved') {
    $driver_bookings_sql = "SELECT b.*, r.FromLocation, r.ToLocation, r.RideDate, r.DepartureTime,
                                   u.FullName as PassengerName, u.PhoneNumber as PassengerPhone,
                                   p.PaymentStatus
                            FROM booking b
                            JOIN rides r ON b.RideID = r.RideID
                            JOIN user u ON b.UserID = u.UserID
                            LEFT JOIN payments p ON b.BookingID = p.BookingID AND p.UserID = b.UserID
                            WHERE r.DriverID = ?
                            ORDER BY b.BookingDateTime DESC";
    $stmt = $conn->prepare($driver_bookings_sql);
    $stmt->bind_param("i", $driver['DriverID']);
    $stmt->execute();
    $driver_bookings = $stmt->get_result();
    $stmt->close();

    // Count only PENDING bookings for driver tab (EXCLUDE CANCELLED)
    $driver_pending_count_sql = "SELECT COUNT(*) as pending_count 
                                 FROM booking b
                                 JOIN rides r ON b.RideID = r.RideID
                                 WHERE r.DriverID = ? AND b.BookingStatus = 'Pending'";
    $stmt = $conn->prepare($driver_pending_count_sql);
    $stmt->bind_param("i", $driver['DriverID']);
    $stmt->execute();
    $driver_pending_result = $stmt->get_result();
    $driver_pending_count = $driver_pending_result->fetch_assoc()['pending_count'];
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - CampusCar</title>
    <link rel="stylesheet" href="../css/userdashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/mybookings.css?v=<?php echo time(); ?>">
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
                    <li class="nav-item active">
                        <a href="mybookings.php" class="nav-link" data-section="bookings">
                            <div class="nav-link-content">
                                <i class="fa-solid fa-ticket"></i>
                                <span>My Bookings</span>
                            </div>
                            <?php if ($is_driver && $driver['Status'] === 'approved' && $driver_pending_count > 0): ?>
                                <span class="notification-dot" title="<?php echo $driver_pending_count; ?> new requests"></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="todaysride.php" class="nav-link" data-section="todays">
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
                        </div>
                        <a href="userprofile.php" class="profile-btn">
                            <i class="fa-solid fa-user"></i>
                            My Profile
                        </a>
                    </div>
                </div>
            </header>

            <main class="dashboard-main">
                <div class="bookings-container">
                    <div class="page-header">
                        <h1><i class="fa-solid fa-ticket"></i> My Bookings</h1>
                        <p>Manage your ride bookings and passenger requests</p>
                        <p class="info-note">
                            <i class="fa-solid fa-info-circle"></i>
                            Cancelled bookings will not appear in Today's Rides
                        </p>
                    </div>

                    <div class="booking-tabs">
                        <button class="tab-btn active" data-tab="passenger-bookings">
                            <i class="fa-solid fa-user"></i>
                            My Bookings
                            <span class="tab-count"><?php echo $passenger_pending_count; ?></span>
                        </button>

                        <?php if ($is_driver && $driver['Status'] === 'approved'): ?>
                            <button class="tab-btn" data-tab="driver-bookings">
                                <i class="fa-solid fa-id-card"></i>
                                Passenger Requests
                                <span class="tab-count"><?php echo $driver_pending_count; ?></span>
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="tab-content active" id="passenger-bookings">
                        <div class="bookings-header">
                            <h2>My Ride Bookings</h2>
                            <p>All your booked rides in one place</p>
                        </div>

                        <?php if ($passenger_bookings->num_rows > 0): ?>
                            <div class="bookings-grid">
                                <?php while ($booking = $passenger_bookings->fetch_assoc()):
                                    // Determine status class
                                    $status_class = 'status-' . strtolower($booking['BookingStatus']);
                                    $payment_status = $booking['PaymentStatus'] ?? null;
                                    $has_review = !empty($booking['ReviewID']);
                                ?>
                                    <div class="booking-card" data-booking-id="<?php echo $booking['BookingID']; ?>">
                                        <div class="booking-badge">
                                            <span class="status-badge <?php echo $status_class; ?>">
                                                <?php echo $booking['BookingStatus']; ?>
                                            </span>
                                            <?php if ($booking['BookingStatus'] == 'Paid' && $payment_status == 'paid' && $has_review): ?>
                                                <span class="status-reviewed">
                                                    <i class="fa-solid fa-star"></i>
                                                    Reviewed
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="booking-content">
                                            <div class="route-info">
                                                <h3>
                                                    <i class="fa-solid fa-route"></i>
                                                    <?php echo htmlspecialchars($booking['FromLocation']); ?>
                                                    <i class="fa-solid fa-arrow-right"></i>
                                                    <?php echo htmlspecialchars($booking['ToLocation']); ?>
                                                </h3>
                                            </div>
                                            <div class="booking-details">
                                                <div class="detail-group">
                                                    <div class="detail-item">
                                                        <i class="fa-solid fa-calendar"></i>
                                                        <span><?php echo date('M j, Y', strtotime($booking['RideDate'])); ?></span>
                                                    </div>
                                                    <div class="detail-item">
                                                        <i class="fa-solid fa-clock"></i>
                                                        <span><?php echo date('g:i A', strtotime($booking['DepartureTime'])); ?></span>
                                                    </div>
                                                    <div class="detail-item">
                                                        <i class="fa-solid fa-user-friends"></i>
                                                        <span><?php echo $booking['NoOfSeats']; ?> seats</span>
                                                    </div>
                                                </div>
                                                <div class="detail-group">
                                                    <div class="detail-item">
                                                        <i class="fa-solid fa-user-tie"></i>
                                                        <span><?php echo htmlspecialchars($booking['DriverName']); ?></span>
                                                    </div>
                                                    <div class="detail-item">
                                                        <i class="fa-solid fa-car"></i>
                                                        <span><?php echo htmlspecialchars($booking['CarModel']); ?></span>
                                                    </div>
                                                    <div class="detail-item">
                                                        <i class="fa-solid fa-phone"></i>
                                                        <span><?php echo htmlspecialchars($booking['DriverPhone']); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="booking-footer">
                                                <div class="price-info">
                                                    <span class="total-price">RM<?php echo $booking['TotalPrice']; ?></span>
                                                    <span class="price-label">Total Amount</span>
                                                </div>
                                                <div class="booking-actions">
                                                    <?php if ($booking['BookingStatus'] == 'Pending'): ?>
                                                        <button class="btn btn-outline btn-cancel" onclick="cancelBooking(<?php echo $booking['BookingID']; ?>)">
                                                            <i class="fa-solid fa-times"></i>
                                                            Cancel
                                                        </button>
                                                    <?php endif; ?>

                                                    <?php if ($booking['BookingStatus'] == 'Paid' && $payment_status == 'paid' && !$has_review): ?>
                                                        <a href="review.php?booking_id=<?php echo $booking['BookingID']; ?>" class="btn btn-success">
                                                            <i class="fa-solid fa-star"></i>
                                                            Leave Review
                                                        </a>
                                                    <?php endif; ?>

                                                    <button class="btn btn-primary" onclick="contactDriver('<?php echo htmlspecialchars($booking['DriverPhone']); ?>')">
                                                        <i class="fa-solid fa-phone"></i>
                                                        Contact Driver
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-icon">
                                    <i class="fa-solid fa-ticket"></i>
                                </div>
                                <h3>No Bookings Yet</h3>
                                <p>You haven't made any ride bookings yet. Start by exploring available rides!</p>
                                <a href="userdashboard.php" class="btn btn-primary">
                                    <i class="fa-solid fa-car"></i>
                                    Find Rides
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($is_driver && $driver['Status'] === 'approved'): ?>
                        <div class="tab-content" id="driver-bookings">
                            <div class="bookings-header">
                                <h2>Passenger Requests</h2>
                                <p>Manage booking requests for your rides</p>
                            </div>

                            <?php if ($driver_bookings && $driver_bookings->num_rows > 0): ?>
                                <div class="bookings-grid">
                                    <?php while ($booking = $driver_bookings->fetch_assoc()):
                                        $status_class = 'status-' . strtolower($booking['BookingStatus']);
                                    ?>
                                        <div class="booking-card driver-booking" data-booking-id="<?php echo $booking['BookingID']; ?>">
                                            <div class="booking-badge">
                                                <span class="status-badge <?php echo $status_class; ?>">
                                                    <?php echo $booking['BookingStatus']; ?>
                                                </span>
                                            </div>
                                            <div class="booking-content">
                                                <div class="route-info">
                                                    <h3>
                                                        <i class="fa-solid fa-route"></i>
                                                        <?php echo htmlspecialchars($booking['FromLocation']); ?>
                                                        <i class="fa-solid fa-arrow-right"></i>
                                                        <?php echo htmlspecialchars($booking['ToLocation']); ?>
                                                    </h3>
                                                </div>
                                                <div class="booking-details">
                                                    <div class="detail-group">
                                                        <div class="detail-item">
                                                            <i class="fa-solid fa-calendar"></i>
                                                            <span><?php echo date('M j, Y', strtotime($booking['RideDate'])); ?></span>
                                                        </div>
                                                        <div class="detail-item">
                                                            <i class="fa-solid fa-clock"></i>
                                                            <span><?php echo date('g:i A', strtotime($booking['DepartureTime'])); ?></span>
                                                        </div>
                                                        <div class="detail-item">
                                                            <i class="fa-solid fa-user-friends"></i>
                                                            <span><?php echo $booking['NoOfSeats']; ?> seats</span>
                                                        </div>
                                                    </div>
                                                    <div class="detail-group">
                                                        <div class="detail-item">
                                                            <i class="fa-solid fa-user"></i>
                                                            <span><?php echo htmlspecialchars($booking['PassengerName']); ?></span>
                                                        </div>
                                                        <div class="detail-item">
                                                            <i class="fa-solid fa-phone"></i>
                                                            <span><?php echo htmlspecialchars($booking['PassengerPhone']); ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="booking-footer">
                                                    <div class="price-info">
                                                        <span class="total-price">RM<?php echo $booking['TotalPrice']; ?></span>
                                                        <span class="price-label">Total Amount</span>
                                                    </div>
                                                    <div class="booking-actions">
                                                        <?php if ($booking['BookingStatus'] == 'Pending'): ?>
                                                            <button class="btn btn-success" onclick="updateBookingStatus(<?php echo $booking['BookingID']; ?>, 'Confirmed')">
                                                                <i class="fa-solid fa-check"></i>
                                                                Confirm
                                                            </button>
                                                            <button class="btn btn-danger" onclick="updateBookingStatus(<?php echo $booking['BookingID']; ?>, 'Cancelled')">
                                                                <i class="fa-solid fa-times"></i>
                                                                Reject
                                                            </button>
                                                        <?php endif; ?>
                                                        <button class="btn btn-outline" onclick="contactPassenger('<?php echo htmlspecialchars($booking['PassengerPhone']); ?>')">
                                                            <i class="fa-solid fa-phone"></i>
                                                            Contact
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <i class="fa-solid fa-users"></i>
                                    </div>
                                    <h3>No Passenger Requests</h3>
                                    <p>You don't have any booking requests for your rides yet.</p>
                                    <a href="rideoffer.php" class="btn btn-primary">
                                        <i class="fa-solid fa-plus"></i>
                                        Offer a Ride
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="../js/userdashboard.js?v=<?php echo time(); ?>"></script>
    <script src="../js/mybookings.js?v=<?php echo time(); ?>"></script>
</body>

</html>