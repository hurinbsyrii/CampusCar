<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../php/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $servername = "127.0.0.1:3301";
    $username = "root";
    $password = "";
    $dbname = "campuscar";

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $user_id = $_SESSION['user_id'];
    $license_number = $conn->real_escape_string($_POST['licenseNumber']);
    $car_model = $conn->real_escape_string($_POST['carModel']);
    $car_plate_number = $conn->real_escape_string($_POST['carPlateNumber']);
    $bank_name = $conn->real_escape_string($_POST['bankName']);
    $account_number = $conn->real_escape_string($_POST['accountNumber']);
    $account_name = $conn->real_escape_string($_POST['accountName']);
    
    // Handle QR code upload
    $qr_code_path = null;
    if (isset($_FILES['paymentQR']) && $_FILES['paymentQR']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = "../uploads/qrcodes/";
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_ext = pathinfo($_FILES['paymentQR']['name'], PATHINFO_EXTENSION);
        $file_name = "qr_" . $user_id . "_" . time() . "." . $file_ext;
        $target_path = $upload_dir . $file_name;
        
        // Validate file type
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
        $file_type = $_FILES['paymentQR']['type'];
        
        if (in_array($file_type, $allowed_types)) {
            if (move_uploaded_file($_FILES['paymentQR']['tmp_name'], $target_path)) {
                $qr_code_path = "uploads/qrcodes/" . $file_name;
            }
        }
    }

    // Check if user is already a driver
    $check_sql = "SELECT * FROM driver WHERE UserID = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $driver_data = $result->fetch_assoc();
        if ($driver_data['Status'] === 'pending') {
            $_SESSION['warning'] = "Your driver registration is still pending approval";
        } else if ($driver_data['Status'] === 'approved') {
            $_SESSION['error'] = "You are already a registered driver";
        }
        header("Location: ../php/userdashboard.php");
        exit();
    }
    $stmt->close();

    // Insert new driver with pending status
    $insert_sql = "INSERT INTO driver (UserID, LicenseNumber, CarModel, CarPlateNumber, 
                    BankName, AccountNumber, AccountName, PaymentQRCode, Status, RegistrationDate) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
    
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("isssssss", $user_id, $license_number, $car_model, 
                     $car_plate_number, $bank_name, $account_number, 
                     $account_name, $qr_code_path);

    if ($stmt->execute()) {
        // Create notification for admin
        $notification_sql = "INSERT INTO notifications (UserID, Title, Message, Type, CreatedAt, RelatedID, RelatedType) 
                             VALUES (3, 'New Driver Registration', 
                             'New driver registration from user ID: $user_id needs approval.', 
                             'info', NOW(), $user_id, 'driver_registration')";
        $conn->query($notification_sql);
        
        $_SESSION['success'] = "Driver registration submitted successfully! It will be reviewed by admin within 24-48 hours.";
        header("Location: ../php/userdashboard.php");
    } else {
        $_SESSION['error'] = "Error registering as driver: " . $conn->error;
        header("Location: ../php/driverregistration.php");
    }

    $stmt->close();
    $conn->close();
} else {
    header("Location: ../php/driverregistration.php");
    exit();
}
?>