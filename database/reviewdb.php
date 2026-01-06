<?php
session_start();
header('Content-Type: application/json');

// Ensure PHP uses Malaysia timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in to submit a review.']);
    exit();
}

// Database connection
$servername = "127.0.0.1:3301";
$username = "root";
$password = "";
$dbname = "campuscar";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

// Ensure MySQL connection uses Malaysia timezone (+08:00)
$conn->query("SET time_zone = '+08:00'");

$user_id = $_SESSION['user_id'];
$booking_id = $_POST['booking_id'] ?? null;
$driver_id = $_POST['driver_id'] ?? null;
$ride_id = $_POST['ride_id'] ?? null;
$rating = $_POST['rating'] ?? null;
$comment = $_POST['comment'] ?? null;

// Validate required fields
if (!$booking_id || !$driver_id || !$ride_id || !$rating) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit();
}

// Validate rating range
$rating = intval($rating);
if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5.']);
    exit();
}

// Validate comment length
if ($comment && strlen($comment) > 500) {
    echo json_encode(['success' => false, 'message' => 'Comment cannot exceed 500 characters.']);
    exit();
}

// Validate terms acceptance
if (!isset($_POST['terms'])) {
    echo json_encode(['success' => false, 'message' => 'Please accept the review terms.']);
    exit();
}

try {
    // 1. Verify booking eligibility
    $verify_sql = "SELECT b.*, p.PaymentStatus, r.Status as RideStatus
                   FROM booking b
                   LEFT JOIN payments p ON b.BookingID = p.BookingID AND p.UserID = b.UserID
                   JOIN rides r ON b.RideID = r.RideID
                   WHERE b.BookingID = ? 
                   AND b.UserID = ?
                   AND b.BookingStatus = 'Paid'
                   AND p.PaymentStatus = 'paid'
                   AND r.Status = 'completed'";

    $stmt = $conn->prepare($verify_sql);
    $stmt->bind_param("ii", $booking_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Booking not eligible for review. Ensure payment is completed and ride is finished.");
    }

    $booking = $result->fetch_assoc();
    $stmt->close();

    // 2. Check if review already exists for this booking
    $check_review_sql = "SELECT ReviewID FROM reviews WHERE BookingID = ?";
    $stmt = $conn->prepare($check_review_sql);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $review_result = $stmt->get_result();

    if ($review_result->num_rows > 0) {
        throw new Exception("You have already reviewed this ride.");
    }
    $stmt->close();

    // 3. Optional: Check 7-day limit
    $ride_date = $booking['RideDate'] . ' ' . $booking['DepartureTime'];
    $ride_timestamp = strtotime($ride_date);
    $current_timestamp = time();
    $days_diff = floor(($current_timestamp - $ride_timestamp) / (60 * 60 * 24));

    // Uncomment to enforce 7-day limit
    // if ($days_diff > 7) {
    //     throw new Exception("Review period has expired (7 days after ride).");
    // }

    // 4. Insert review
    $insert_sql = "INSERT INTO reviews (BookingID, UserID, DriverID, RideID, Rating, Comment, CreatedAt) 
                   VALUES (?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($insert_sql);
    $comment = $comment ? trim($comment) : null;
    $stmt->bind_param("iiiiss", $booking_id, $user_id, $driver_id, $ride_id, $rating, $comment);

    if (!$stmt->execute()) {
        throw new Exception("Failed to save review.");
    }

    $review_id = $conn->insert_id;
    $stmt->close();

    // 5. Update driver's average rating (optional - can be cached)
    updateDriverRating($conn, $driver_id);

    // Success response
    echo json_encode([
        'success' => true,
        'message' => 'Review submitted successfully!',
        'review_id' => $review_id,
        'booking_id' => $booking_id
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();

// Function to update driver's average rating
function updateDriverRating($conn, $driver_id)
{
    // This function calculates and caches the driver's average rating
    // You can implement caching here if needed
    $avg_sql = "SELECT AVG(Rating) as avg_rating, COUNT(*) as review_count 
                FROM reviews 
                WHERE DriverID = ?";

    $stmt = $conn->prepare($avg_sql);
    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $data = $result->fetch_assoc();
        // You could store this in a driver_profile table or cache it
        // For now, we just calculate it on the fly when needed
    }

    $stmt->close();
}
