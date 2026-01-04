<?php
session_start();
header('Content-Type: application/json');

// For debugging - remove in production
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Quick check - is the script being called?
error_log("=== PAYMENT SCRIPT CALLED ===");
error_log("Time: " . date('Y-m-d H:i:s'));

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Get POST data
$user_id = $_SESSION['user_id'];
$booking_id = $_POST['booking_id'] ?? null;
$amount = $_POST['amount'] ?? null;
$payment_method = $_POST['payment_method'] ?? 'cash';

error_log("User: $user_id, Booking: $booking_id, Amount: $amount, Method: $payment_method");

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

    // Handle file upload if exists
    if ($payment_method === 'online_banking' && isset($_FILES['proof'])) {
        $file = $_FILES['proof'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $upload_dir = "../uploads/payment_proofs/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = "proof_{$booking_id}_{$user_id}_" . time() . ".{$ext}";
            move_uploaded_file($file['tmp_name'], $upload_dir . $filename);
            $proof_path = "uploads/payment_proofs/" . $filename;
            $bank_info = $_POST['bank_name'] ?? null;
        }
    }

    // 4. Insert payment record
    $stmt = $conn->prepare("INSERT INTO payments (BookingID, UserID, Amount, PaymentMethod, BankInfo, PaymentStatus, ProofPath, TransactionID, PaymentDate) VALUES (?, ?, ?, ?, ?, 'paid', ?, ?, NOW())");
    $stmt->bind_param("iidssss", $booking_id, $user_id, $amount, $payment_method, $bank_info, $proof_path, $transaction_id);

    if (!$stmt->execute()) {
        throw new Exception("Failed to save payment");
    }

    $payment_id = $conn->insert_id;
    $stmt->close();

    // 5. Update booking status
    $stmt = $conn->prepare("UPDATE booking SET BookingStatus = 'Paid' WHERE BookingID = ?");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $stmt->close();

    // 6. Record driver earnings if needed
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

    error_log("Payment successful for booking $booking_id");
} catch (Exception $e) {
    error_log("Payment error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
