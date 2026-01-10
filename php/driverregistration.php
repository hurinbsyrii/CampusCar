<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if user is already a driver
$servername = "127.0.0.1:3301";
$username = "root";
$password = "";
$dbname = "campuscar";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];
$is_driver = false;
$driver_status = '';
$driver_check_sql = "SELECT * FROM driver WHERE UserID = ?";
$stmt = $conn->prepare($driver_check_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $is_driver = true;
    $driver_data = $result->fetch_assoc();
    $driver_status = $driver_data['Status'];
}
$stmt->close();

if ($is_driver && $driver_status === 'approved') {
    header("Location: userdashboard.php");
    exit();
}

// If pending, show pending message
if ($is_driver && $driver_status === 'pending') {
    $pending_message = "Your driver registration is pending approval from admin.";
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Registration - CampusCar</title>
    <link rel="stylesheet" href="../css/driverregistration.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
    <div class="registration-container">
        <div class="registration-card">
            <div class="registration-header">
                <div class="logo">
                    <i class="fa-solid fa-car-side"></i>
                    <span>CampusCar</span>
                </div>
                <h1>Become a Driver</h1>
                <p>Join our community of student drivers and start earning</p>

                <?php if (isset($pending_message)): ?>
                    <div class="pending-notice">
                        <i class="fa-solid fa-clock"></i>
                        <p><?php echo $pending_message; ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!$is_driver || $driver_status !== 'pending'): ?>
                <form id="driverRegistrationForm" action="../database/driverregistrationdb.php" method="POST" enctype="multipart/form-data" class="registration-form">
                    <div class="form-group">
                        <label for="licenseNumber">
                            <i class="fa-solid fa-id-card"></i>
                            Driver's License Number *
                        </label>
                        <div class="input-group">
                            <input type="text" id="licenseNumber" name="licenseNumber" placeholder="Enter your license number" required>
                        </div>
                        <small class="help-text">Enter your valid Malaysian driving license number</small>
                        <small class="error-text" id="licenseNumberError"></small>
                    </div>

                    <div class="form-group">
                        <label for="carModel">
                            <i class="fa-solid fa-car"></i>
                            Car Model *
                        </label>
                        <div class="input-group">
                            <input type="text" id="carModel" name="carModel" placeholder="e.g., Proton Saga, Perodua Myvi" required>
                        </div>
                        <small class="help-text">Enter the model of your vehicle</small>
                        <small class="error-text" id="carModelError"></small>
                    </div>

                    <div class="form-group">
                        <label for="carPlateNumber">
                            <i class="fa-solid fa-car"></i>
                            Car Plate Number *
                        </label>
                        <div class="input-group">
                            <input type="text" id="carPlateNumber" name="carPlateNumber" placeholder="e.g., ABC1234" required>
                        </div>
                        <small class="help-text">Enter your vehicle's license plate number</small>
                        <small class="error-text" id="carPlateNumberError"></small>
                    </div>

                    <!-- Bank Details Section -->
                    <div class="form-section">
                        <h3><i class="fa-solid fa-university"></i> Bank Details for Payment</h3>

                        <div class="form-group">
                            <label for="bankName">
                                <i class="fa-solid fa-building-columns"></i>
                                Bank Name *
                            </label>
                            <div class="input-group">
                                <select id="bankName" name="bankName" required>
                                    <option value="">Select Bank</option>
                                    <option value="Maybank">Maybank</option>
                                    <option value="CIMB">CIMB Bank</option>
                                    <option value="Public Bank">Public Bank</option>
                                    <option value="RHB">RHB Bank</option>
                                    <option value="Hong Leong">Hong Leong Bank</option>
                                    <option value="Bank Islam">Bank Islam</option>
                                    <option value="Bank Rakyat">Bank Rakyat</option>
                                    <option value="BSN">BSN</option>
                                    <!-- <option value="Other">Other</option> -->
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="accountNumber">
                                <i class="fa-solid fa-credit-card"></i>
                                Account Number *
                            </label>
                            <div class="input-group">
                                <input
                                    type="text"
                                    id="accountNumber"
                                    name="accountNumber"
                                    placeholder="e.g., 1234567890"
                                    maxlength="20"
                                    minlength="10"
                                    inputmode="numeric"
                                    oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                    required>
                            </div>
                            <small class="help-text">Enter your bank account number (10-20 digits)</small>
                            <small class="error-text" id="accountNumberError"></small>
                        </div>

                        <div class="form-group">
                            <label for="accountName">
                                <i class="fa-solid fa-user-tie"></i>
                                Account Holder Name *
                            </label>
                            <div class="input-group">
                                <input type="text" id="accountName" name="accountName" placeholder="e.g., AHMAD BIN ALI" required>
                            </div>
                            <small class="help-text">Name as it appears on bank account</small>
                            <small class="error-text" id="accountNameError"></small>
                        </div>

                        <div class="form-group">
                            <label for="paymentQR">
                                <i class="fa-solid fa-qrcode"></i>
                                Payment QR Code (Optional)
                            </label>
                            <div class="input-group">
                                <input type="file" id="paymentQR" name="paymentQR" accept="image/*">
                            </div>
                            <small class="help-text">Upload your DuitNow/FPX QR code (Max 2MB, JPG/PNG)</small>
                            <small class="error-text" id="paymentQRError"></small>
                        </div>
                    </div>

                    <div class="benefits-section">
                        <h3><i class="fa-solid fa-star"></i> Driver Benefits</h3>
                        <div class="benefits-grid">
                            <div class="benefit-item">
                                <i class="fa-solid fa-money-bill-wave"></i>
                                <span>Earn extra income</span>
                            </div>
                            <div class="benefit-item">
                                <i class="fa-solid fa-users"></i>
                                <span>Connect with students</span>
                            </div>
                            <div class="benefit-item">
                                <i class="fa-solid fa-clock"></i>
                                <span>Flexible schedule</span>
                            </div>
                            <div class="benefit-item">
                                <i class="fa-solid fa-gas-pump"></i>
                                <span>Fuel cost sharing</span>
                            </div>
                        </div>
                    </div>

                    <div class="agreement-section">
                        <div class="form-check">
                            <input type="checkbox" id="agreeTerms" name="agreeTerms" required>
                            <label for="agreeTerms">
                                I agree to the <a href="#" class="link">Terms of Service</a>,
                                <a href="#" class="link">Driver Policy</a>, and confirm that all
                                information provided is accurate.
                            </label>
                        </div>
                        <small class="help-text">Note: Your registration will be reviewed by admin within 24-48 hours</small>
                    </div>

                    <div class="form-actions">
                        <button type="button" onclick="goBack()" class="btn-secondary">
                            <i class="fa-solid fa-arrow-left"></i>
                            Back to Dashboard
                        </button>
                        <button type="submit" class="btn-primary" id="submitBtn">
                            <i class="fa-solid fa-user-plus"></i>
                            Submit Registration
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <div class="registration-info">
            <div class="info-card">
                <h3><i class="fa-solid fa-shield-check"></i> Driver Requirements</h3>
                <ul class="requirements-list">
                    <li>
                        <i class="fa-solid fa-check"></i>
                        <span>Valid Malaysian driving license</span>
                    </li>
                    <li>
                        <i class="fa-solid fa-check"></i>
                        <span>Vehicle in good condition</span>
                    </li>
                    <li>
                        <i class="fa-solid fa-check"></i>
                        <span>Comprehensive insurance</span>
                    </li>
                    <li>
                        <i class="fa-solid fa-check"></i>
                        <span>Clean driving record</span>
                    </li>
                    <li>
                        <i class="fa-solid fa-check"></i>
                        <span>Student verification</span>
                    </li>
                    <li>
                        <i class="fa-solid fa-check"></i>
                        <span>Valid bank account for payments</span>
                    </li>
                </ul>
            </div>

            <div class="stats-card">
                <h4><i class="fa-solid fa-chart-line"></i> Driver Statistics</h4>
                <div class="stats-grid">
                    <div class="stat-item">
                        <i class="fa-solid fa-coins"></i>
                        <div class="stat-info">
                            <span class="stat-number">RM 200+</span>
                            <span class="stat-label">Avg. Monthly Earnings</span>
                        </div>
                    </div>
                    <div class="stat-item">
                        <i class="fa-solid fa-user-friends"></i>
                        <div class="stat-info">
                            <span class="stat-number">150+</span>
                            <span class="stat-label">Active Drivers</span>
                        </div>
                    </div>
                    <div class="stat-item">
                        <i class="fa-solid fa-clock"></i>
                        <div class="stat-info">
                            <span class="stat-number">24-48h</span>
                            <span class="stat-label">Approval Time</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="support-card">
                <h4><i class="fa-solid fa-headset"></i> Need Help?</h4>
                <p>Our support team is here to assist you with the registration process.</p>
                <div class="contact-info">
                    <p><i class="fa-solid fa-phone"></i> 06-123 4567</p>
                    <p><i class="fa-solid fa-envelope"></i> campuscar.team@gmail.com</p>
                </div>
            </div>
        </div>
    </div>

    <script src="../js/driverregistration.js?v=<?php echo time(); ?>"></script>
</body>

</html>