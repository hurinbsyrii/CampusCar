<?php
// Start output buffering to capture any stray output
ob_start();

session_start();

// Set JSON header FIRST
header('Content-Type: application/json');

// Ensure PHP uses Malaysia timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to submit a review.']);
    ob_end_flush();
    exit();
}

// Validate required POST data
$required_fields = ['booking_id', 'driver_id', 'ride_id', 'rating', 'terms'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        ob_end_flush();
        exit();
    }
}

// Assign variables
$user_id = $_SESSION['user_id'];
$booking_id = intval($_POST['booking_id']);
$driver_id = intval($_POST['driver_id']);
$ride_id = intval($_POST['ride_id']);
$rating = intval($_POST['rating']);
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

// Validate rating range
if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5.']);
    ob_end_flush();
    exit();
}

// Validate comment length
if (strlen($comment) > 500) {
    echo json_encode(['success' => false, 'message' => 'Comment cannot exceed 500 characters.']);
    ob_end_flush();
    exit();
}

// Database connection
$servername = "127.0.0.1:3301";
$username = "root";
$password = "";
$dbname = "campuscar";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    ob_end_flush();
    exit();
}

// Ensure MySQL connection uses Malaysia timezone
$conn->query("SET time_zone = '+08:00'");

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
    $check_review_sql = "SELECT ReviewID FROM reviews WHERE BookingID = ?";
    $stmt = $conn->prepare($check_review_sql);
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    
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
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    // Use empty string if comment is null
    $comment = empty($comment) ? '' : $comment;
    $stmt->bind_param("iiiiss", $booking_id, $user_id, $driver_id, $ride_id, $rating, $comment);

    if (!$stmt->execute()) {
        throw new Exception("Failed to save review: " . $stmt->error);
    }

    $review_id = $conn->insert_id;
    $stmt->close();

    // 5. Update driver's average rating (optional)
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
ob_end_flush();

// Function to update driver's average rating
function updateDriverRating($conn, $driver_id)
{
    $avg_sql = "SELECT AVG(Rating) as avg_rating, COUNT(*) as review_count 
                FROM reviews 
                WHERE DriverID = ?";

    $stmt = $conn->prepare($avg_sql);
    if ($stmt) {
        $stmt->bind_param("i", $driver_id);
        $stmt->execute();
        $stmt->close();
    }
}
?>