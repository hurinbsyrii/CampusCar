<?php
// Database operations for user profile management

function updateUserProfile($conn, $user_id, $phone_number)
{
    $sql = "UPDATE user SET PhoneNumber = ? WHERE UserID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $phone_number, $user_id);
    return $stmt->execute();
}

function updateDriverProfile($conn, $user_id, $car_model, $car_plate_number)
{
    $sql = "UPDATE driver SET CarModel = ?, CarPlateNumber = ? WHERE UserID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $car_model, $car_plate_number, $user_id);
    return $stmt->execute();
}

function updateDriverPaymentInfo($conn, $user_id, $car_model, $car_plate_number, $payment_qr_code, $bank_name, $account_number, $account_name)
{
    $sql = "UPDATE driver SET 
            CarModel = ?, 
            CarPlateNumber = ?, 
            PaymentQRCode = ?,
            BankName = ?,
            AccountNumber = ?,
            AccountName = ? 
            WHERE UserID = ?";

    $stmt = $conn->prepare($sql);
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

    return $stmt->execute();
}

function getUserProfile($conn, $user_id)
{
    $sql = "SELECT * FROM user WHERE UserID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function getDriverProfile($conn, $user_id)
{
    $sql = "SELECT * FROM driver WHERE UserID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0 ? $result->fetch_assoc() : null;
}

function uploadQRCode($file, $user_id)
{
    $upload_dir = "../uploads/qrcodes/";

    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Generate unique filename
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $file_name = 'qr_' . $user_id . '_' . time() . '.' . $file_extension;
    $file_path = $upload_dir . $file_name;

    // Validate file type
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $file_type = mime_content_type($file['tmp_name']);

    if (!in_array($file_type, $allowed_types)) {
        return ['success' => false, 'error' => 'Invalid file type. Only images are allowed.'];
    }

    // Validate file size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'error' => 'File size exceeds 5MB limit.'];
    }

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        return ['success' => true, 'path' => 'uploads/qrcodes/' . $file_name];
    }

    return ['success' => false, 'error' => 'Failed to upload file.'];
}

function deleteOldQRCode($file_path)
{
    if ($file_path && file_exists('../' . $file_path)) {
        return unlink('../' . $file_path);
    }
    return true;
}

function isDriver($conn, $user_id)
{
    $sql = "SELECT COUNT(*) as count FROM driver WHERE UserID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['count'] > 0;
}

function getDriverStatus($conn, $user_id)
{
    $sql = "SELECT Status FROM driver WHERE UserID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result ? $result['Status'] : null;
}
