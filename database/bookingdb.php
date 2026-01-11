<?php
error_reporting(0);
ini_set('display_errors', 0);

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in to book a ride.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data.']);
    exit();
}

$required_fields = ['rideID', 'userID', 'passengerName', 'passengerPhone', 'numberOfSeats', 'totalPrice'];
foreach ($required_fields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit();
    }
}

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

$conn->begin_transaction();

try {
    $ride_id = intval($input['rideID']);
    $user_id = intval($input['userID']);
    $number_of_seats = intval($input['numberOfSeats']);
    $total_price = floatval($input['totalPrice']);
    $passenger_name = $conn->real_escape_string(trim($input['passengerName']));
    $passenger_phone = $conn->real_escape_string(trim($input['passengerPhone']));
    $special_requests = isset($input['specialRequests']) ? $conn->real_escape_string(trim($input['specialRequests'])) : '';
    $booking_datetime = date('Y-m-d H:i:s');

    // Get ride details including date and time
    $ride_check_sql = "SELECT r.*, d.UserID as DriverUserID 
                       FROM rides r 
                       JOIN driver d ON r.DriverID = d.DriverID 
                       WHERE r.RideID = ? AND r.Status = 'available'";
    $stmt = $conn->prepare($ride_check_sql);
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }

    $stmt->bind_param("i", $ride_id);
    $stmt->execute();
    $ride_result = $stmt->get_result();

    if ($ride_result->num_rows === 0) {
        throw new Exception('Ride not available or does not exist.');
    }

    $ride = $ride_result->fetch_assoc();
    $available_seats = $ride['AvailableSeats'];
    $driver_user_id = $ride['DriverUserID'];
    $female_only = isset($ride['FemaleOnly']) ? $ride['FemaleOnly'] : 0;
    $ride_date = $ride['RideDate'];
    $departure_time = $ride['DepartureTime'];
    $stmt->close();

    // Check if user is trying to book their own ride
    if ($user_id == $driver_user_id) {
        throw new Exception('You cannot book your own ride.');
    }

    // Check if ride is Girls Only and user is male
    if ($female_only == 1) {
        $user_gender_sql = "SELECT Gender FROM user WHERE UserID = ?";
        $stmt = $conn->prepare($user_gender_sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $gender_result = $stmt->get_result();

        if ($gender_result->num_rows > 0) {
            $user_data = $gender_result->fetch_assoc();
            if ($user_data['Gender'] !== 'female') {
                throw new Exception('This ride is for female passengers only.');
            }
        }
        $stmt->close();
    }

    // Check if enough seats are available
    if ($number_of_seats > $available_seats) {
        throw new Exception('Not enough pax available. Only ' . $available_seats . ' pax left.');
    }

    // Check if user already has a booking for this specific ride
    $existing_booking_sql = "SELECT BookingID FROM booking 
                            WHERE RideID = ? AND UserID = ? 
                            AND BookingStatus IN ('Pending', 'Confirmed', 'Paid')";
    $stmt = $conn->prepare($existing_booking_sql);
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }

    $stmt->bind_param("ii", $ride_id, $user_id);
    $stmt->execute();
    $existing_result = $stmt->get_result();

    if ($existing_result->num_rows > 0) {
        throw new Exception('You already have a booking for this ride.');
    }
    $stmt->close();

    // ========== PERUBAHAN PENTING ==========
    // Check if user has any other ACTIVE booking on the same date and time
    // PERUBAHAN: 'Paid' status is ALLOWED because ride is already completed
    $overlap_check_sql = "SELECT b.BookingID, r.RideID, r.FromLocation, r.ToLocation,
                                 r.RideDate, r.DepartureTime, b.BookingStatus
                         FROM booking b
                         JOIN rides r ON b.RideID = r.RideID
                         WHERE b.UserID = ? 
                         AND b.BookingStatus IN ('Pending', 'Confirmed', 'In Progress')
                         AND r.RideDate = ?
                         AND ABS(TIME_TO_SEC(TIMEDIFF(r.DepartureTime, ?))) < 3600
                         AND r.RideID != ?";

    $stmt = $conn->prepare($overlap_check_sql);
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }

    $stmt->bind_param("issi", $user_id, $ride_date, $departure_time, $ride_id);
    $stmt->execute();
    $overlap_result = $stmt->get_result();

    if ($overlap_result->num_rows > 0) {
        $conflicting_ride = $overlap_result->fetch_assoc();
        $conflict_time = date('g:i A', strtotime($conflicting_ride['DepartureTime']));
        $conflict_date = date('F j, Y', strtotime($conflicting_ride['RideDate']));

        // Berikan mesej yang lebih jelas tentang status booking
        $status_message = '';
        switch ($conflicting_ride['BookingStatus']) {
            case 'Pending':
                $status_message = 'pending confirmation';
                break;
            case 'Confirmed':
                $status_message = 'confirmed';
                break;
            case 'In Progress':
                $status_message = 'in progress';
                break;
            default:
                $status_message = $conflicting_ride['BookingStatus'];
        }

        throw new Exception("You already have a $status_message booking on $conflict_date at $conflict_time from {$conflicting_ride['FromLocation']} to {$conflicting_ride['ToLocation']}. Please complete or cancel your existing booking before booking another ride at this time.");
    }
    $stmt->close();
    // ========== AKHIR PERUBAHAN ==========

    // Insert booking
    $booking_sql = "INSERT INTO booking (RideID, UserID, BookingDateTime, NoOfSeats, TotalPrice, BookingStatus, CancellationReason) 
                    VALUES (?, ?, ?, ?, ?, 'Pending', '')";
    $stmt = $conn->prepare($booking_sql);
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }

    $stmt->bind_param("iisid", $ride_id, $user_id, $booking_datetime, $number_of_seats, $total_price);

    if (!$stmt->execute()) {
        throw new Exception('Failed to create booking: ' . $stmt->error);
    }

    $booking_id = $stmt->insert_id;
    $stmt->close();

    // Update available seats in rides table
    $new_available_seats = $available_seats - $number_of_seats;
    $update_ride_sql = "UPDATE rides SET AvailableSeats = ? WHERE RideID = ?";
    $stmt = $conn->prepare($update_ride_sql);
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }

    $stmt->bind_param("ii", $new_available_seats, $ride_id);

    if (!$stmt->execute()) {
        throw new Exception('Failed to update ride availability: ' . $stmt->error);
    }
    $stmt->close();

    // Create notification for driver
    $notification_sql = "INSERT INTO notifications (UserID, Title, Message, Type, RelatedID, RelatedType) 
                        VALUES (?, 'New Booking', 'You have a new booking for your ride on $ride_date at $departure_time.', 'info', ?, 'booking')";
    $stmt = $conn->prepare($notification_sql);
    if ($stmt) {
        $stmt->bind_param("ii", $driver_user_id, $booking_id);
        $stmt->execute();
        $stmt->close();
    }

    // Create notification for passenger
    $passenger_notification_sql = "INSERT INTO notifications (UserID, Title, Message, Type, RelatedID, RelatedType) 
                                  VALUES (?, 'Booking Confirmed', 'Your booking for the ride on $ride_date at $departure_time has been created successfully.', 'success', ?, 'booking')";
    $stmt = $conn->prepare($passenger_notification_sql);
    if ($stmt) {
        $stmt->bind_param("ii", $user_id, $booking_id);
        $stmt->execute();
        $stmt->close();
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Booking created successfully!',
        'booking_id' => $booking_id
    ]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    $conn->close();
}
