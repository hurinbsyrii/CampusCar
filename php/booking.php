<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get ride ID from URL
if (!isset($_GET['ride_id']) || empty($_GET['ride_id'])) {
    header("Location: userdashboard.php");
    exit();
}

$ride_id = intval($_GET['ride_id']);

// Database connection
$servername = "127.0.0.1:3301";
$username = "root";
$password = "";
$dbname = "campuscar";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get ride details
$ride_sql = "SELECT r.*, u.FullName as DriverName, u.PhoneNumber as DriverPhone, 
                    d.CarModel, d.CarPlateNumber, u.Gender as DriverGender
             FROM rides r 
             JOIN driver d ON r.DriverID = d.DriverID 
             JOIN user u ON d.UserID = u.UserID 
             WHERE r.RideID = ? AND r.Status = 'available'";
$stmt = $conn->prepare($ride_sql);
$stmt->bind_param("i", $ride_id);
$stmt->execute();
$ride_result = $stmt->get_result();

if ($ride_result->num_rows === 0) {
    header("Location: userdashboard.php");
    exit();
}

$ride = $ride_result->fetch_assoc();
$stmt->close();

// Ensure FemaleOnly key exists
if (!isset($ride['FemaleOnly'])) {
    $ride['FemaleOnly'] = 0;
}

// Get user details
$user_id = $_SESSION['user_id'];
$user_sql = "SELECT FullName, PhoneNumber, Gender FROM user WHERE UserID = ?";
$stmt = $conn->prepare($user_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();
$stmt->close();

// PERUBAHAN: Check for ACTIVE bookings on the same date/time
// Paid status is ALLOWED because ride is already completed
$check_availability_sql = "SELECT b.BookingID, r.RideID, r.FromLocation, r.ToLocation, 
                                  r.RideDate, r.DepartureTime, b.BookingStatus
                           FROM booking b
                           JOIN rides r ON b.RideID = r.RideID
                           WHERE b.UserID = ? 
                           AND b.BookingStatus IN ('Pending', 'Confirmed', 'In Progress')
                           AND r.RideDate = ?
                           AND ABS(TIME_TO_SEC(TIMEDIFF(r.DepartureTime, ?))) < 3600";
$stmt = $conn->prepare($check_availability_sql);
$stmt->bind_param("iss", $user_id, $ride['RideDate'], $ride['DepartureTime']);
$stmt->execute();
$availability_result = $stmt->get_result();

$conflicting_booking = null;
if ($availability_result->num_rows > 0) {
    $conflicting_booking = $availability_result->fetch_assoc();
}
$stmt->close();

// Check if user can book this ride (Girls Only validation)
if ($ride['FemaleOnly'] == 1 && $user['Gender'] !== 'female') {
    $_SESSION['error'] = "This ride is for female passengers only.";
    header("Location: userdashboard.php");
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Ride - CampusCar</title>
    <link rel="stylesheet" href="../css/booking.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Girls Only Notice */
        .gender-notice {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 15px;
            background: rgba(231, 84, 128, 0.1);
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid #e75480;
        }

        .gender-notice i {
            color: #e75480;
            font-size: 1.2rem;
        }

        .gender-notice span {
            font-weight: 500;
            color: #333;
        }

        /* Girls Only Badge */
        .girls-only-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #e75480;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-left: 10px;
        }

        .girls-only-badge i {
            font-size: 0.9rem;
        }

        /* Conflict Warning */
        .conflict-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
            color: #856404;
        }

        .conflict-warning h4 {
            color: #856404;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .conflict-warning h4 i {
            color: #ffc107;
        }

        .conflict-details {
            background: white;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
            border-left: 3px solid #ffc107;
        }

        .conflict-details p {
            margin: 5px 0;
        }

        .mt-3 {
            margin-top: 15px;
        }
    </style>
</head>

