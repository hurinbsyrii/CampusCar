<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in to manage bookings.']);
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

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

// Check if user is a driver
$is_driver = false;
$driver_check_sql = "SELECT DriverID FROM driver WHERE UserID = ?";
$stmt = $conn->prepare($driver_check_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $is_driver = true;
    $driver = $result->fetch_assoc();
}
$stmt->close();

// Handle different actions
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'cancel_booking':
        cancelBooking($conn, $user_id, $_POST['booking_id']);
        break;

    case 'update_booking_status':
        if (!$is_driver) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only drivers can update booking status.']);
            exit();
        }
        updateBookingStatus($conn, $driver['DriverID'], $_POST['booking_id'], $_POST['status']);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        break;
}

$conn->close();

// Function to cancel booking
function cancelBooking($conn, $user_id, $booking_id)
{
    // Verify user owns the booking
    $verify_sql = "SELECT * FROM booking WHERE BookingID = ? AND UserID = ?";
    $stmt = $conn->prepare($verify_sql);
    $stmt->bind_param("ii", $booking_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Booking not found or access denied.']);
        exit();
    }

    $booking = $result->fetch_assoc();
    $stmt->close();

    // Start transaction
    $conn->begin_transaction();

    try {
        // Update booking status
        $update_sql = "UPDATE booking SET BookingStatus = 'Cancelled' WHERE BookingID = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("i", $booking_id);

        if (!$stmt->execute()) {
            throw new Exception('Failed to cancel booking.');
        }
        $stmt->close();

        // Return seats to available seats
        $ride_sql = "UPDATE rides r 
                    JOIN booking b ON r.RideID = b.RideID 
                    SET r.AvailableSeats = r.AvailableSeats + b.NoOfSeats 
                    WHERE b.BookingID = ?";
        $stmt = $conn->prepare($ride_sql);
        $stmt->bind_param("i", $booking_id);

        if (!$stmt->execute()) {
            throw new Exception('Failed to update ride availability.');
        }
        $stmt->close();

        // Commit transaction
        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Booking cancelled successfully!'
        ]);
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

// Function to update booking status (for drivers)
function updateBookingStatus($conn, $driver_id, $booking_id, $status)
{
    // Verify driver owns the ride associated with this booking
    $verify_sql = "SELECT b.*, b.NoOfSeats, r.RideID FROM booking b
                  JOIN rides r ON b.RideID = r.RideID
                  WHERE b.BookingID = ? AND r.DriverID = ?";
    $stmt = $conn->prepare($verify_sql);
    $stmt->bind_param("ii", $booking_id, $driver_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Booking not found or access denied.']);
        exit();
    }

    $booking = $result->fetch_assoc();
    $stmt->close();

    // Start transaction
    $conn->begin_transaction();

    try {
        // Update booking status
        // Kemaskini status DAN set IsSeenByPassenger kepada 0 (supaya notifikasi naik kat passenger)
        $update_sql = "UPDATE booking SET BookingStatus = ?, IsSeenByPassenger = 0 WHERE BookingID = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("si", $status, $booking_id);

        if (!$stmt->execute()) {
            throw new Exception('Failed to update booking status.');
        }
        $stmt->close();

        // If status is 'Rejected' or 'Cancelled', return seats to available seats
        if ($status === 'Rejected' || $status === 'Cancelled') {
            $ride_sql = "UPDATE rides 
                        SET AvailableSeats = AvailableSeats + ? 
                        WHERE RideID = ?";
            $stmt = $conn->prepare($ride_sql);
            $stmt->bind_param("ii", $booking['NoOfSeats'], $booking['RideID']);

            if (!$stmt->execute()) {
                throw new Exception('Failed to update ride availability.');
            }
            $stmt->close();
        }

        // Commit transaction
        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Booking status updated successfully!'
        ]);
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}
