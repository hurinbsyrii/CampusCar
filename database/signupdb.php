<?php
// signupdb.php
session_start();

// Database configuration
$host = '127.0.0.1:3301';
$dbname = 'campuscar';
$username = 'root';
$password = '';

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Check if form is submitted via POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('Invalid JSON input');
    }

    // Validate required fields
    $required_fields = [
        'matricNo',
        'icNo',
        'fullName',
        'username',
        'password',
        'email',
        'phone',
        'gender',
        'faculty'
    ];

    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Sanitize input data
    $matricNo = trim($input['matricNo']);
    $icNo = trim($input['icNo']);
    $fullName = trim($input['fullName']);
    $username = trim($input['username']);
    $email = trim($input['email']);
    $password = $input['password'];
    $phone = trim($input['phone']);
    $gender = trim($input['gender']);
    $faculty = trim($input['faculty']);

    // Normalize matric number to uppercase to avoid duplicates with different casing
    $matricNo = strtoupper($matricNo);
    $email = strtolower($email); // Normalize email to lowercase

    // Check if username already exists
    $stmt = $pdo->prepare("SELECT UserID FROM user WHERE Username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        throw new Exception('Username already exists. Please choose a different username.');
    }

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT UserID FROM user WHERE LOWER(Email) = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        throw new Exception('Email address already exists. Please use a different email.');
    }

    // Check if matric number already exists (each matric can have only one account)
    $stmt = $pdo->prepare("SELECT UserID FROM user WHERE UPPER(MatricNo) = ?");
    $stmt->execute([$matricNo]);
    if ($stmt->fetch()) {
        throw new Exception('An account with this matric number already exists.');
    }

    // Hash the password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert user into database (including email field)
    $sql = "INSERT INTO user (
        MatricNo, ICNo, FullName, Username, Password, 
        PhoneNumber, Email, Gender, Faculty, Role
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'user')";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $matricNo,
        $icNo,
        $fullName,
        $username,
        $hashedPassword,
        $phone,
        $email,
        $gender,
        $faculty
    ]);

    // Get the newly created user ID
    $userID = $pdo->lastInsertId();

    // Store user information in session
    $_SESSION['user_id'] = $userID;
    $_SESSION['username'] = $username;
    $_SESSION['full_name'] = $fullName;
    $_SESSION['matric_no'] = $matricNo;
    $_SESSION['email'] = $email;

    // Return JSON success with redirect URL to dashboard
    echo json_encode([
        'success' => true,
        'message' => 'Account created successfully.',
        'redirect_url' => '../php/userdashboard.php'
    ], JSON_UNESCAPED_UNICODE);
    exit();
} catch (Exception $e) {
    // Send error response
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}