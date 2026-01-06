<?php
// Start output buffering early
ob_start();

// Start session
session_start();

// Set JSON header FIRST - this is critical
header('Content-Type: application/json; charset=utf-8');

// Ensure PHP uses Malaysia timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

// Database connection
$servername = "127.0.0.1:3301";
$username = "root";
$password = "";
$dbname = "campuscar";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    sendJsonResponse(false, 'Database connection failed: ' . $conn->connect_error);
    exit();
}

// Set timezone for MySQL
$conn->query("SET time_zone = '+08:00'");

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Please log in to submit a review.');
    }

    // Validate required POST data
    $required_fields = ['booking_id', 'driver_id', 'ride_id', 'rating'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception('Missing required fields.');
        }
    }

    // Validate terms checkbox
    if (!isset($_POST['terms']) || $_POST['terms'] !== 'on') {
        throw new Exception('Please confirm the review terms.');
    }

    // Sanitize inputs
    $user_id = (int)$_SESSION['user_id'];
    $booking_id = (int)$_POST['booking_id'];
    $driver_id = (int)$_POST['driver_id'];
    $ride_id = (int)$_POST['ride_id'];
    $rating = (int)$_POST['rating'];
    $comment = isset($_POST['comment']) ? trim($conn->real_escape_string($_POST['comment'])) : '';

    // Validate rating range
    if ($rating < 1 || $rating > 5) {
        throw new Exception('Rating must be between 1 and 5.');
    }

    // Validate comment length
    if (strlen($comment) > 500) {
        throw new Exception('Comment cannot exceed 500 characters.');
    }

    // 1. Verify booking eligibility
    $verify_sql = "SELECT b.*, p.PaymentStatus, r.Status as RideStatus
                   FROM booking b
                   JOIN payments p ON b.BookingID = p.BookingID AND p.UserID = b.UserID
                   JOIN rides r ON b.RideID = r.RideID
                   WHERE b.BookingID = ? 
                   AND b.UserID = ?
                   AND b.BookingStatus = 'Paid'
                   AND p.PaymentStatus = 'paid'
                   AND r.Status = 'completed'";

    $stmt = $conn->prepare($verify_sql);
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }

    $stmt->bind_param("ii", $booking_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Booking not eligible for review. Ensure payment is completed and ride is finished.");
    }

    $booking = $result->fetch_assoc();
    $stmt->close();

    // 2. Check if review already exists for this booking
    $check_review_sql = "SELECT ReviewID FROM reviews WHERE BookingID = ? AND UserID = ?";
    $stmt = $conn->prepare($check_review_sql);
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }

    $stmt->bind_param("ii", $booking_id, $user_id);
    $stmt->execute();
    $review_result = $stmt->get_result();

    if ($review_result->num_rows > 0) {
        throw new Exception("You have already reviewed this ride.");
    }
    $stmt->close();

    // 3. Insert review
    $insert_sql = "INSERT INTO reviews (BookingID, UserID, DriverID, RideID, Rating, Comment, CreatedAt) 
                   VALUES (?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($insert_sql);
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }

    $stmt->bind_param("iiiiss", $booking_id, $user_id, $driver_id, $ride_id, $rating, $comment);

    if (!$stmt->execute()) {
        throw new Exception("Failed to save review: " . $stmt->error);
    }

    $review_id = $stmt->insert_id;
    $stmt->close();

    // 4. Update driver's average rating
    updateDriverRating($conn, $driver_id);

    // Success response
    sendJsonResponse(true, 'Review submitted successfully!', [
        'review_id' => $review_id,
        'booking_id' => $booking_id
    ]);
} catch (Exception $e) {
    sendJsonResponse(false, $e->getMessage());
}

$conn->close();

// Function to update driver's average rating
function updateDriverRating($conn, $driver_id)
{
    // This function can calculate and update driver's average rating if needed
    // Currently it just calculates but doesn't update any table
    $avg_sql = "SELECT AVG(Rating) as avg_rating, COUNT(*) as review_count 
                FROM reviews 
                WHERE DriverID = ?";
    $stmt = $conn->prepare($avg_sql);
    if ($stmt) {
        $stmt->bind_param("i", $driver_id);
        $stmt->execute();
        // You might want to store this in a driver_ratings table
        $stmt->close();
    }
}

// Helper function to send JSON response
function sendJsonResponse($success, $message, $data = [])
{
    // Clear any previous output
    if (ob_get_length()) ob_clean();

    $response = [
        'success' => $success,
        'message' => $message
    ];

    if (!empty($data)) {
        $response = array_merge($response, $data);
    }

    echo json_encode($response);
    exit();
}
