<?php
session_start();

// 1. Check login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit();
}

// 2. Database connection
$servername = "127.0.0.1:3301";
$username = "root";
$password = "";
$dbname = "campuscar";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 3. Update semua notifikasi user ini kepada IsRead = 1
$user_id = $_SESSION['user_id'];
$sql = "UPDATE notifications SET IsRead = 1 WHERE UserID = ? AND IsRead = 0";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error']);
}

$stmt->close();
$conn->close();
?>