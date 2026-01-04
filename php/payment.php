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
    header("Location: todaysride.php");
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

// Get booking details with ride and driver information
$sql = "SELECT 
            b.*, 
            r.*, 
            d.*,
            u.FullName as DriverName,
            u.PhoneNumber as DriverPhone,
            du.FullName as PassengerName,
            du.Email as PassengerEmail
        FROM booking b
        JOIN rides r ON b.RideID = r.RideID
        JOIN driver d ON r.DriverID = d.DriverID
        JOIN user u ON d.UserID = u.UserID
        JOIN user du ON b.UserID = du.UserID
        WHERE b.BookingID = ? 
AND b.UserID = ?
AND b.BookingStatus IN ('Completed', 'Confirmed')";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Check if booking is already paid
    $check_paid_sql = "SELECT * FROM payments WHERE BookingID = ? AND UserID = ? AND PaymentStatus = 'paid'";
    $check_stmt = $conn->prepare($check_paid_sql);
    $check_stmt->bind_param("ii", $booking_id, $user_id);
    $check_stmt->execute();
    $paid_result = $check_stmt->get_result();

    if ($paid_result->num_rows > 0) {
        header("Location: mybookings.php?message=already_paid");
        exit();
    } else {
        header("Location: todaysride.php?message=invalid_booking");
        exit();
    }
}

$booking = $result->fetch_assoc();
$stmt->close();

// Check if payment already exists
$payment_check_sql = "SELECT * FROM payments WHERE BookingID = ? AND UserID = ?";
$payment_stmt = $conn->prepare($payment_check_sql);
$payment_stmt->bind_param("ii", $booking_id, $user_id);
$payment_stmt->execute();
$payment_result = $payment_stmt->get_result();

$existing_payment = null;
if ($payment_result->num_rows > 0) {
    $existing_payment = $payment_result->fetch_assoc();
}

$payment_stmt->close();