<body>
    <div class="booking-container">
        <!-- Header -->
        <header class="booking-header">
            <div class="header-content">
                <div class="logo">
                    <i class="fa-solid fa-car-side"></i>
                    <span>CampusCar</span>
                </div>
                <div class="user-info">
                    <div class="user-welcome">
                        <i class="fa-solid fa-user-circle"></i>
                        <span>Welcome, <?php echo $_SESSION['full_name'] ?? 'User'; ?></span>
                        <span class="girls-only-badge" style="<?php echo ($user['Gender'] === 'female') ? 'background: #4CAF50;' : 'background: #2196F3;'; ?>">
                            <i class="fa-solid fa-<?php echo ($user['Gender'] === 'female') ? 'venus' : 'mars'; ?>"></i>
                            <?php echo ucfirst($user['Gender']); ?>
                        </span>
                    </div>
                    <a href="userdashboard.php" class="back-btn">
                        <i class="fa-solid fa-arrow-left"></i>
                        Back to Dashboard
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="booking-main">
            <div class="booking-layout">
                <!-- Booking Form -->
                <div class="booking-form-section">
                    <div class="booking-card">
                        <div class="booking-header">
                            <h1><i class="fa-solid fa-ticket"></i> Book Your Ride</h1>
                            <p>Complete your booking details</p>
                        </div>

                        <?php if ($conflicting_booking): ?>
                            <div class="conflict-warning">
                                <h4><i class="fa-solid fa-exclamation-triangle"></i> Scheduling Conflict Detected</h4>
                                <p>You already have an <strong>active booking</strong> at this time:</p>
                                <div class="conflict-details">
                                    <p><strong>From:</strong> <?php echo htmlspecialchars($conflicting_booking['FromLocation']); ?></p>
                                    <p><strong>To:</strong> <?php echo htmlspecialchars($conflicting_booking['ToLocation']); ?></p>
                                    <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($conflicting_booking['RideDate'])); ?></p>
                                    <p><strong>Time:</strong> <?php echo date('g:i A', strtotime($conflicting_booking['DepartureTime'])); ?></p>
                                    <p><strong>Status:</strong> <span style="color: #d35400; font-weight: bold;"><?php echo htmlspecialchars($conflicting_booking['BookingStatus']); ?></span></p>
                                </div>
                                <p class="mt-3"><em>Please <strong>complete or cancel</strong> your existing booking before booking a new ride at this time.</em></p>
                                <p><strong>Note:</strong> "Paid" status is allowed because the ride is already completed.</p>
                            </div>
                        <?php endif; ?>

                        <form id="bookingForm" class="booking-form" <?php echo $conflicting_booking ? 'onsubmit="return showConflictError();"' : ''; ?>>
                            <!-- Ride Information Summary -->
                            <div class="ride-summary">
                                <h3>
                                    <i class="fa-solid fa-route"></i> Ride Details
                                    <?php if ($ride['FemaleOnly']): ?>
                                        <span class="girls-only-badge">
                                            <i class="fa-solid fa-venus"></i>
                                            Girls Only
                                        </span>
                                    <?php endif; ?>
                                </h3>
                                <div class="summary-content">
                                    <div class="route">
                                        <strong><?php echo htmlspecialchars($ride['FromLocation']); ?></strong>
                                        <i class="fa-solid fa-arrow-right"></i>
                                        <strong><?php echo htmlspecialchars($ride['ToLocation']); ?></strong>
                                    </div>
                                    <div class="summary-details">
                                        <div class="detail">
                                            <i class="fa-solid fa-calendar"></i>
                                            <span><?php echo date('F j, Y', strtotime($ride['RideDate'])); ?></span>
                                        </div>
                                        <div class="detail">
                                            <i class="fa-solid fa-clock"></i>
                                            <span><?php echo date('g:i A', strtotime($ride['DepartureTime'])); ?></span>
                                        </div>
                                        <div class="detail">
                                            <i class="fa-solid fa-user"></i>
                                            <span><?php echo htmlspecialchars($ride['DriverName']); ?></span>
                                        </div>
                                        <div class="detail">
                                            <i class="fa-solid fa-car"></i>
                                            <span><?php echo htmlspecialchars($ride['CarModel']); ?></span>
                                        </div>
                                        <div class="detail">
                                            <i class="fa-solid fa-id-card"></i>
                                            <span>Number Plate : <?php echo htmlspecialchars($ride['CarPlateNumber']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Booking Details -->
                            <div class="form-section">
                                <h3><i class="fa-solid fa-user-edit"></i> Your Details</h3>

                                <?php if ($ride['FemaleOnly']): ?>
                                    <div class="gender-notice">
                                        <i class="fa-solid fa-venus"></i>
                                        <span><strong>Girls Only Ride</strong> - This ride is exclusively for female passengers</span>
                                    </div>
                                <?php endif; ?>

                                <div class="form-group">
                                    <label for="passengerName">
                                        <i class="fa-solid fa-user"></i>
                                        Full Name *
                                    </label>
                                    <div class="input-group">
                                        <input type="text" id="passengerName" name="passengerName"
                                            value="<?php echo htmlspecialchars($user['FullName']); ?>" required readonly>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="passengerPhone">
                                            <i class="fa-solid fa-phone"></i>
                                            Phone Number *
                                        </label>
                                        <div class="input-group">
                                            <input type="tel" id="passengerPhone" name="passengerPhone"
                                                value="<?php echo htmlspecialchars($user['PhoneNumber']); ?>" required>
                                        </div>
                                        <small class="help-text">Your contact number for driver communication</small>
                                        <small class="error-text" id="passengerPhoneError"></small>
                                    </div>

                                    <div class="form-group">
                                        <label for="numberOfSeats">
                                            <i class="fa-solid fa-user-friends"></i>
                                            Number of Pax *
                                        </label>
                                        <div class="input-group">
                                            <select id="numberOfSeats" name="numberOfSeats" required>
                                                <?php for ($i = 1; $i <= $ride['AvailableSeats']; $i++): ?>
                                                    <option value="<?php echo $i; ?>"><?php echo $i; ?> pax<?php echo $i > 1 ? 's' : ''; ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                        <small class="help-text">Maximum <?php echo $ride['AvailableSeats']; ?> pax available</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Special Requests -->
                            <div class="form-section">
                                <h3><i class="fa-solid fa-comment-dots"></i> Additional Information</h3>
                                <div class="form-group">
                                    <label for="specialRequests">
                                        <i class="fa-solid fa-note-sticky"></i>
                                        Special Requests (Optional)
                                    </label>
                                    <div class="input-group">
                                        <textarea id="specialRequests" name="specialRequests" rows="3"
                                            placeholder="Any special requests or instructions for the driver..."></textarea>
                                    </div>
                                    <small class="help-text">Meeting point preferences, luggage details, etc.</small>
                                </div>
                            </div>

                            <!-- Price Summary -->
                            <div class="price-summary">
                                <h3><i class="fa-solid fa-receipt"></i> Price Summary</h3>
                                <div class="price-breakdown">
                                    <div class="price-item">
                                        <span>Price per seat</span>
                                        <span class="price-amount">RM <span id="pricePerSeat"><?php echo $ride['PricePerSeat']; ?></span></span>
                                    </div>
                                    <div class="price-item">
                                        <span>Number of pax</span>
                                        <span class="price-amount" id="seatsCount">1</span>
                                    </div>
                                    <div class="price-divider"></div>
                                    <div class="price-total">
                                        <span><strong>Total Amount</strong></span>
                                        <span class="total-amount">RM <span id="totalPrice"><?php echo $ride['PricePerSeat']; ?></span></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Hidden Fields -->
                            <input type="hidden" id="rideID" name="rideID" value="<?php echo $ride_id; ?>">
                            <input type="hidden" id="userID" name="userID" value="<?php echo $user_id; ?>">
                            <input type="hidden" id="userGender" name="userGender" value="<?php echo $user['Gender']; ?>">
                            <input type="hidden" id="femaleOnly" name="femaleOnly" value="<?php echo $ride['FemaleOnly']; ?>">

                            <div class="form-actions">
                                <button type="button" onclick="goBack()" class="btn-secondary">
                                    <i class="fa-solid fa-times"></i>
                                    Cancel
                                </button>
                                <button type="submit" class="btn-primary" id="submitBtn" <?php echo $conflicting_booking ? 'disabled' : ''; ?>>
                                    <i class="fa-solid fa-credit-card"></i>
                                    <?php echo $conflicting_booking ? 'Booking Conflict' : 'Confirm Booking'; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Booking Info Sidebar -->
                <div class="booking-info">
                    <div class="info-card">
                        <h3><i class="fa-solid fa-circle-info"></i> Booking Information</h3>
                        <ul class="info-list">
                            <li>
                                <i class="fa-solid fa-shield-check"></i>
                                <div>
                                    <strong>Secure Booking</strong>
                                    <span>Your booking is protected</span>
                                </div>
                            </li>
                            <li>
                                <i class="fa-solid fa-clock"></i>
                                <div>
                                    <strong>Instant Confirmation</strong>
                                    <span>Get confirmed immediately</span>
                                </div>
                            </li>
                            <li>
                                <i class="fa-solid fa-phone"></i>
                                <div>
                                    <strong>Driver Contact</strong>
                                    <span>Contact details provided after booking</span>
                                </div>
                            </li>
                            <li>
                                <i class="fa-solid fa-rotate-left"></i>
                                <div>
                                    <strong>Flexible Cancellation</strong>
                                    <span>Cancel up to 1 hour before ride</span>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <div class="driver-card">
                        <h4><i class="fa-solid fa-user-tie"></i> About the Driver</h4>
                        <div class="driver-info">
                            <div class="driver-avatar">
                                <i class="fa-solid fa-user"></i>
                            </div>
                            <div class="driver-details">
                                <strong><?php echo htmlspecialchars($ride['DriverName']); ?></strong>
                                <span><?php echo htmlspecialchars($ride['CarModel']); ?></span>
                                <span class="car-plate">
                                    <i class="fa-solid fa-id-card"></i>
                                    <?php echo htmlspecialchars($ride['CarPlateNumber']); ?>
                                </span>
                                <span class="driver-rating">
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star"></i>
                                    <i class="fa-solid fa-star-half-alt"></i>
                                    4.5
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="support-card">
                        <h4><i class="fa-solid fa-headset"></i> Need Help?</h4>
                        <p>Contact our support team for assistance with your booking.</p>
                        <div class="contact-info">
                            <p><i class="fa-solid fa-phone"></i> 06-123 4567</p>
                            <p><i class="fa-solid fa-envelope"></i> campuscar.team@gmail.com</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../js/booking.js?v=<?php echo time(); ?>"></script>
    <script>
        function showConflictError() {
            showNotification('You already have an active booking at this time. Please complete or cancel your existing booking first.', 'error');
            return false;
        }
    </script>
</body>

</html>