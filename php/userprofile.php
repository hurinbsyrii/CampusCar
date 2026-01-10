<?php
session_start();
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

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';
$uploaded_file_name = '';

// Fetch user data
$user_sql = "SELECT * FROM user WHERE UserID = ?";
$stmt = $conn->prepare($user_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user_data = $user_result->fetch_assoc();
$stmt->close();

// Check if user is a driver and fetch driver data
$is_driver = false;
$driver_data = null;
$driver_pending_count = 0; // Initialize count

$driver_check_sql = "SELECT * FROM driver WHERE UserID = ?";
$stmt = $conn->prepare($driver_check_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$driver_result = $stmt->get_result();
if ($driver_result->num_rows > 0) {
    $is_driver = true;
    $driver_data = $driver_result->fetch_assoc();

    // NEW LOGIC: Count Pending Requests
    $pending_sql = "SELECT COUNT(*) as count FROM booking b 
                    JOIN rides r ON b.RideID = r.RideID 
                    WHERE r.DriverID = ? AND b.BookingStatus = 'Pending'";
    $p_stmt = $conn->prepare($pending_sql);
    $p_stmt->bind_param("i", $driver_data['DriverID']);
    $p_stmt->execute();
    $p_result = $p_stmt->get_result();
    $driver_pending_count = $p_result->fetch_assoc()['count'];
    $p_stmt->close();
}
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $phone_number = $_POST['phone_number'];

    // Update user phone number
    $update_user_sql = "UPDATE user SET PhoneNumber = ? WHERE UserID = ?";
    $stmt = $conn->prepare($update_user_sql);
    $stmt->bind_param("si", $phone_number, $user_id);

    if ($stmt->execute()) {
        $message = "Profile updated successfully!";
        $message_type = "success";
        $_SESSION['phone_number'] = $phone_number;
        $user_data['PhoneNumber'] = $phone_number;
    } else {
        $message = "Error updating profile: " . $conn->error;
        $message_type = "error";
    }
    $stmt->close();

    // If user is driver AND approved, update driver data
    // Tambah check status approved di sini juga untuk security backend
    if ($is_driver && isset($driver_data['Status']) && $driver_data['Status'] === 'approved' && isset($_POST['car_model']) && isset($_POST['car_plate_number'])) {
        $car_model = $_POST['car_model'];
        $car_plate_number = $_POST['car_plate_number'];
        $bank_name = $_POST['bank_name'] ?? null;
        $account_number = $_POST['account_number'] ?? null;
        $account_name = $_POST['account_name'] ?? null;

        // Handle QR code upload
        $payment_qr_code = $driver_data['PaymentQRCode'] ?? null;
        if (isset($_FILES['payment_qr_code']) && $_FILES['payment_qr_code']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['payment_qr_code']['tmp_name'];
            if (is_uploaded_file($tmp)) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($tmp);
                $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

                if (in_array($mime, $allowed)) {
                    $ext = pathinfo($_FILES['payment_qr_code']['name'], PATHINFO_EXTENSION);
                    $ext = preg_replace('/[^a-zA-Z0-9]/', '', $ext) ?: 'png';
                    $file_name = 'qr_' . $user_id . '_' . time() . '.' . $ext;
                    $uploaded_file_name = $_FILES['payment_qr_code']['name'];

                    $upload_dir = __DIR__ . '/../uploads/qrcodes/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    $dest_path = $upload_dir . $file_name;

                    if (move_uploaded_file($tmp, $dest_path)) {
                        $payment_qr_code = 'uploads/qrcodes/' . $file_name;
                        if (!empty($driver_data['PaymentQRCode'])) {
                            $old = __DIR__ . '/../' . $driver_data['PaymentQRCode'];
                            if (file_exists($old)) {
                                @unlink($old);
                            }
                        }
                        $message = ($message ? $message . " " : "") . "QR code uploaded successfully!";
                    } else {
                        $message = ($message ? $message . " " : "") . "Failed to save uploaded QR file.";
                        $message_type = "error";
                    }
                } else {
                    $message = ($message ? $message . " " : "") . "Invalid QR file type.";
                    $message_type = "error";
                }
            }
        }

        $update_driver_sql = "UPDATE driver SET 
            CarModel = ?, 
            CarPlateNumber = ?, 
            PaymentQRCode = ?,
            BankName = ?,
            AccountNumber = ?,
            AccountName = ? 
            WHERE UserID = ?";

        $stmt = $conn->prepare($update_driver_sql);
        $stmt->bind_param(
            "ssssssi",
            $car_model,
            $car_plate_number,
            $payment_qr_code,
            $bank_name,
            $account_number,
            $account_name,
            $user_id
        );

        if ($stmt->execute()) {
            $message = $message ? $message . " Driver information updated!" : "Driver information updated!";
            $message_type = "success";
            // Update driver data for display
            $driver_data['CarModel'] = $car_model;
            $driver_data['CarPlateNumber'] = $car_plate_number;
            $driver_data['PaymentQRCode'] = $payment_qr_code;
            $driver_data['BankName'] = $bank_name;
            $driver_data['AccountNumber'] = $account_number;
            $driver_data['AccountName'] = $account_name;
        } else {
            $message = "Error updating driver information: " . $conn->error;
            $message_type = "error";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - CampusCar</title>
    <link rel="stylesheet" href="../css/userdashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/userprofile.css?v=<?php echo time(); ?>">
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
                    <span class="user-role"><?php echo ($is_driver && $driver_data['Status'] === 'approved') ? 'Driver' : 'Passenger'; ?></span>
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
                    <li class="nav-item active">
                        <a href="userprofile.php" class="nav-link">
                            <i class="fa-solid fa-user"></i>
                            <span>My Profile</span>
                        </a>
                    </li>

                    <?php if ($is_driver && $driver_data['Status'] === 'approved'): ?>
                        <li class="nav-item">
                            <a href="driverdashboard.php" class="nav-link">
                                <i class="fa-solid fa-car-side"></i>
                                <span>Driver Dashboard</span>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if ($is_driver && $driver_data['Status'] === 'approved'): ?>
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
                        <a href="mybookings.php" class="nav-link">
                            <div class="nav-link-content">
                                <i class="fa-solid fa-ticket"></i>
                                <span>My Bookings</span>
                            </div>
                            <?php if ($is_driver && $driver_pending_count > 0): ?>
                                <span class="notification-dot" title="<?php echo $driver_pending_count; ?> new requests"></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
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
                        </div>
                        <a href="userprofile.php" class="profile-btn">
                            <i class="fa-solid fa-user"></i>
                            My Profile
                        </a>
                    </div>
                </div>
            </header>

            <main class="dashboard-main">
                <div class="profile-container">
                    <div class="profile-header">
                        <h1><i class="fa-solid fa-user"></i> My Profile</h1>
                        <p>Manage your personal information and preferences</p>
                    </div>

                    <?php if ($message): ?>
                        <div class="message <?php echo $message_type; ?>">
                            <i class="fa-solid fa-<?php echo $message_type == 'success' ? 'check' : 'exclamation'; ?>-circle"></i>
                            <span><?php echo $message; ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="profile-content">
                        <div class="profile-section">
                            <div class="section-header">
                                <h2><i class="fa-solid fa-id-card"></i> Personal Information</h2>
                                <span class="section-badge">Read Only</span>
                            </div>
                            <div class="info-grid">
                                <div class="info-item">
                                    <label>Full Name</label>
                                    <div class="read-only-field"><?php echo htmlspecialchars($user_data['FullName']); ?></div>
                                </div>
                                <div class="info-item">
                                    <label>Matric Number</label>
                                    <div class="read-only-field"><?php echo htmlspecialchars($user_data['MatricNo']); ?></div>
                                </div>
                                <div class="info-item">
                                    <label>IC Number</label>
                                    <div class="read-only-field"><?php echo htmlspecialchars($user_data['ICNo']); ?></div>
                                </div>
                                <div class="info-item">
                                    <label>Username</label>
                                    <div class="read-only-field"><?php echo htmlspecialchars($user_data['Username']); ?></div>
                                </div>
                                <div class="info-item">
                                    <label>Gender</label>
                                    <div class="read-only-field"><?php echo htmlspecialchars($user_data['Gender']); ?></div>
                                </div>
                                <div class="info-item">
                                    <label>Faculty</label>
                                    <div class="read-only-field"><?php echo htmlspecialchars($user_data['Faculty']); ?></div>
                                </div>
                            </div>
                        </div>

                        <form method="POST" class="profile-section" enctype="multipart/form-data" id="profileForm">
                            <div class="section-header">
                                <h2><i class="fa-solid fa-edit"></i> Editable Information</h2>
                                <span class="section-badge editable">Editable</span>
                            </div>
                            <div class="info-grid">
                                <div class="info-item">
                                    <label for="phone_number">Phone Number</label>
                                    <input type="text" id="phone_number" name="phone_number"
                                        value="<?php echo htmlspecialchars($user_data['PhoneNumber']); ?>"
                                        required pattern="[0-9]{10,11}"
                                        title="Please enter a valid phone number (10-11 digits)">
                                    <small class="field-note">Enter your phone number (10-11 digits)</small>
                                </div>
                            </div>

                            <?php if ($is_driver && $driver_data && $driver_data['Status'] === 'approved'): ?>
                                <div class="driver-section">
                                    <div class="section-header">
                                        <h2><i class="fa-solid fa-car"></i> Driver Information</h2>
                                        <span class="section-badge editable">Editable</span>
                                    </div>
                                    <div class="info-grid">
                                        <div class="info-item">
                                            <label for="car_model">Car Model</label>
                                            <input type="text" id="car_model" name="car_model"
                                                value="<?php echo htmlspecialchars($driver_data['CarModel']); ?>"
                                                required>
                                            <small class="field-note">Enter your car model</small>
                                        </div>
                                        <div class="info-item">
                                            <label for="car_plate_number">Car Plate Number</label>
                                            <input type="text" id="car_plate_number" name="car_plate_number"
                                                value="<?php echo htmlspecialchars($driver_data['CarPlateNumber']); ?>"
                                                required>
                                            <small class="field-note">Enter your car plate number</small>
                                        </div>
                                        <div class="info-item">
                                            <label>License Number</label>
                                            <div class="read-only-field"><?php echo htmlspecialchars($driver_data['LicenseNumber']); ?></div>
                                            <small class="field-note">License number cannot be changed</small>
                                        </div>
                                    </div>

                                    <div class="payment-section">
                                        <div class="section-header">
                                            <h3><i class="fa-solid fa-credit-card"></i> Payment Information</h3>
                                            <span class="section-badge editable">Editable</span>
                                        </div>

                                        <div class="info-grid">
                                            <div class="info-item">
                                                <label for="bank_name">Bank Name</label>
                                                <input type="text" id="bank_name" name="bank_name"
                                                    value="<?php echo htmlspecialchars($driver_data['BankName'] ?? ''); ?>"
                                                    placeholder="e.g., Maybank, CIMB, Public Bank">
                                                <small class="field-note">Enter your bank name</small>
                                            </div>

                                            <div class="info-item">
                                                <label for="account_number">Account Number</label>
                                                <input type="text" id="account_number" name="account_number"
                                                    value="<?php echo htmlspecialchars($driver_data['AccountNumber'] ?? ''); ?>"
                                                    placeholder="e.g., 1234567890">
                                                <small class="field-note">Enter your bank account number</small>
                                            </div>

                                            <div class="info-item">
                                                <label for="account_name">Account Name</label>
                                                <input type="text" id="account_name" name="account_name"
                                                    value="<?php echo htmlspecialchars($driver_data['AccountName'] ?? ''); ?>"
                                                    placeholder="e.g., JOHN DOE">
                                                <small class="field-note">Enter account holder name</small>
                                            </div>

                                            <div class="info-item full-width">
                                                <label for="payment_qr_code">Payment QR Code</label>
                                                <div class="qr-upload-container">
                                                    <?php if (!empty($driver_data['PaymentQRCode'])): ?>
                                                        <div class="current-qr" id="currentQRContainer">
                                                            <p>Current QR Code:</p>
                                                            <img src="../<?php echo htmlspecialchars($driver_data['PaymentQRCode']); ?>"
                                                                alt="Payment QR Code" class="qr-preview" id="currentQRImage">
                                                            <div class="qr-file-info">
                                                                <span class="file-name"><?php echo basename($driver_data['PaymentQRCode']); ?></span>
                                                                <a href="../<?php echo htmlspecialchars($driver_data['PaymentQRCode']); ?>"
                                                                    target="_blank" class="view-qr-link">
                                                                    <i class="fa-solid fa-expand"></i> View Full Size
                                                                </a>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>

                                                    <div class="qr-upload-box" id="qrUploadBox">
                                                        <div id="uploadArea" class="upload-area" role="button" tabindex="0">
                                                            <i class="fa-solid fa-cloud-upload-alt"></i>
                                                            <p id="uploadText"><?php echo empty($driver_data['PaymentQRCode']) ? 'Click or drag & drop to upload QR Code' : 'Click or drag & drop to change QR Code'; ?></p>
                                                            <span class="upload-note">Supported: JPEG, PNG, GIF, WebP — Max 5MB</span>
                                                        </div>
                                                        <input type="file" id="payment_qr_code" name="payment_qr_code"
                                                            accept="image/*" class="qr-file-input" style="display: none;">
                                                        <div id="selectedFileInfo" class="selected-file-info" style="display: none;">
                                                            <div class="selected-file-content">
                                                                <i class="fa-solid fa-file-image"></i>
                                                                <div class="selected-file-details">
                                                                    <span id="selectedFileName" class="selected-file-name"></span>
                                                                    <span id="selectedFileSize" class="selected-file-size"></span>
                                                                </div>
                                                                <button type="button" id="removeSelectedFile" class="remove-selected-file-btn">
                                                                    <i class="fa-solid fa-times"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <div id="qrLivePreview" class="qr-live-preview"></div>
                                                        <div id="previewHint" class="preview-hint" style="display: none;">
                                                            <i class="fa-solid fa-eye"></i>
                                                            <span>New QR code will replace the current one after saving</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="form-actions">
                                <button type="submit" class="btn-save" id="saveBtn">
                                    <i class="fa-solid fa-floppy-disk"></i>
                                    Save Changes
                                </button>
                                <button type="button" class="btn-cancel" onclick="window.location.href='userdashboard.php'">
                                    <i class="fa-solid fa-arrow-left"></i>
                                    Back to Dashboard
                                </button>
                            </div>
                        </form>

                        <div class="profile-section">
                            <div class="section-header">
                                <h2><i class="fa-solid fa-shield-alt"></i> Account Status</h2>
                            </div>
                            <div class="status-grid">
                                <div class="status-item <?php echo $is_driver ? 'status-active' : 'status-inactive'; ?>">
                                    <div class="status-icon">
                                        <i class="fa-solid fa-id-card"></i>
                                    </div>
                                    <div class="status-info">
                                        <h4>Driver Status</h4>
                                        <p><?php echo $is_driver ? 'Registered Application' : 'Not Registered as Driver'; ?></p>
                                        <?php if ($is_driver && $driver_data): ?>
                                            <p class="driver-details">
                                                <small>Status:
                                                    <span class="status-badge <?php echo strtolower($driver_data['Status']); ?>">
                                                        <?php echo ucfirst($driver_data['Status']); ?>
                                                    </span>
                                                </small>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!$is_driver): ?>
                                        <a href="driverregistration.php" class="btn-register">
                                            <i class="fa-solid fa-user-plus"></i>
                                            Register as Driver
                                        </a>
                                    <?php else: ?>
                                        <span class="badge-success">Registered</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="../js/userdashboard.js?v=<?php echo time(); ?>"></script>
    <script src="../js/userprofile.js?v=<?php echo time(); ?>"></script>
</body>

</html>
<?php $conn->close(); ?>