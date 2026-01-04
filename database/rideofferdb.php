<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../php/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $servername = "127.0.0.1:3301";
    $username = "root";
    $password = "";
    $dbname = "campuscar";

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $user_id = $_SESSION['user_id'];

    // Get driver ID and gender
    $driver_sql = "SELECT d.DriverID, u.Gender 
                   FROM driver d 
                   JOIN user u ON d.UserID = u.UserID 
                   WHERE d.UserID = ?";
    $stmt = $conn->prepare($driver_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $_SESSION['error'] = "You need to be registered as a driver to offer rides";
        header("Location: ../php/userdashboard.php");
        exit();
    }

    $driver_data = $result->fetch_assoc();
    $driver_id = $driver_data['DriverID'];
    $driver_gender = $driver_data['Gender'];
    $stmt->close();

    // Get form data
    $from_location = $conn->real_escape_string($_POST['fromLocation']);
    $to_location = $conn->real_escape_string($_POST['toLocation']);
    $from_lat = isset($_POST['fromLocationLat']) ? floatval($_POST['fromLocationLat']) : null;
    $from_lng = isset($_POST['fromLocationLng']) ? floatval($_POST['fromLocationLng']) : null;
    $to_lat = isset($_POST['toLocationLat']) ? floatval($_POST['toLocationLat']) : null;
    $to_lng = isset($_POST['toLocationLng']) ? floatval($_POST['toLocationLng']) : null;
    $ride_date = $conn->real_escape_string($_POST['rideDate']);
    $departure_time = $conn->real_escape_string($_POST['departureTime']);
    $available_seats = intval($_POST['availableSeats']);
    $price_per_seat = floatval($_POST['pricePerSeat']);
    $ride_description = isset($_POST['rideDescription']) ? $conn->real_escape_string($_POST['rideDescription']) : '';

    // Get coordinates if they exist
    $from_lat = isset($_POST['fromLocationLat']) && !empty($_POST['fromLocationLat']) ? floatval($_POST['fromLocationLat']) : null;
    $from_lng = isset($_POST['fromLocationLng']) && !empty($_POST['fromLocationLng']) ? floatval($_POST['fromLocationLng']) : null;
    $to_lat = isset($_POST['toLocationLat']) && !empty($_POST['toLocationLat']) ? floatval($_POST['toLocationLat']) : null;
    $to_lng = isset($_POST['toLocationLng']) && !empty($_POST['toLocationLng']) ? floatval($_POST['toLocationLng']) : null;

    // Get femaleOnly value
    $female_only = 0;
    if (isset($_POST['femaleOnly']) && $_POST['femaleOnly'] == '1' && $driver_gender === 'female') {
        $female_only = 1;
    }

    // CHECK: Prevent driver from offering multiple rides on same date and time
    $check_ride_sql = "SELECT RideID, FromLocation, ToLocation, Status 
                       FROM rides 
                       WHERE DriverID = ? 
                       AND RideDate = ? 
                       AND DepartureTime = ?
                       AND Status IN ('available', 'in_progress')";

    $check_stmt = $conn->prepare($check_ride_sql);
    $check_stmt->bind_param("iss", $driver_id, $ride_date, $departure_time);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $existing_ride = $check_result->fetch_assoc();
        $check_stmt->close();

        $_SESSION['error'] = "You already have a ride scheduled on " . date('d M Y', strtotime($ride_date)) .
            " at " . date('h:i A', strtotime($departure_time)) .
            " from " . $existing_ride['FromLocation'] . " to " . $existing_ride['ToLocation'] .
            ". Please choose a different date/time or cancel the existing ride first.";
        header("Location: ../php/rideoffer.php");
        exit();
    }
    $check_stmt->close();

    // Check if coordinate columns exist
    $coord_columns_exist = $conn->query("SHOW COLUMNS FROM rides LIKE 'FromLat'")->num_rows > 0;

    // Insert ride offer
    if ($coord_columns_exist && $from_lat !== null && $from_lng !== null && $to_lat !== null && $to_lng !== null) {
        // Insert with coordinates
        $insert_sql = "INSERT INTO rides (DriverID, FromLocation, ToLocation, FromLat, FromLng, ToLat, ToLng, 
                                          RideDate, DepartureTime, AvailableSeats, PricePerSeat, 
                                          RideDescription, FemaleOnly, Status) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'available')";

        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param(
            "issssssssidsi",
            $driver_id,
            $from_location,
            $to_location,
            $from_lat,
            $from_lng,
            $to_lat,
            $to_lng,
            $ride_date,
            $departure_time,
            $available_seats,
            $price_per_seat,
            $ride_description,
            $female_only
        );
    } else {
        // Insert without coordinates (backward compatible)
        $insert_sql = "INSERT INTO rides (DriverID, FromLocation, ToLocation, RideDate, DepartureTime, 
                                          AvailableSeats, PricePerSeat, RideDescription, FemaleOnly, Status) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'available')";

        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param(
            "issssidsi",
            $driver_id,
            $from_location,
            $to_location,
            $ride_date,
            $departure_time,
            $available_seats,
            $price_per_seat,
            $ride_description,
            $female_only
        );
    }

    if ($stmt->execute()) {
        $_SESSION['success'] = "Ride offered successfully!";

        header("Location: ../php/userdashboard.php");
    } else {
        $_SESSION['error'] = "Error offering ride: " . $stmt->error;
        header("Location: ../php/rideoffer.php");
    }

    $stmt->close();
    $conn->close();
} else {
    header("Location: ../php/rideoffer.php");
    exit();
}
