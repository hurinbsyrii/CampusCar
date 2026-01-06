<?php
session_start();

// Ensure PHP uses Malaysia timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if booking_id is provided
if (!isset($_GET['booking_id']) || !is_numeric($_GET['booking_id'])) {
    header("Location: mybookings.php");
    exit();
}

$booking_id = $_GET['booking_id'];
$user_id = $_SESSION['user_id'];

// Database connection
$servername = "127.0.0.1:3301";
$username = "root";
$password = "";
$dbname = "campuscar";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Ensure MySQL connection uses Malaysia timezone
$conn->query("SET time_zone = '+08:00'");

// Get booking details with validation for review eligibility
$sql = "SELECT 
            b.*, 
            r.*, 
            d.*,
            u.FullName as DriverName,
            du.FullName as PassengerName,
            p.PaymentStatus,
            rev.ReviewID as ExistingReviewID
        FROM booking b
        JOIN rides r ON b.RideID = r.RideID
        JOIN driver d ON r.DriverID = d.DriverID
        JOIN user u ON d.UserID = u.UserID
        JOIN user du ON b.UserID = du.UserID
        LEFT JOIN payments p ON b.BookingID = p.BookingID AND p.UserID = b.UserID
        LEFT JOIN reviews rev ON b.BookingID = rev.BookingID
        WHERE b.BookingID = ? 
        AND b.UserID = ?
        AND b.BookingStatus = 'Paid'
        AND p.PaymentStatus = 'paid'
        AND r.Status = 'completed'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: mybookings.php?message=not_eligible");
    exit();
}

$booking = $result->fetch_assoc();
$stmt->close();

// Check if review already exists
if ($booking['ExistingReviewID']) {
    header("Location: mybookings.php?message=already_reviewed");
    exit();
}

// Check if review is within 7 days of ride completion (optional)
$ride_date = $booking['RideDate'] . ' ' . $booking['DepartureTime'];
$ride_timestamp = strtotime($ride_date);
$current_timestamp = time();
$days_diff = floor(($current_timestamp - $ride_timestamp) / (60 * 60 * 24));

