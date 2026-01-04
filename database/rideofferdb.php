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
    $ride_date = $conn->real_escape_string($_POST['rideDate']);
    $departure_time = $conn->real_escape_string($_POST['departureTime']);
    $available_seats = intval($_POST['availableSeats']);
    $price_per_seat = floatval($_POST['pricePerSeat']);
    $ride_description = isset($_POST['rideDescription']) ? $conn->real_escape_string($_POST['rideDescription']) : '';

    // Get femaleOnly value (default to 0 if not set or driver is male)
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

    // Also check for rides that haven't expired yet (including today's rides)
    $current_datetime = date('Y-m-d H:i:s');
    
    // Check for any active ride on the same date (even with different times)
    // that might cause scheduling conflicts
    $check_date_sql = "SELECT RideID, FromLocation, ToLocation, DepartureTime, Status 
                       FROM rides 
                       WHERE DriverID = ? 
                       AND RideDate = ? 
                       AND Status IN ('available', 'in_progress')
                       AND CONCAT(RideDate, ' ', DepartureTime) > ?";
    
    $check_date_stmt = $conn->prepare($check_date_sql);
    $check_date_stmt->bind_param("iss", $driver_id, $ride_date, $current_datetime);
    $check_date_stmt->execute();
    $date_result = $check_date_stmt->get_result();
    
    if ($date_result->num_rows > 0) {
        // Check if there's any time conflict (within 2 hours of existing ride)
        $has_conflict = false;
        $conflict_ride = null;
        
        while ($ride = $date_result->fetch_assoc()) {
            $existing_time = strtotime($ride_date . ' ' . $ride['DepartureTime']);
            $new_time = strtotime($ride_date . ' ' . $departure_time);
            $time_diff = abs($existing_time - $new_time) / 3600; // Difference in hours
            
            if ($time_diff < 2) { // Less than 2 hours difference
                $has_conflict = true;
                $conflict_ride = $ride;
                break;
            }
        }
        
        if ($has_conflict && $conflict_ride) {
            $check_date_stmt->close();
            
            $_SESSION['warning'] = "You have another ride scheduled on " . date('d M Y', strtotime($ride_date)) . 
                                  " at " . date('h:i A', strtotime($conflict_ride['DepartureTime'])) . 
                                  " from " . $conflict_ride['FromLocation'] . " to " . $conflict_ride['ToLocation'] . 
                                  ". Please ensure there's at least 2 hours between rides.";
            header("Location: ../php/rideoffer.php");
            exit();
        }
    }
    $check_date_stmt->close();

    // Insert ride offer
    $insert_sql = "INSERT INTO rides (DriverID, FromLocation, ToLocation, RideDate, DepartureTime, AvailableSeats, PricePerSeat, RideDescription, FemaleOnly, Status) 
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

    if ($stmt->execute()) {
        $_SESSION['success'] = "Ride offered successfully!";
        
        // Get the new ride ID for notification
        $new_ride_id = $stmt->insert_id;
        
        // Create notification for the driver
        $notification_sql = "INSERT INTO notifications (UserID, Title, Message, Type, RelatedID, RelatedType) 
                             VALUES (?, 'Ride Offered', ?, 'success', ?, 'ride')";
        $notif_stmt = $conn->prepare($notification_sql);
        $message = "You have successfully offered a ride from $from_location to $to_location on " . 
                   date('d M Y', strtotime($ride_date)) . " at " . date('h:i A', strtotime($departure_time)) . 
                   ". Price: RM" . number_format($price_per_seat, 2) . " per seat.";
        $notif_stmt->bind_param("isi", $user_id, $message, $new_ride_id);
        $notif_stmt->execute();
        $notif_stmt->close();
        
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
?>