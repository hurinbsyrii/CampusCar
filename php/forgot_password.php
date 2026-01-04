<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include konfigurasi email
require_once 'email_config.php';

// Database connection
$host = "127.0.0.1:3301";
$username = "root";
$password = "";
$database = "campuscar";

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Buat tabel password_reset jika belum ada
$conn->query("
    CREATE TABLE IF NOT EXISTS `password_reset` (
        `ResetID` int(11) NOT NULL AUTO_INCREMENT,
        `UserID` int(11) NOT NULL,
        `Token` varchar(255) NOT NULL,
        `ExpiryDateTime` datetime NOT NULL,
        `IsUsed` tinyint(1) DEFAULT 0,
        `CreatedAt` datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`ResetID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $error = "Please enter your email address";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } else {
        // Cek apakah email terdaftar
        $stmt = $conn->prepare("SELECT UserID, FullName, Email FROM user WHERE Email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Generate token reset (64 karakter hex)
            $token = bin2hex(random_bytes(32));
            
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Hapus token lama
            $delete_stmt = $conn->prepare("DELETE FROM password_reset WHERE UserID = ?");
            $delete_stmt->bind_param("i", $user['UserID']);
            $delete_stmt->execute();
            $delete_stmt->close();
            
            // Simpan token baru
            $stmt = $conn->prepare("INSERT INTO password_reset (UserID, Token, ExpiryDateTime) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $user['UserID'], $token, $expiry);
            
            if ($stmt->execute()) {
                // Kirim email menggunakan fungsi dari email_config.php
                if (sendResetEmail($email, $user['FullName'], $token)) {
                    $success = "Password reset link has been sent! Check your inbox and spam folder.";
                } else {
                    $error = "Failed to send email. Please try again.";
                }
            } else {
                $error = "Database error: " . $conn->error;
            }
            $stmt->close();
        } else {
            $error = "Email not found in our system";
        }
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - CampusCar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #7c9bc9;
            --secondary-color: #a8d5ba;
            --accent-color: #f9c5bd;
            --success-color: #28a745;
            --error-color: #dc3545;
            --warning-color: #ffc107;
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --text-color: #2d3748;
            --border-radius: 16px;
            --box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
            --gradient-primary: linear-gradient(135deg, #7c9bc9 0%, #a8d5ba 100%);
            --gradient-accent: linear-gradient(135deg, #f9c5bd 0%, #ffe8e4 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        body {
            background: var(--bg-color);
            color: var(--text-color);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        /* Background decorative elements */
        .bg-decoration {
            position: fixed;
            z-index: -1;
        }

        .circle-1 {
            width: 300px;
            height: 300px;
            background: var(--gradient-accent);
            border-radius: 50%;
            position: fixed;
            top: -150px;
            right: -150px;
            opacity: 0.15;
        }

        .circle-2 {
            width: 200px;
            height: 200px;
            background: var(--gradient-primary);
            border-radius: 50%;
            position: fixed;
            bottom: -100px;
            left: -100px;
            opacity: 0.1;
        }

        .wave {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 150px;
            background: linear-gradient(180deg, transparent, rgba(124, 155, 201, 0.05));
            clip-path: path('M0,100 C150,200 350,0 500,100 S850,300 1000,100 S1350,0 1500,100 L1500,200 L0,200 Z');
        }

        /* Main container */
        .reset-container {
            width: 100%;
            max-width: 460px;
            z-index: 10;
            animation: fadeInUp 0.6s ease-out;
        }

        /* Logo/Header */
        .reset-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo-container {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 80px;
            height: 80px;
            background: var(--gradient-primary);
            border-radius: 50%;
            margin-bottom: 20px;
            box-shadow: 0 10px 20px rgba(124, 155, 201, 0.2);
        }

        .logo-icon {
            font-size: 32px;
            color: white;
        }

        .reset-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .reset-header p {
            color: #6b7280;
            font-size: 15px;
            line-height: 1.5;
        }

        /* Card */
        .reset-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 40px;
            position: relative;
            overflow: hidden;
        }

        .reset-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--gradient-primary);
        }

        /* Alert messages */
        .alert {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease;
            border: 1px solid transparent;
        }

        .alert-danger {
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.08), rgba(220, 53, 69, 0.04));
            border-color: rgba(220, 53, 69, 0.15);
            color: var(--error-color);
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.08), rgba(40, 167, 69, 0.04));
            border-color: rgba(40, 167, 69, 0.15);
            color: var(--success-color);
        }

        .alert-icon {
            font-size: 20px;
            flex-shrink: 0;
        }

        /* Form */
        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-color);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-container {
            position: relative;
        }

        .form-input {
            width: 100%;
            padding: 16px 20px 16px 48px;
            border: 2px solid #e6e9ee;
            border-radius: 12px;
            font-size: 16px;
            background: transparent;
            color: var(--text-color);
            transition: var(--transition);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(124, 155, 201, 0.1);
        }

        .form-input::placeholder {
            color: #9ca3af;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 18px;
            transition: var(--transition);
        }

        .form-input:focus + .input-icon {
            color: var(--primary-color);
        }

        /* Submit button */
        .submit-btn {
            width: 100%;
            padding: 18px;
            background: var(--gradient-primary);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(124, 155, 201, 0.3);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .submit-btn::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: 0.5s;
        }

        .submit-btn:hover::after {
            left: 100%;
        }

        /* Links */
        .links {
            display: flex;
            justify-content: space-between;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e6e9ee;
        }

        .link-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: var(--transition);
        }

        .link-item:hover {
            color: #6a8ab5;
            transform: translateX(4px);
        }

        .link-icon {
            font-size: 14px;
        }

        /* Success state */
        .success-content {
            text-align: center;
            padding: 40px 0;
        }

        .success-icon {
            font-size: 64px;
            color: var(--success-color);
            margin-bottom: 24px;
            animation: bounce 1s ease;
        }

        .success-message {
            font-size: 18px;
            font-weight: 500;
            margin-bottom: 16px;
            color: var(--text-color);
        }

        .success-note {
            color: #6b7280;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 32px;
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            60% {
                transform: translateY(-5px);
            }
        }

        /* Responsive */
        @media (max-width: 576px) {
            .reset-container {
                max-width: 100%;
            }
            
            .reset-card {
                padding: 30px 24px;
            }
            
            .reset-header h1 {
                font-size: 24px;
            }
            
            .links {
                flex-direction: column;
                gap: 16px;
            }
        }

        /* Loading animation */
        .loading {
            display: none;
        }

        .loading.active {
            display: block;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Background decorations -->
    <div class="bg-decoration circle-1"></div>
    <div class="bg-decoration circle-2"></div>
    <div class="bg-decoration wave"></div>

    <div class="reset-container">
        <div class="reset-header">
            <div class="logo-container">
                <i class="fas fa-car logo-icon"></i>
            </div>
            <h1>Reset Your Password</h1>
            <p>Enter your email address and we'll send you a link to reset your password</p>
        </div>

        <div class="reset-card">
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle alert-icon"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-content">
                    <i class="fas fa-check-circle success-icon"></i>
                    <p class="success-message">Email Sent Successfully!</p>
                    <p class="success-note">We've sent a password reset link to your email address. The link will expire in 1 hour.</p>
                    <div class="links">
                        <a href="login.php" class="link-item">
                            <i class="fas fa-arrow-left link-icon"></i>
                            <span>Back to Login</span>
                        </a>
                        <!-- <a href="mailto:" class="link-item">
                            <i class="fas fa-envelope link-icon"></i>
                            <span>Open Email</span>
                        </a> -->
                    </div>
                </div>
            <?php else: ?>
                <form method="POST" action="" id="resetForm">
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <div class="input-container">
                            <input 
                                type="email" 
                                name="email" 
                                class="form-input" 
                                placeholder="name@example.com" 
                                required
                            >
                            <i class="fas fa-envelope input-icon"></i>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn" id="submitBtn">
                        <span id="btnText">Send Reset Link</span>
                        <div class="loading" id="loadingSpinner"></div>
                        <i class="fas fa-paper-plane" id="btnIcon"></i>
                    </button>

                    <div class="links">
                        <a href="login.php" class="link-item">
                            <i class="fas fa-arrow-left link-icon"></i>
                            <span>Back to Login</span>
                        </a>
                        <!-- <a href="mailto:campuscar.team@gmail.com" class="link-item">
                            <i class="fas fa-question-circle link-icon"></i>
                            <span>Need Help?</span>
                        </a> -->
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Form submission animation
        document.getElementById('resetForm')?.addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            const btnIcon = document.getElementById('btnIcon');
            const loadingSpinner = document.getElementById('loadingSpinner');
            
            // Show loading state
            btnText.textContent = 'Sending...';
            btnIcon.style.display = 'none';
            loadingSpinner.classList.add('active');
            submitBtn.disabled = true;
            
            // Revert after 5 seconds (in case form takes too long)
            setTimeout(() => {
                btnText.textContent = 'Send Reset Link';
                btnIcon.style.display = 'block';
                loadingSpinner.classList.remove('active');
                submitBtn.disabled = false;
            }, 5000);
        });

        // Input focus effect
        const inputs = document.querySelectorAll('.form-input');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('focused');
            });
        });

        // Add subtle floating animation to header logo
        const logo = document.querySelector('.logo-container');
        let floatDirection = 1;
        let floatValue = 0;
        
        function floatLogo() {
            floatValue += 0.05 * floatDirection;
            if (floatValue > 2) floatDirection = -1;
            if (floatValue < -2) floatDirection = 1;
            
            logo.style.transform = `translateY(${floatValue}px)`;
            requestAnimationFrame(floatLogo);
        }
        
        // Start floating animation
        floatLogo();
    </script>
</body>
</html>