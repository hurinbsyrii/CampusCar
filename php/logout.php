<?php
session_start();

// Store user info for potential future use
$user_name = $_SESSION['full_name'] ?? 'User';

// Destroy all session data
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Finally, destroy the session
session_destroy();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging Out - CampusCar</title>
    <link rel="stylesheet" href="../css/logout.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
    <div class="logout-container">
        <div class="logout-card">
            <!-- Animated Car -->
            <div class="car-animation">
                <div class="car">
                    <div class="car-body">
                        <div class="car-top"></div>
                        <div class="car-bottom"></div>
                        <div class="car-wheel front-wheel"></div>
                        <div class="car-wheel back-wheel"></div>
                        <div class="car-window"></div>
                        <div class="car-light"></div>
                    </div>
                </div>
                <div class="road"></div>
            </div>

            <div class="logout-content">
                <h1>Logging You Out</h1>
                <p>Thank you for using CampusCar!</p>

                <div class="loading-indicator">
                    <div class="loading-bar">
                        <div class="loading-progress"></div>
                    </div>
                    <span class="loading-text">Securing your session...</span>
                </div>
            </div>

            <div class="logout-message">
                <i class="fa-solid fa-shield-check"></i>
                <span>Your session has been securely ended</span>
            </div>
        </div>
    </div>

    <script src="../js/logout.js?v=<?php echo time(); ?>"></script>
</body>

</html>