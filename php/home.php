<?php
// Force light theme for home page (override any saved theme)
setcookie('theme', 'light', time() + (365 * 24 * 60 * 60), "/");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CampusCar - Ride Sharing for University</title>
    <link rel="stylesheet" href="../css/home.css?v=<?php echo time(); ?>">
    <script defer src="../js/home.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
    <header class="navbar">
        <div class="logo">
            <i class="fa-solid fa-car-side"></i>
            <span>CampusCar</span>
        </div>
        <nav>
            <a href="#" class="active">Home</a>
            <a href="#">Find Ride</a>
            <a href="#">About</a>
            <a href="#">Contact</a>
        </nav>
        <div class="nav-buttons">
            <!-- theme toggle removed -->
            <button class="login-btn" onclick="window.location.href='../php/login.php'">Login</button>
            <button class="signup-btn" onclick="window.location.href='../php/verify.php'">Sign Up</button>
        </div>
    </header>

    <!-- Mobile Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="#" class="nav-item active">
            <i class="fas fa-home"></i>
            <span>Home</span>
        </a>
        <a href="#" class="nav-item">
            <i class="fas fa-search"></i>
            <span>Find Ride</span>
        </a>
        <a href="#" class="nav-item">
            <i class="fas fa-info-circle"></i>
            <span>About</span>
        </a>
        <a href="#" class="nav-item">
            <i class="fas fa-user"></i>
            <span>Account</span>
        </a>
    </nav>

    <section class="hero">
        <div class="hero-content">
            <h1>Connect with <span class="highlight">Verified Drivers</span> for Your Daily Commute</h1>
            <p>Safe and reliable carpooling within your university. Join CampusCar's trusted community of verified drivers and student commuters.</p>
            <div class="hero-buttons">
                <button class="find-btn" onclick="window.location.href='verify.php';">
                    <i class="fa-solid fa-user-plus"></i> Start Your Jorney
                </button>
                <button class="join-btn" onclick="window.location.href='login.php';">
                    <i class="fa-solid fa-user-plus"></i> Already have an account?
                </button>
            </div>
        </div>
        <div class="hero-visual">
            <div class="floating-card card-1">
                <i class="fa-solid fa-graduation-cap"></i>
                <p>UTEM Students</p>
            </div>
            <div class="floating-card card-2">
                <i class="fa-solid fa-car-side"></i>
                <p>Reliable Rides</p>
            </div>
            <div class="floating-card card-3">
                <i class="fa-solid fa-wallet"></i>
                <p>Student Rates</p>
            </div>
            <div class="floating-card card-4">
                <i class="fa-solid fa-map-location-dot"></i>
                <p>Campus Routes</p>
            </div>
            <div class="floating-card card-5">
                <i class="fa-solid fa-users"></i>
                <p>Community</p>
            </div>
            <div class="floating-card card-6">
                <i class="fa-solid fa-mobile-screen-button"></i>
                <p>Easy Booking</p>
            </div>
        </div>
    </section>

    <section class="stats-section">
        <div class="container">
            <div class="stats">
                <div class="stat-box">
                    <i class="fa-solid fa-shield"></i>
                    <h3>100%</h3>
                    <p>Verified Drivers</p>
                </div>
                <div class="stat-box">
                    <i class="fa-solid fa-users"></i>
                    <h3>1000+</h3>
                    <p>Active Commuters</p>
                </div>
                <div class="stat-box">
                    <i class="fa-solid fa-headset"></i>
                    <h3>24/7</h3>
                    <p>Support</p>
                </div>
            </div>
        </div>
    </section>

    <section class="why-campuscar">
        <div class="container">
            <h2>Why Choose CampusCar?</h2>
            <p class="section-subtitle">We provide the safest, most reliable, and affordable carpooling solution for university students and staff.</p>

            <div class="features">
                <div class="feature-box">
                    <div class="feature-icon">
                        <i class="fa-solid fa-id-card"></i>
                    </div>
                    <h3>Verified Drivers</h3>
                    <p>All drivers undergo background checks and student ID verification to ensure a safe experience.</p>
                </div>
                <div class="feature-box">
                    <div class="feature-icon">
                        <i class="fa-solid fa-bolt"></i>
                    </div>
                    <h3>Quick Booking</h3>
                    <p>Find and book rides instantly using our smart matching system designed for university schedules.</p>
                </div>
                <div class="feature-box">
                    <div class="feature-icon">
                        <i class="fa-solid fa-money-bill-wave"></i>
                    </div>
                    <h3>Affordable Rates</h3>
                    <p>Student-friendly fares with cost-sharing options between drivers and riders.</p>
                </div>
                <div class="feature-box">
                    <div class="feature-icon">
                        <i class="fa-solid fa-people-group"></i>
                    </div>
                    <h3>Campus Community</h3>
                    <p>Connect with verified university students for trusted, safe, and friendly travel companions.</p>
                </div>
                <div class="feature-box">
                    <div class="feature-icon">
                        <i class="fa-solid fa-star"></i>
                    </div>
                    <h3>Rated Drivers</h3>
                    <p>Choose drivers with ratings and feedback from other students for peace of mind.</p>
                </div>
                <div class="feature-box">
                    <div class="feature-icon">
                        <i class="fa-solid fa-lock"></i>
                    </div>
                    <h3>Secure Platform</h3>
                    <p>Your data and trip details are encrypted and protected for your privacy.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="female-safety">
        <div class="container">
            <div class="safety-content">
                <div class="safety-icon"><i class="fa-solid fa-venus"></i></div>
                <h3>Special Focus on Female Safety</h3>
                <p>CampusCar understands the safety concerns of female students. We have verified female drivers and dedicated support for safe commutes across campus.</p>

                <div class="safety-stats">
                    <div>
                        <h4>24/7</h4>
                        <p>Female Support Helpline</p>
                    </div>
                    <div>
                        <h4>100%</h4>
                        <p>Background Verified</p>
                    </div>
                    <div>
                        <h4>50+</h4>
                        <p>Female Drivers</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="cta-section">
        <div class="container">
            <h2>Ready to Start Your Journey?</h2>
            <p>Join thousands of students who trust CampusCar for their daily commute.</p>
            <button class="cta-btn">Get Started Today</button>
        </div>
    </section>

    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">
                    <i class="fa-solid fa-car-side"></i>
                    <span>CampusCar</span>
                </div>
                <div class="footer-links">
                    <a href="#">Home</a>
                    <a href="#">Find Ride</a>
                    <a href="#">About</a>
                    <a href="#">Contact</a>
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms of Service</a>
                </div>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
            <p class="copyright">© 2025 CampusCar. All rights reserved.</p>
        </div>
    </footer>
</body>

</html>