<?php
session_start();
header('Content-Type: application/json');
date_default_timezone_set('Asia/Kuala_Lumpur'); // Set timezone Malaysia

// For debugging - remove in production
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Get POST data
$user_id = $_SESSION['user_id'];
$booking_id = $_POST['booking_id'] ?? null;
$amount = $_POST['amount'] ?? null;
$payment_method = $_POST['payment_method'] ?? 'cash';

// Basic validation
if (!$booking_id || !$amount) {
    echo json_encode(['success' => false, 'message' => 'Missing booking or amount']);
    exit();
}

// Database connection
$conn = new mysqli("127.0.0.1:3301", "root", "", "campuscar");

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
}

try {
    // 1. Verify booking exists and is valid
    $stmt = $conn->prepare("SELECT * FROM booking WHERE BookingID = ? AND UserID = ? AND BookingStatus IN ('Completed', 'Confirmed')");
    $stmt->bind_param("ii", $booking_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Invalid booking or already paid");
    }

    $booking = $result->fetch_assoc();
    $stmt->close();

    // 2. Check if payment already exists
    $stmt = $conn->prepare("SELECT * FROM payments WHERE BookingID = ? AND UserID = ?");
    $stmt->bind_param("ii", $booking_id, $user_id);
    $stmt->execute();
    $payment_result = $stmt->get_result();

    if ($payment_result->num_rows > 0) {
        $existing = $payment_result->fetch_assoc();
        if ($existing['PaymentStatus'] === 'paid') {
            throw new Exception("Payment already completed");
        }
    }
    $stmt->close();

    // 3. Prepare payment data
    $transaction_id = "CC" . date('YmdHis') . rand(1000, 9999);
    $proof_path = null;
    $bank_info = null;

    // --- LOGIC UPLOAD GAMBAR YANG DIBETULKAN (FIXED) ---
    $file_to_upload = null;

    // Tentukan input file mana nak guna berdasarkan method
    if ($payment_method === 'online_banking' && isset($_FILES['proof'])) {
        $file_to_upload = $_FILES['proof'];
        $bank_info = $_POST['bank_name'] ?? null;
    } elseif ($payment_method === 'qr' && isset($_FILES['qr_proof'])) {
        $file_to_upload = $_FILES['qr_proof'];
        // Bank info null untuk QR
    }

    // Proses upload jika ada file
    if ($file_to_upload && $file_to_upload['error'] === UPLOAD_ERR_OK) {
        $upload_dir = "../uploads/payment_proofs/";

        // Buat folder jika tiada
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $ext = strtolower(pathinfo($file_to_upload['name'], PATHINFO_EXTENSION));
        // Validasi extension mudah
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
            $filename = "proof_{$booking_id}_{$user_id}_" . time() . ".{$ext}";

            if (move_uploaded_file($file_to_upload['tmp_name'], $upload_dir . $filename)) {
                $proof_path = "uploads/payment_proofs/" . $filename;
            } else {
                throw new Exception("Failed to move uploaded file");
            }
        } else {
            throw new Exception("Invalid file type. Only JPG, PNG, PDF allowed.");
        }
    }
    // --- TAMAT FIX ---

    // 4. Insert payment record
    $stmt = $conn->prepare("INSERT INTO payments (BookingID, UserID, Amount, PaymentMethod, BankInfo, PaymentStatus, ProofPath, TransactionID, PaymentDate) VALUES (?, ?, ?, ?, ?, 'paid', ?, ?, NOW())");
    $stmt->bind_param("iidssss", $booking_id, $user_id, $amount, $payment_method, $bank_info, $proof_path, $transaction_id);

    if (!$stmt->execute()) {
        throw new Exception("Failed to save payment: " . $stmt->error);
    }

    $payment_id = $conn->insert_id;
    $stmt->close();

    // 5. Update booking status
    $stmt = $conn->prepare("UPDATE booking SET BookingStatus = 'Paid' WHERE BookingID = ?");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $stmt->close();

    // 6. Record driver earnings
    $stmt = $conn->prepare("SELECT r.DriverID FROM rides r JOIN booking b ON r.RideID = b.RideID WHERE b.BookingID = ?");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $ride_result = $stmt->get_result();

    if ($ride_result->num_rows > 0) {
        $ride = $ride_result->fetch_assoc();
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO driver_earnings (DriverID, RideID, BookingID, Amount, PaymentDate) VALUES (?, (SELECT RideID FROM booking WHERE BookingID = ?), ?, ?, NOW())");
        $stmt->bind_param("iiid", $ride['DriverID'], $booking_id, $booking_id, $amount);
        $stmt->execute();
        $stmt->close();
    }

    // Success response
    echo json_encode([
        'success' => true,
        'message' => 'Payment completed successfully!',
        'payment_id' => $payment_id,
        'booking_id' => $booking_id,
        'amount' => $amount,
        'transaction_id' => $transaction_id
    ]);
} catch (Exception $e) {
    error_log("Payment error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