// Optional: Uncomment to enforce 7-day limit
// if ($days_diff > 7) {
//     header("Location: mybookings.php?message=review_expired");
//     exit();
// }

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Rate Your Ride - CampusCar</title>
    <link rel="stylesheet" href="../css/userdashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/review.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
    <div class="dashboard-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="user-avatar">
                    <i class="fa-solid fa-user-circle"></i>
                </div>
                <div class="user-details">
                    <h3><?php echo $_SESSION['full_name'] ?? 'User'; ?></h3>
                    <span class="user-role">Passenger</span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <ul>
                    <li class="nav-item">
                        <a href="userdashboard.php" class="nav-link">
                            <i class="fa-solid fa-gauge"></i>
                            <span>Find Ride</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="userprofile.php" class="nav-link">
                            <i class="fa-solid fa-user"></i>
                            <span>My Profile</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="mybookings.php" class="nav-link">
                            <i class="fa-solid fa-ticket"></i>
                            <span>My Bookings</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="todaysride.php" class="nav-link">
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

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
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

            <!-- Main Content -->
            <main class="dashboard-main">
                <div class="review-container">
                    <!-- Page Header -->
                    <div class="page-header">
                        <h1><i class="fa-solid fa-star"></i> Rate Your Ride</h1>
                        <p>Share your experience to help improve our service</p>
                    </div>

                    <div class="review-wrapper">
                        <!-- Left Column: Ride Details -->
                        <div class="review-left">
                            <div class="ride-summary">
                                <h3><i class="fa-solid fa-receipt"></i> Ride Summary</h3>
                                <div class="summary-details">
                                    <div class="summary-item">
                                        <span class="label">Booking ID:</span>
                                        <span class="value">#<?php echo str_pad($booking['BookingID'], 6, '0', STR_PAD_LEFT); ?></span>
                                    </div>
                                    <div class="summary-item">
                                        <span class="label">Route:</span>
                                        <span class="value">
                                            <?php echo htmlspecialchars($booking['FromLocation']); ?>
                                            <i class="fa-solid fa-arrow-right"></i>
                                            <?php echo htmlspecialchars($booking['ToLocation']); ?>
                                        </span>
                                    </div>
                                    <div class="summary-item">
                                        <span class="label">Driver:</span>
                                        <span class="value"><?php echo htmlspecialchars($booking['DriverName']); ?></span>
                                    </div>
                                    <div class="summary-item">
                                        <span class="label">Ride Date:</span>
                                        <span class="value"><?php echo date('F j, Y', strtotime($booking['RideDate'])); ?></span>
                                    </div>
                                    <div class="summary-item">
                                        <span class="label">Departure Time:</span>
                                        <span class="value"><?php echo date('g:i A', strtotime($booking['DepartureTime'])); ?></span>
                                    </div>
                                    <div class="summary-item">
                                        <span class="label">Price Paid:</span>
                                        <span class="value">RM<?php echo number_format($booking['TotalPrice'], 2); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="review-guidelines">
                                <h3><i class="fa-solid fa-lightbulb"></i> Review Guidelines</h3>
                                <ul>
                                    <li><i class="fa-solid fa-check"></i> Be honest and fair in your rating</li>
                                    <li><i class="fa-solid fa-check"></i> Focus on your actual experience</li>
                                    <li><i class="fa-solid fa-check"></i> Keep comments respectful and constructive</li>
                                    <li><i class="fa-solid fa-check"></i> Reviews are anonymous to drivers</li>
                                </ul>
                            </div>
                        </div>

                        <!-- Right Column: Review Form -->
                        <div class="review-right">
                            <div class="review-form-container">
                                <h3><i class="fa-solid fa-comment-dots"></i> How was your ride?</h3>

                                <form id="reviewForm" action="../database/reviewdb.php" method="POST">
                                    <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
                                    <input type="hidden" name="driver_id" value="<?php echo $booking['DriverID']; ?>">
                                    <input type="hidden" name="ride_id" value="<?php echo $booking['RideID']; ?>">

                                    <!-- Star Rating -->
                                    <div class="rating-section">
                                        <h4>Overall Rating</h4>
                                        <div class="star-rating">
                                            <div class="stars" id="stars">
                                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                                    <input type="radio" id="star<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" <?php echo $i == 5 ? 'checked' : ''; ?>>
                                                    <label for="star<?php echo $i; ?>">
                                                        <i class="fa-solid fa-star"></i>
                                                    </label>
                                                <?php endfor; ?>
                                            </div>
                                            <div class="rating-labels">
                                                <span id="rating-text">Excellent</span>
                                            </div>
                                        </div>
                                        <div class="rating-descriptions">
                                            <div class="rating-desc" data-rating="1">Poor - Very dissatisfied</div>
                                            <div class="rating-desc" data-rating="2">Fair - Could be better</div>
                                            <div class="rating-desc" data-rating="3">Good - Met expectations</div>
                                            <div class="rating-desc" data-rating="4">Very Good - Exceeded expectations</div>
                                            <div class="rating-desc active" data-rating="5">Excellent - Outstanding experience</div>
                                        </div>
                                    </div>

                                    <!-- Comment Section -->
                                    <div class="comment-section">
                                        <h4>Share your experience (optional)</h4>
                                        <div class="form-group">
                                            <textarea id="comment" name="comment" class="form-control"
                                                placeholder="Tell us about your ride experience... 
- Was the driver punctual?
- How was the car condition?
- Any suggestions for improvement?
- What did you like most?

(500 characters maximum)"
                                                maxlength="500" rows="6"></textarea>
                                            <div class="char-count">
                                                <span id="char-count">0</span>/500 characters
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Terms and Conditions -->
                                    <div class="terms-section">
                                        <div class="form-check">
                                            <input type="checkbox" id="terms" name="terms" required>
                                            <label for="terms">I confirm this review is based on my genuine experience</label>
                                        </div>
                                    </div>

                                    <!-- Submit Button -->
                                    <div class="form-submit">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fa-solid fa-paper-plane"></i>
                                            Submit Review
                                        </button>
                                        <a href="mybookings.php" class="btn btn-outline">
                                            <i class="fa-solid fa-times"></i>
                                            Cancel
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="../js/userdashboard.js?v=<?php echo time(); ?>"></script>
    <script src="../js/review.js?v=<?php echo time(); ?>"></script>
</body>

</html>