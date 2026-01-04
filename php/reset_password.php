<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$host = "127.0.0.1:3301";
$username = "root";
$password = "";
$database = "campuscar";

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// DEBUG: Log semua request
error_log("=== RESET PASSWORD PAGE ACCESSED ===");
error_log("GET Parameters: " . print_r($_GET, true));
error_log("Server Time: " . date('Y-m-d H:i:s'));

if (!isset($_GET['token'])) {
    error_log("No token provided, redirecting to forgot_password.php");
    header("Location: forgot_password.php");
    exit();
}

// DAPATKAN TOKEN
$token = $_GET['token'];
error_log("Token from URL: " . $token);

$error = '';
$success = '';
$show_form = false;

// 1. CEK TOKEN DI DATABASE (TANPA KONDISI EXPIRED DULU)
$sql = "SELECT pr.*, u.UserID, u.FullName, u.Email 
        FROM password_reset pr 
        JOIN user u ON pr.UserID = u.UserID 
        WHERE pr.Token = ? 
        AND pr.IsUsed = 0";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

error_log("Number of rows found: " . $result->num_rows);

if ($result->num_rows === 0) {
    // Token tidak ditemukan - debug lebih lanjut
    $error = "Invalid reset link. Please request a new password reset.";
    
    // Debug: Tampilkan semua token di database untuk comparison
    $debug_sql = "SELECT Token, LEFT(Token, 20) as token_start, UserID, ExpiryDateTime, IsUsed, CreatedAt 
                  FROM password_reset 
                  ORDER BY CreatedAt DESC";
    $debug_result = $conn->query($debug_sql);
    
    error_log("=== DEBUG: ALL TOKENS IN DATABASE ===");
    $found = false;
    while ($row = $debug_result->fetch_assoc()) {
        error_log("DB Token: " . $row['Token'] . 
                  " | Start: " . $row['token_start'] .
                  " | UserID: " . $row['UserID'] . 
                  " | Expiry: " . $row['ExpiryDateTime'] . 
                  " | Used: " . $row['IsUsed'] . 
                  " | Created: " . $row['CreatedAt']);
        
        // Check if tokens match
        if ($row['Token'] === $token) {
            $found = true;
            error_log("*** TOKEN MATCH FOUND IN DATABASE! ***");
        }
    }
    
    if ($found) {
        error_log("Token exists but query returned 0 rows. Checking expiry...");
        
        // Check if token exists but expired
        $expiry_sql = "SELECT ExpiryDateTime FROM password_reset WHERE Token = ?";
        $expiry_stmt = $conn->prepare($expiry_sql);
        $expiry_stmt->bind_param("s", $token);
        $expiry_stmt->execute();
        $expiry_result = $expiry_stmt->get_result();
        
        if ($expiry_row = $expiry_result->fetch_assoc()) {
            $expiry_time = strtotime($expiry_row['ExpiryDateTime']);
            $current_time = time();
            
            error_log("Token expiry time: " . $expiry_row['ExpiryDateTime'] . " (" . $expiry_time . ")");
            error_log("Current server time: " . date('Y-m-d H:i:s') . " (" . $current_time . ")");
            error_log("Time difference: " . ($current_time - $expiry_time) . " seconds");
            
            if ($current_time > $expiry_time) {
                $error = "This reset link has expired. Please request a new one.";
            }
        }
        $expiry_stmt->close();
    }
    
} else {
    $reset_data = $result->fetch_assoc();
    
    error_log("Token VALID for user: " . $reset_data['FullName']);
    error_log("Token expiry: " . $reset_data['ExpiryDateTime']);
    
    // 2. CEK APAKAH TOKEN SUDAH EXPIRED
    $expiry_time = strtotime($reset_data['ExpiryDateTime']);
    $current_time = time();
    
    if ($current_time > $expiry_time) {
        $error = "This reset link has expired. Please request a new one.";
        error_log("Token expired! Current: $current_time, Expiry: $expiry_time");
    } else {
        $show_form = true;
        error_log("Token is valid and not expired. Showing reset form.");
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $password = trim($_POST['password']);
            $confirm_password = trim($_POST['confirm_password']);
            
            if (empty($password) || empty($confirm_password)) {
                $error = "Please fill in all fields";
            } elseif ($password !== $confirm_password) {
                $error = "Passwords do not match";
            } elseif (strlen($password) < 6) {
                $error = "Password must be at least 6 characters";
            } else {
                // Hash password baru
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Mulai transaction
                $conn->begin_transaction();
                
                try {
                    // Update password user
                    $update_sql = "UPDATE user SET Password = ? WHERE UserID = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("si", $hashed_password, $reset_data['UserID']);
                    
                    if (!$update_stmt->execute()) {
                        throw new Exception("Failed to update password: " . $update_stmt->error);
                    }
                    $update_stmt->close();
                    
                    // Tandai token sebagai sudah digunakan
                    $mark_sql = "UPDATE password_reset SET IsUsed = 1 WHERE ResetID = ?";
                    $mark_stmt = $conn->prepare($mark_sql);
                    $mark_stmt->bind_param("i", $reset_data['ResetID']);
                    
                    if (!$mark_stmt->execute()) {
                        throw new Exception("Failed to mark token as used: " . $mark_stmt->error);
                    }
                    $mark_stmt->close();
                    
                    // Commit transaction
                    $conn->commit();
                    
                    $success = "✅ Password has been reset successfully! You can now login with your new password.";
                    $show_form = false;
                    
                    error_log("Password reset SUCCESSFUL for user: " . $reset_data['FullName']);
                    
                } catch (Exception $e) {
                    // Rollback transaction
                    $conn->rollback();
                    $error = "Failed to reset password. Please try again.";
                    error_log("Password reset FAILED: " . $e->getMessage());
                }
            }
        }
    }
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - CampusCar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            border: none;
        }
        .card-header {
            background: linear-gradient(135deg, #4CAF50 0%, #2E7D32 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 25px;
            text-align: center;
        }
        .btn-success {
            background: linear-gradient(135deg, #4CAF50 0%, #2E7D32 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
        }
        .btn-success:hover {
            background: linear-gradient(135deg, #45a049 0%, #1B5E20 100%);
            transform: translateY(-2px);
            transition: all 0.3s;
        }
        .debug-info {
            font-size: 12px;
            color: #666;
            background: #f8f9fa;
            border-radius: 5px;
            padding: 10px;
            margin-top: 10px;
            display: none; /* Sembunyikan di production */
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-car"></i> CampusCar</h3>
                        <h4 class="mt-2"><i class="fas fa-lock"></i> Reset Password</h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> 
                                <strong><?php echo $error; ?></strong>
                                <div class="mt-2">
                                    <small>
                                        If you're having issues:
                                        <ul class="mb-0 mt-1">
                                            <li>Make sure you copied the entire link from email</li>
                                            <li>The link expires in 1 hour</li>
                                            <li>Each link can only be used once</li>
                                        </ul>
                                    </small>
                                </div>
                            </div>
                            <div class="text-center mt-3">
                                <a href="forgot_password.php" class="btn btn-success">
                                    <i class="fas fa-redo"></i> Request New Reset Link
                                </a>
                            </div>
                        <?php elseif ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> 
                                <strong><?php echo $success; ?></strong>
                            </div>
                            <div class="text-center mt-3">
                                <a href="login.php" class="btn btn-success">
                                    <i class="fas fa-sign-in-alt"></i> Go to Login
                                </a>
                            </div>
                        <?php elseif ($show_form): ?>
                            <p class="text-muted mb-4">
                                Hello <strong><?php echo htmlspecialchars($reset_data['FullName']); ?></strong>, 
                                please enter your new password below.
                            </p>
                            
                            <form method="POST" action="" id="resetForm">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-key"></i> New Password
                                    </label>
                                    <input type="password" class="form-control" 
                                           name="password" 
                                           placeholder="Enter new password"
                                           required 
                                           minlength="6"
                                           autofocus>
                                    <div class="form-text">Minimum 6 characters</div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-key"></i> Confirm New Password
                                    </label>
                                    <input type="password" class="form-control" 
                                           name="confirm_password" 
                                           placeholder="Confirm new password"
                                           required 
                                           minlength="6">
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="fas fa-sync-alt"></i> Reset Password
                                    </button>
                                    <a href="forgot_password.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-arrow-left"></i> Cancel
                                    </a>
                                </div>
                            </form>
                            
                            <!-- Password strength indicator (hidden by default) -->
                            <div id="password-strength" class="mt-2" style="display: none;"></div>
                            
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-circle"></i> 
                                <strong>Invalid reset token</strong>
                            </div>
                            <div class="text-center">
                                <a href="forgot_password.php" class="btn btn-success">
                                    Request New Reset Link
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Debug info for testing -->
                        <?php if (isset($_GET['debug'])): ?>
                        <div class="debug-info mt-3">
                            <strong>Debug Info:</strong><br>
                            Token: <?php echo isset($token) ? substr($token, 0, 20) . '...' : 'none'; ?><br>
                            Token Length: <?php echo isset($token) ? strlen($token) : '0'; ?><br>
                            Show Form: <?php echo $show_form ? 'Yes' : 'No'; ?><br>
                            Time: <?php echo date('Y-m-d H:i:s'); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer text-center text-muted">
                        <small>Having issues? Contact: campuscar.team@gmail.com</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password strength indicator
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.querySelector('input[name="password"]');
            const confirmInput = document.querySelector('input[name="confirm_password"]');
            const strengthDiv = document.getElementById('password-strength');
            
            if (passwordInput && strengthDiv) {
                passwordInput.addEventListener('input', function() {
                    const password = this.value;
                    
                    if (password.length === 0) {
                        strengthDiv.style.display = 'none';
                        return;
                    }
                    
                    strengthDiv.style.display = 'block';
                    
                    let strength = 0;
                    let message = '';
                    let color = '#dc3545';
                    
                    // Length check
                    if (password.length >= 6) strength++;
                    if (password.length >= 8) strength++;
                    
                    // Complexity checks
                    if (/[A-Z]/.test(password)) strength++;
                    if (/[a-z]/.test(password)) strength++;
                    if (/\d/.test(password)) strength++;
                    if (/[^A-Za-z0-9]/.test(password)) strength++;
                    
                    if (strength <= 2) {
                        message = 'Weak';
                        color = '#dc3545';
                    } else if (strength <= 4) {
                        message = 'Medium';
                        color = '#ffc107';
                    } else {
                        message = 'Strong';
                        color = '#28a745';
                    }
                    
                    strengthDiv.innerHTML = `<span style="color: ${color}; font-weight: bold;">${message}</span> password`;
                });
                
                // Password match check
                if (confirmInput) {
                    confirmInput.addEventListener('input', function() {
                        if (passwordInput.value !== this.value && this.value.length > 0) {
                            this.style.borderColor = '#dc3545';
                        } else {
                            this.style.borderColor = '#ced4da';
                        }
                    });
                }
            }
        });
    </script>
</body>
</html>