// Malaysian banks list
$malaysian_banks = [
    'maybank' => 'Maybank',
    'cimb' => 'CIMB Bank',
    'public' => 'Public Bank',
    'rhb' => 'RHB Bank',
    'hongleong' => 'Hong Leong Bank',
    'ambank' => 'AmBank',
    'uob' => 'UOB Malaysia',
    'hsbc' => 'HSBC Malaysia',
    'standard' => 'Standard Chartered',
    'bankislam' => 'Bank Islam',
    'bankrakyat' => 'Bank Rakyat',
    'affin' => 'Affin Bank'
];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Make Payment - CampusCar</title>
    <link rel="stylesheet" href="../css/userdashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/payment.css?v=<?php echo time(); ?>">
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
                    <li class="nav-item active">
                        <a href="payment.php?booking_id=<?php echo $booking_id; ?>" class="nav-link">
                            <i class="fa-solid fa-credit-card"></i>
                            <span>Make Payment</span>
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
                <div class="payment-container">
                    <!-- Page Header -->
                    <div class="page-header">
                        <h1><i class="fa-solid fa-credit-card"></i> Make Payment</h1>
                        <p>Complete your payment for the ride booking</p>
                    </div>

                    <?php if ($existing_payment && $existing_payment['PaymentStatus'] == 'paid'): ?>
                        <div class="payment-completed">
                            <div class="success-icon">
                                <i class="fa-solid fa-check-circle"></i>
                            </div>
                            <h3>Payment Already Completed!</h3>
                            <p>Your payment has been processed successfully.</p>
                            <div class="payment-details">
                                <p><strong>Transaction ID:</strong> <?php echo htmlspecialchars($existing_payment['TransactionID'] ?? 'N/A'); ?></p>
                                <p><strong>Payment Date:</strong> <?php echo date('F j, Y g:i A', strtotime($existing_payment['PaymentDate'])); ?></p>
                                <p><strong>Payment Method:</strong> <?php echo ucfirst($existing_payment['PaymentMethod']); ?></p>
                            </div>
                            <div class="action-buttons">
                                <a href="mybookings.php" class="btn btn-primary">
                                    <i class="fa-solid fa-ticket"></i>
                                    View My Bookings
                                </a>
                                <a href="todaysride.php" class="btn btn-outline">
                                    <i class="fa-solid fa-calendar-day"></i>
                                    Today's Rides
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="payment-wrapper">
                            <!-- Left Column: Booking Details -->
                            <div class="payment-left">
                                <div class="booking-summary">
                                    <h3><i class="fa-solid fa-receipt"></i> Booking Summary</h3>
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
                                            <span class="label">Car Model:</span>
                                            <span class="value"><?php echo htmlspecialchars($booking['CarModel']); ?></span>
                                        </div>
                                        <div class="summary-item">
                                            <span class="label">Seats Booked:</span>
                                            <span class="value"><?php echo $booking['NoOfSeats']; ?></span>
                                        </div>
                                        <div class="summary-item">
                                            <span class="label">Price per Seat:</span>
                                            <span class="value">RM<?php echo number_format($booking['PricePerSeat'], 2); ?></span>
                                        </div>
                                        <div class="summary-item total">
                                            <span class="label">Total Amount:</span>
                                            <span class="value">RM<?php echo number_format($booking['TotalPrice'], 2); ?></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Driver's Payment Information -->
                                <div class="driver-payment-info">
                                    <h3><i class="fa-solid fa-user-tie"></i> Driver's Payment Details</h3>

                                    <?php if ($booking['PaymentQRCode']): ?>
                                        <div class="qr-code-section">
                                            <h4><i class="fa-solid fa-qrcode"></i> QR Code Payment</h4>
                                            <div class="qr-code-container">
                                                <img src="../<?php echo htmlspecialchars($booking['PaymentQRCode']); ?>"
                                                    alt="QR Code for payment"
                                                    class="qr-code-image">
                                                <p class="qr-note">Scan this QR code to make payment</p>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($booking['BankName'] && $booking['AccountNumber'] && $booking['AccountName']): ?>
                                        <div class="bank-details-section">
                                            <h4><i class="fa-solid fa-university"></i> Bank Transfer Details</h4>
                                            <div class="bank-details">
                                                <div class="bank-detail-item">
                                                    <span class="label">Bank Name:</span>
                                                    <span class="value"><?php echo htmlspecialchars($booking['BankName']); ?></span>
                                                </div>
                                                <div class="bank-detail-item">
                                                    <span class="label">Account Number:</span>
                                                    <span class="value"><?php echo htmlspecialchars($booking['AccountNumber']); ?></span>
                                                </div>
                                                <div class="bank-detail-item">
                                                    <span class="label">Account Name:</span>
                                                    <span class="value"><?php echo htmlspecialchars($booking['AccountName']); ?></span>
                                                </div>
                                            </div>
                                            <p class="bank-note">Use this information for bank transfers</p>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!$booking['PaymentQRCode'] && !$booking['BankName']): ?>
                                        <div class="no-payment-info">
                                            <i class="fa-solid fa-info-circle"></i>
                                            <p>The driver hasn't provided payment details. Please contact them directly.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Right Column: Payment Form -->
                            <div class="payment-right">
                                <div class="payment-form-container">
                                    <h3><i class="fa-solid fa-wallet"></i> Select Payment Method</h3>

                                    <form id="paymentForm" action="../database/paymentdb.php" method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
                                        <input type="hidden" name="amount" value="<?php echo $booking['TotalPrice']; ?>">

                                        <!-- Payment Method Selection -->
                                        <div class="payment-methods">
                                            <div class="payment-method-option">
                                                <input type="radio" id="cash" name="payment_method" value="cash" checked>
                                                <label for="cash">
                                                    <i class="fa-solid fa-money-bill-wave"></i>
                                                    <div class="method-info">
                                                        <span class="method-name">Cash</span>
                                                        <span class="method-desc">Pay cash directly to driver</span>
                                                    </div>
                                                </label>
                                            </div>

                                            <div class="payment-method-option">
                                                <input type="radio" id="online_banking" name="payment_method" value="online_banking">
                                                <label for="online_banking">
                                                    <i class="fa-solid fa-university"></i>
                                                    <div class="method-info">
                                                        <span class="method-name">Online Banking</span>
                                                        <span class="method-desc">Transfer via online banking</span>
                                                    </div>
                                                </label>
                                            </div>

                                            <div class="payment-method-option">
                                                <input type="radio" id="qr" name="payment_method" value="qr">
                                                <label for="qr">
                                                    <i class="fa-solid fa-qrcode"></i>
                                                    <div class="method-info">
                                                        <span class="method-name">QR Code</span>
                                                        <span class="method-desc">Scan & pay via QR code</span>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>

                                        <!-- Online Banking Details (Hidden by default) -->
                                        <div id="onlineBankingDetails" class="payment-details-section" style="display: none;">
                                            <h4><i class="fa-solid fa-bank"></i> Select Your Bank</h4>
                                            <div class="form-group">
                                                <label for="bank_name">Bank</label>
                                                <select id="bank_name" name="bank_name" class="form-control">
                                                    <option value="">-- Select Bank --</option>
                                                    <?php foreach ($malaysian_banks as $key => $bank): ?>
                                                        <option value="<?php echo $key; ?>"><?php echo $bank; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <!-- Change these lines in the online banking section -->
                                            <div class="form-group">
                                                <label for="transaction_id">Transaction ID/Reference (optional)</label>
                                                <input type="text" id="transaction_id" name="transaction_id"
                                                    class="form-control" placeholder="Enter transaction reference">
                                            </div>
                                            <div class="form-group">
                                                <label for="proof">Upload Proof of Payment</label>
                                                <input type="file" id="proof" name="proof"
                                                    class="form-control" accept="image/*,.pdf" required>
                                                <small class="form-text">Upload screenshot or receipt (JPG, PNG, PDF)</small>
                                            </div>
                                        </div>

                                        <!-- QR Payment Details (Hidden by default) -->
                                        <div id="qrPaymentDetails" class="payment-details-section" style="display: none;">
                                            <h4><i class="fa-solid fa-qrcode"></i> QR Payment Details</h4>
                                            <div class="form-group">
                                                <label for="qr_transaction_id">Transaction ID/Reference (optional)</label>
                                                <input type="text" id="qr_transaction_id" name="qr_transaction_id"
                                                    class="form-control" placeholder="Enter transaction reference">
                                            </div>
                                            <div class="form-group">
                                                <label for="qr_proof">Upload Proof of Payment</label>
                                                <input type="file" id="qr_proof" name="qr_proof"
                                                    class="form-control" accept="image/*,.pdf">
                                                <small class="form-text">Upload screenshot of successful payment</small>
                                            </div>
                                        </div>

                                        <!-- Cash Payment Details (Hidden by default) -->
                                        <div id="cashPaymentDetails" class="payment-details-section">
                                            <h4><i class="fa-solid fa-money-check"></i> Cash Payment Instructions</h4>
                                            <div class="alert alert-info">
                                                <i class="fa-solid fa-info-circle"></i>
                                                <p>Please pay RM<?php echo number_format($booking['TotalPrice'], 2); ?> in cash directly to the driver.</p>
                                            </div>
                                            <div class="form-group">
                                                <label for="cash_received_by">Received By (Driver's Name)</label>
                                                <input type="text" id="cash_received_by" name="cash_received_by"
                                                    class="form-control" value="<?php echo htmlspecialchars($booking['DriverName']); ?>" readonly>
                                            </div>
                                        </div>

                                        <!-- Terms and Conditions -->
                                        <div class="terms-section">
                                            <div class="form-check">
                                                <input type="checkbox" id="terms" name="terms" required>
                                                <label for="terms">I confirm that I have made the payment as selected above</label>
                                            </div>
                                        </div>

                                        <!-- Submit Button -->
                                        <!-- Submit Button -->
                                        <div class="form-submit">
                                            <button type="button" id="manualPaymentSubmit" class="btn btn-primary btn-payment-submit">
                                                <i class="fa-solid fa-check-circle"></i>
                                                Complete Payment
                                            </button>
                                            <a href="todaysride.php" class="btn btn-outline">
                                                <i class="fa-solid fa-times"></i>
                                                Cancel
                                            </a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Loading Modal -->
    <div id="loadingModal" class="modal">
        <div class="modal-content loading-modal">
            <div class="loading-content">
                <div class="loading-spinner">
                    <i class="fa-solid fa-spinner fa-spin"></i>
                </div>
                <h3>Processing Payment...</h3>
                <p>Please wait while we process your payment.</p>
                <p class="loading-note">This may take a few seconds.</p>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div id="successModal" class="modal">
        <div class="modal-content success-modal">
            <div class="success-content">
                <div class="success-icon">
                    <i class="fa-solid fa-check-circle"></i>
                </div>
                <h3>Payment Successful!</h3>
                <p>Your payment has been processed successfully.</p>
                <div class="success-details">
                    <p><strong>Booking ID:</strong> #<?php echo str_pad($booking['BookingID'], 6, '0', STR_PAD_LEFT); ?></p>
                    <p><strong>Amount:</strong> RM<?php echo number_format($booking['TotalPrice'], 2); ?></p>
                    <p class="success-message">Thank you for using CampusCar!</p>
                </div>
                <div class="success-actions">
                    <a href="mybookings.php" class="btn btn-primary">
                        <i class="fa-solid fa-ticket"></i>
                        View My Bookings
                    </a>
                    <a href="todaysride.php" class="btn btn-outline">
                        <i class="fa-solid fa-calendar-day"></i>
                        Today's Rides
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="../js/userdashboard.js?v=<?php echo time(); ?>"></script>
    <script src="../js/payment.js?v=<?php echo time(); ?>"></script>

    <script>
        // Bank redirect mapping and handlers
        document.addEventListener('DOMContentLoaded', function() {
            const bankUrls = {
                'maybank': 'https://www.maybank2u.com.my/',
                'cimb': 'https://www.cimbclicks.com.my/',
                'public': 'https://www.publicbankgroup.com/',
                'rhb': 'https://www.rhbgroup.com/',
                'hongleong': 'https://www.hlisb.com.my/',
                'ambank': 'https://www.ambank.com.my/',
                'uob': 'https://www.uob.com.my/',
                'hsbc': 'https://www.hsbc.com.my/',
                'standard': 'https://www.sc.com/my/',
                'bankislam': 'https://www.bankislam.com.my/',
                'bankrakyat': 'https://www.bankrakyat.com.my/',
                'affin': 'https://www.affinbank.com.my/'
            };

            const bankSelect = document.getElementById('bank_name');
            const onlineBankRadio = document.getElementById('online_banking');

            function tryRedirectToBank(bankKey) {
                if (!bankKey || !bankUrls[bankKey]) return;
                const bankName = bankSelect.options[bankSelect.selectedIndex].text || bankKey;
                const url = bankUrls[bankKey];
                // Ask user before redirecting
                const proceed = confirm('You will be redirected to ' + bankName + "'s online banking site. Continue?");
                if (proceed) {
                    // Open bank login in a new tab to keep the app open for user
                    window.open(url, '_blank');
                }
            }

            if (bankSelect) {
                bankSelect.addEventListener('change', function() {
                    // Only redirect when Online Banking method is selected
                    if (onlineBankRadio && onlineBankRadio.checked) {
                        tryRedirectToBank(this.value);
                    }
                });
            }

            // If user switches to Online Banking and a bank is already selected, prompt redirect
            if (onlineBankRadio) {
                onlineBankRadio.addEventListener('change', function() {
                    if (this.checked && bankSelect && bankSelect.value) {
                        tryRedirectToBank(bankSelect.value);
                    }
                });
            }

            // Keep existing payment button behavior intact from payment.js; this is only an enhancement
        });
    </script>
</body>

</html>