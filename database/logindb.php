<?php
// logindb.php
session_start();

// Database configuration
$host = '127.0.0.1:3301';
$dbname = 'campuscar';
$username = 'root';
$password = '';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

try {
    // Check if form is submitted via POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method. Expected POST, got ' . $_SERVER['REQUEST_METHOD']);
    }

    // Get JSON input
    $json_input = file_get_contents('php://input');

    if (empty($json_input)) {
        throw new Exception('No input data received');
    }

    $input = json_decode($json_input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }

    if (!$input) {
        throw new Exception('Invalid JSON input or empty data');
    }

    // Validate required fields
    if (empty($input['username'])) {
        throw new Exception('Username is required');
    }

    if (empty($input['password'])) {
        throw new Exception('Password is required');
    }

    // Create PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Sanitize input data
    $username = trim($input['username']);
    $password = $input['password'];
    // $remember = isset($input['remember']) ? (bool)$input['remember'] : false;

    // Check if user exists with role and driver status
    $stmt = $pdo->prepare("
        SELECT 
            u.*, 
            d.DriverID,
            CASE 
                WHEN d.DriverID IS NOT NULL THEN 'driver' 
                ELSE 'passenger' 
            END as user_type
        FROM user u
        LEFT JOIN driver d ON u.UserID = d.UserID
        WHERE u.Username = ?
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception('Invalid username or password');
    }

    // Verify password
    if (!password_verify($password, $user['Password'])) {
        throw new Exception('Invalid username or password');
    }

    // Store user information in session
    $_SESSION['user_id'] = $user['UserID'];
    $_SESSION['username'] = $user['Username'];
    $_SESSION['full_name'] = $user['FullName'];
    $_SESSION['matric_no'] = $user['MatricNo'];
    $_SESSION['faculty'] = $user['Faculty'];
    $_SESSION['phone'] = $user['PhoneNumber'];
    $_SESSION['gender'] = $user['Gender'];
    $_SESSION['role'] = $user['Role']; // Add role to session
    $_SESSION['user_type'] = $user['user_type']; // 'driver' or 'passenger'

    // Set driver_id if user is a driver
    if ($user['DriverID']) {
        $_SESSION['driver_id'] = $user['DriverID'];
    }

    // Determine redirect URL based on role
    $redirect_url = '../php/userdashboard.php'; // Default for regular users

    if ($user['Role'] == 'admin') {
        $redirect_url = '../php/admindashboard.php';
    } elseif ($user['user_type'] == 'user') {
        $redirect_url = '../php/userdashboard.php';
    }
    // 'passenger' with role 'user' will use default userdashboard.php

    // Set remember me cookie if requested
    // if ($remember) {
    //     $cookie_value = base64_encode($user['UserID'] . ':' . hash('sha256', $user['Password']));
    //     setcookie('campuscar_remember', $cookie_value, time() + (30 * 24 * 60 * 60), '/');
    // }

    // Send success response
    echo json_encode([
        'success' => true,
        'message' => 'Login successful! Welcome back, ' . $user['FullName'],
        'user' => [
            'id' => $user['UserID'],
            'username' => $user['Username'],
            'full_name' => $user['FullName'],
            'matric_no' => $user['MatricNo'],
            'faculty' => $user['Faculty'],
            'phone' => $user['PhoneNumber'],
            'gender' => $user['Gender'],
            'role' => $user['Role'],
            'user_type' => $user['user_type']
        ],
        'redirect_url' => $redirect_url
    ]);
} catch (PDOException $e) {
    // Database connection error
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    // Other errors
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
