<?php
// database/verifydb.php - Student Verification with Database
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$servername = "localhost:3301";
$username = "root";
$password = "";
$dbname = "utemcampuscar";

// Response array
$response = array(
    'success' => false,
    'message' => '',
    'student' => null,
    'debug' => '' // Added for debugging
);

try {
    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }

    // Set charset to utf8
    $conn->set_charset("utf8");

    // Handle preflight request
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        http_response_code(200);
        exit();
    }

    // Only handle POST requests
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            throw new Exception('Invalid JSON input');
        }

        $matricNo = isset($input['matricNo']) ? trim($input['matricNo']) : '';
        $icNo = isset($input['icNo']) ? trim($input['icNo']) : '';

        // Validate input
        if (empty($matricNo) || empty($icNo)) {
            throw new Exception('Matric number and IC number are required');
        }

        // Validate matric number format
        if (!preg_match('/^[A-Z]{1}\d{9}$/', $matricNo)) {
            throw new Exception('Invalid matric number format.');
        }

        // Validate IC number format
        if (!preg_match('/^\d{12}$/', $icNo)) {
            throw new Exception('Invalid IC number format.');
        }

        // Convert matric number to uppercase
        $matricNo = strtoupper($matricNo);

        // DEBUG: Check what columns exist in the user table
        $debugInfo = "";
        $columnCheck = $conn->query("SHOW COLUMNS FROM user");
        $debugInfo .= "Columns in user table: ";
        $columns = [];
        while ($row = $columnCheck->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
        $debugInfo .= implode(", ", $columns);

        // Prepare SQL statement
        $stmt = $conn->prepare("SELECT MatricNo, ICNo, FullName FROM user WHERE MatricNo = ? AND ICNo = ?");
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }

        $stmt->bind_param("ss", $matricNo, $icNo);

        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }

        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Student found and verified
            $student = $result->fetch_assoc();

            // DEBUG: Log what we actually got from database
            $debugInfo .= " | Retrieved student data: " . print_r($student, true);

            $response['success'] = true;
            $response['message'] = 'Student verification successful';
            $response['student'] = [
                'matricNo' => $student['MatricNo'],
                'fullName' => $student['FullName'], // Make sure this matches your database column
                'icNo' => substr($student['ICNo'], 0, 6) . '******'
            ];
            $response['debug'] = $debugInfo;
        } else {
            // Check if matric number exists but IC doesn't match
            $stmt2 = $conn->prepare("SELECT MatricNo, FullName FROM user WHERE MatricNo = ?");
            if (!$stmt2) {
                throw new Exception('Prepare failed: ' . $conn->error);
            }

            $stmt2->bind_param("s", $matricNo);
            $stmt2->execute();
            $result2 = $stmt2->get_result();

            if ($result2->num_rows > 0) {
                // Matric exists but IC doesn't match
                $student = $result2->fetch_assoc();
                $debugInfo .= " | Matric exists but IC mismatch. Student data: " . print_r($student, true);

                // Do NOT expose FullName on IC mismatch — only return a generic message
                $response['student'] = null;
                $response['message'] = 'IC number does not match the matric number';
                $response['debug'] = $debugInfo;
            } else {
                // Matric number not found
                $response['message'] = 'Matric number not found in UTEM student records';
                $response['debug'] = $debugInfo;
            }
            $stmt2->close();
        }
        $stmt->close();
    } else {
        throw new Exception('Only POST requests are allowed');
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

// Return JSON response
echo json_encode($response, JSON_UNESCAPED_UNICODE);
