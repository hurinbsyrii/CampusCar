<?php
session_start();
header('Content-Type: application/json');

// Ensure PHP uses Malaysia timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in to access this page.']);
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
$today = date('Y-m-d');
$current_time = date('H:i:s'); // Current time

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
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_passengers':
        if (!$is_driver) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only drivers can view passengers.']);
            exit();
        }
        getPassengers($conn, $_GET['ride_id']);
        break;

    case 'get_passenger_booking':
        getPassengerBooking($conn, $_GET['ride_id'], $user_id);
        break;

    case 'start_ride':
        if (!$is_driver) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only drivers can start rides.']);
            exit();
        }
        startRide($conn, $_POST['ride_id'], $driver['DriverID'], $current_time);
        break;

    case 'end_ride':
        if (!$is_driver) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only drivers can end rides.']);
            exit();
        }
        endRide($conn, $_POST['ride_id'], $driver['DriverID']);
        break;

    case 'cancel_booking':
        cancelBooking($conn, $_POST['booking_id'], $user_id);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action. Use paymentdb.php for payment operations.']);
        break;
}

$conn->close();

// Function to get passengers for a ride - EXCLUDE CANCELLED BOOKINGS
function getPassengers($conn, $ride_id)
{
    $sql = "SELECT b.BookingID, u.FullName as PassengerName, b.NoOfSeats as Seats,
                   CASE 
                       WHEN b.BookingStatus = 'Paid' THEN 'paid'
                       ELSE 'unpaid'
                   END as PaymentStatus
            FROM booking b
            JOIN user u ON b.UserID = u.UserID
            WHERE b.RideID = ? 
            AND b.BookingStatus IN ('Confirmed', 'Completed', 'Paid')  -- EXCLUDE CANCELLED
            ORDER BY b.BookingDateTime";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $ride_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $passengers = [];
    while ($row = $result->fetch_assoc()) {
        $passengers[] = $row;
    }

    $stmt->close();

    echo json_encode([
        'success' => true,
        'passengers' => $passengers
    ]);
}

// Function to get passenger's booking ID for a ride - EXCLUDE CANCELLED
function getPassengerBooking($conn, $ride_id, $user_id)
{
    $sql = "SELECT BookingID 
            FROM booking 
            WHERE RideID = ? AND UserID = ? 
            AND BookingStatus IN ('Confirmed', 'Completed')";  // EXCLUDE CANCELLED

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $ride_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'No confirmed booking found for this ride.'
        ]);
        exit();
    }

    $booking = $result->fetch_assoc();
    $stmt->close();

    echo json_encode([
        'success' => true,
        'booking_id' => $booking['BookingID']
    ]);
}

// Function to start a ride - WITH TIME CHECK
function startRide($conn, $ride_id, $driver_id, $current_time)
{
    // Verify driver owns the ride and check departure time
    $verify_sql = "SELECT RideID, DepartureTime, Status FROM rides WHERE RideID = ? AND DriverID = ?";
    $stmt = $conn->prepare($verify_sql);
    $stmt->bind_param("ii", $ride_id, $driver_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Ride not found or access denied.']);
        exit();
    }

    $ride = $result->fetch_assoc();
    $stmt->close();

    // Check if ride status is 'available'
    if ($ride['Status'] !== 'available') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Ride cannot be started.']);
        exit();
    }

    // Check if current time is after or equal to departure time
    if ($current_time < $ride['DepartureTime']) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Cannot start ride before scheduled departure time!'
        ]);
        exit();
    }

    // Update ride status to 'in_progress'
    $update_sql = "UPDATE rides SET Status = 'in_progress' WHERE RideID = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("i", $ride_id);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Ride started successfully!'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to start ride.'
        ]);
    }

    $stmt->close();
}

// Function to end a ride
function endRide($conn, $ride_id, $driver_id)
{
    // Verify driver owns the ride
    $verify_sql = "SELECT RideID FROM rides WHERE RideID = ? AND DriverID = ?";
    $stmt = $conn->prepare($verify_sql);
    $stmt->bind_param("ii", $ride_id, $driver_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Ride not found or access denied.']);
        exit();
    }
    $stmt->close();

    // Start transaction
    $conn->begin_transaction();

    try {
        // Update ride status to 'completed'
        $update_ride_sql = "UPDATE rides SET Status = 'completed' WHERE RideID = ?";
        $stmt = $conn->prepare($update_ride_sql);
        $stmt->bind_param("i", $ride_id);

        if (!$stmt->execute()) {
            throw new Exception('Failed to update ride status.');
        }
        $stmt->close();

        // Update all confirmed bookings for this ride to 'Completed' status
        // EXCLUDE CANCELLED BOOKINGS
        $update_bookings_sql = "UPDATE booking SET BookingStatus = 'Completed' 
                               WHERE RideID = ? AND BookingStatus = 'Confirmed'";
        $stmt = $conn->prepare($update_bookings_sql);
        $stmt->bind_param("i", $ride_id);

        if (!$stmt->execute()) {
            throw new Exception('Failed to update booking status.');
        }
        $stmt->close();

        // Commit transaction
        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Ride completed successfully! Passengers can now make payment.'
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

// Function to cancel booking - WITH PROPER SEAT RETURN
function cancelBooking($conn, $booking_id, $user_id)
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
        $ride_sql = "UPDATE rides SET AvailableSeats = AvailableSeats + ? WHERE RideID = ?";
        $stmt = $conn->prepare($ride_sql);
        $stmt->bind_param("ii", $booking['NoOfSeats'], $booking['RideID']);

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
