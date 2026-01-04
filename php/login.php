<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CampusCar</title>
    <link rel="stylesheet" href="../css/login.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo">
                    <i class="fa-solid fa-car-side"></i>
                    <span>CampusCar</span>
                </div>
                <h1>Welcome Back</h1>
                <p>Sign in to your CampusCar account</p>
            </div>

            <form id="loginForm" class="login-form">
                <div class="form-group">
                    <label for="username">Username *</label>
                    <div class="input-group">
                        <i class="fa-solid fa-user"></i>
                        <input type="text" id="username" name="username" placeholder="Enter your username" required>
                    </div>
                    <small class="help-text">Enter your registered username</small>
                </div>

                <div class="form-group">
                    <label for="password">Password *</label>
                    <div class="input-group">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                        <button type="button" class="toggle-password" id="togglePassword">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                    <small class="help-text">Enter your password</small>
                </div>

                <div class="form-options">
                    <!-- <div class="remember-me">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember">Remember me</label>
                    </div> -->
                    <a href="forgot_password.php" class="forgot-password">Forgot Password?</a>
                </div>

                <button type="submit" class="login-btn" id="loginBtn">
                    <i class="fa-solid fa-right-to-bracket"></i>
                    Sign In
                </button>

                <div class="login-footer">
                    <p>Don't have an account? <a href="verify.php" class="link">Sign Up</a></p>
                    <a href="home.php" class="back-link">
                        <i class="fa-solid fa-arrow-left"></i> Back to Home
                    </a>
                </div>
            </form>
        </div>

        <div class="login-info">
            <h3>Welcome to CampusCar</h3>
            <ul>
                <li><i class="fa-solid fa-shield-check"></i> Secure student verification</li>
                <li><i class="fa-solid fa-car"></i> Reliable campus transportation</li>
                <li><i class="fa-solid fa-users"></i> Connect with fellow students</li>
                <li><i class="fa-solid fa-clock"></i> 24/7 ride availability</li>
                <li><i class="fa-solid fa-money-bill-wave"></i> Affordable student rates</li>
            </ul>

            <div class="stats-info">
                <h4>Join Our Community</h4>
                <div class="stats">
                    <div class="stat-item">
                        <i class="fa-solid fa-users"></i>
                        <span>500+ Students</span>
                    </div>
                    <div class="stat-item">
                        <i class="fa-solid fa-car"></i>
                        <span>200+ Rides Daily</span>
                    </div>
                </div>
            </div>

            <div class="support-info">
                <h4>Need Help?</h4>
                <p><i class="fa-solid fa-phone"></i> CampusCar Support: 06-123 4567</p>
                <p><i class="fa-solid fa-envelope"></i> campuscar.team@gmail.com</p>
            </div>
        </div>
    </div>

    <script src="../js/login.js?v=<?php echo time(); ?>"></script>
</body>

</html>