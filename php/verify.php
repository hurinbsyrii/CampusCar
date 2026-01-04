<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Verification - CampusCar</title>
    <link rel="stylesheet" href="../css/verify.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    
    <div class="verification-container">
        <div class="verification-card">
            <div class="verification-header">
                <div class="logo">
                    <i class="fa-solid fa-car-side"></i>
                    <span>CampusCar</span>
                </div>
                <h1>Student Verification</h1>
                <p>Verify your UTEM student status to access CampusCar services</p>
            </div>

            <form id="verificationForm" class="verification-form">
                <div class="form-group">
                    <label for="matricNo">Matric Number</label>
                    <div class="input-group">
                        <i class="fa-solid fa-id-card"></i>
                        <input type="text" id="matricNo" name="matricNo" placeholder="e.g., D032310209" required>
                    </div>
                    <small class="help-text">Format: 1 letters followed by 9 numbers</small>
                    <small id="matricError" class="error-text"></small>
                </div>

                <div class="form-group">
                    <label for="icNo">IC Number</label>
                    <div class="input-group">
                        <i class="fa-solid fa-fingerprint"></i>
                        <input type="text" id="icNo" name="icNo" placeholder="e.g., 050618050196" required maxlength="12">
                    </div>
                    <small class="help-text">12-digit number without dashes</small>
                    <small id="icError" class="error-text"></small>
                </div>

                <div class="form-group checkbox-group">
                    <input type="checkbox" id="consent" name="consent" required>
                    <label for="consent">
                        I consent to CampusCar verifying my student status with UTEM
                    </label>
                </div>

                <button type="submit" class="verify-btn" id="verifyBtn">
                    <i class="fa-solid fa-shield-check"></i>
                    Verify Student Status
                </button>
            </form>

            <div id="resultMessage" class="result-message hidden">
                <div class="result-icon">
                    <i class="fa-solid fa-check-circle success"></i>
                    <i class="fa-solid fa-times-circle error"></i>
                    <i class="fa-solid fa-exclamation-triangle warning"></i>
                </div>
                <h3 id="resultTitle">Verification Result</h3>
                <p id="resultText">Your verification result will appear here.</p>
                <button id="tryAgainBtn" class="try-again-btn">Verify Another Student</button>
            </div>

            <div class="verification-footer">
                <p><i class="fa-solid fa-lock"></i> Your information is secure and encrypted</p>
                <a href="../php/home.php" class="back-link">
                    <i class="fa-solid fa-arrow-left"></i> Back to Home
                </a>
            </div>
        </div>

        <div class="verification-info">
            <h3>Why Verification is Required?</h3>
            <ul>
                <li><i class="fa-solid fa-shield"></i> Ensures only UTEM students use the platform</li>
                <li><i class="fa-solid fa-user-check"></i> Creates a trusted community</li>
                <li><i class="fa-solid fa-car"></i> Enables access to campus-specific features</li>
                <li><i class="fa-solid fa-handshake"></i> Builds accountability among users</li>
            </ul>

            <div class="support-info">
                <h4>Need Help?</h4>
                <p><i class="fa-solid fa-phone"></i> Student Affairs: 06-123 4567</p>
                <p><i class="fa-solid fa-envelope"></i> campuscar.team@gmail.com</p>
                <p><i class="fa-solid fa-clock"></i> Mon-Fri: 8:30AM - 5:30PM</p>
            </div>
        </div>

        <script src="../js/verify.js?v=<?php echo time(); ?>"></script>
</body>

</html>