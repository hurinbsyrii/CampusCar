<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - CampusCar</title>
    <link rel="stylesheet" href="../css/signup.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
    <div class="signup-container">
        <div class="signup-card">
            <div class="signup-header">
                <div class="logo">
                    <i class="fa-solid fa-car-side"></i>
                    <span>CampusCar</span>
                </div>
                <h1>Create Your Account</h1>
                <p>Complete your profile to start using CampusCar</p>
            </div>

            <form id="signupForm" class="signup-form" action="../database/signupdb.php" method="POST">
                <!-- Student Information -->
                <div class="form-section">
                    <h3><i class="fa-solid fa-user-graduate"></i> Student Information</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="matricNo">Matric Number *</label>
                            <div class="input-group">
                                <i class="fa-solid fa-id-card"></i>
                                <input type="text" id="matricNo" name="matricNo" placeholder="D032310209" required readonly>
                            </div>
                            <small class="help-text">Verified student ID</small>
                        </div>

                        <div class="form-group">
                            <label for="icNo">IC Number *</label>
                            <div class="input-group">
                                <i class="fa-solid fa-fingerprint"></i>
                                <input type="text" id="icNo" name="icNo" placeholder="050618050196" required readonly>
                            </div>
                            <small class="help-text">12-digit without dashes</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="fullName">Full Name *</label>
                        <div class="input-group">
                            <i class="fa-solid fa-user"></i>
                            <input type="text" id="fullName" name="fullName" placeholder="Enter your full name" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="faculty">Faculty *</label>
                            <div class="input-group">
                                <i class="fa-solid fa-building"></i>
                                <select id="faculty" name="faculty" required>
                                    <option value="">Select Faculty</option>
                                    <option value="electronics">Faculty of Electronics and Computer Technology and Engineering</option>
                                    <option value="electrical">Faculty of Electrical Technology and Engineering</option>
                                    <option value="mechanical">Faculty of Mechanical Technology and Engineering</option>
                                    <option value="industrial">Faculty of Industrial and Manufacturing Technology and Engineering</option>
                                    <option value="ict">Faculty of Information And Communications Technology</option>
                                    <option value="management">Faculty of Technology Management And Technopreneurship</option>
                                    <option value="ai">Faculty of Artificial Intelligence and Cyber Security</option>
                                    <option value="language">Centre For Language Learning</option>
                                    <option value="entrepreneurship">Institute of Technology Management And Entrepreneurship</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="gender">Gender *</label>
                            <div class="input-group">
                                <i class="fa-solid fa-venus-mars"></i>
                                <select id="gender" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Account Information -->
                <div class="form-section">
                    <h3><i class="fa-solid fa-lock"></i> Account Information</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="username">Username *</label>
                            <div class="input-group">
                                <i class="fa-solid fa-at"></i>
                                <input type="text" id="username" name="username" placeholder="Choose a username" required>
                            </div>
                            <small class="help-text">4-20 characters, letters and numbers only</small>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <div class="input-group">
                                <i class="fa-solid fa-envelope"></i>
                                <input type="email" id="email" name="email" placeholder="your.email@example.com" required>
                            </div>
                            <small class="help-text">Use your university email if available</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone">Phone Number *</label>
                            <div class="input-group">
                                <i class="fa-solid fa-phone"></i>
                                <input type="tel" id="phone" name="phone" placeholder="01X-XXXX XXXX" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="password">Password *</label>
                            <div class="input-group">
                                <i class="fa-solid fa-key"></i>
                                <input type="password" id="password" name="password" placeholder="Create a password" required>
                                <button type="button" class="toggle-password" id="togglePassword">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                            <small class="help-text">Minimum 8 characters with uppercase, lowercase & number</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="confirmPassword">Confirm Password *</label>
                            <div class="input-group">
                                <i class="fa-solid fa-key"></i>
                                <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm your password" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Terms and Conditions -->
                <div class="form-group checkbox-group">
                    <input type="checkbox" id="terms" name="terms" required>
                    <label for="terms">
                        I agree to the <a href="#" class="link">Terms of Service</a> and <a href="#" class="link">Privacy Policy</a> *
                    </label>
                </div>

                <!-- <div class="form-group checkbox-group">
                    <input type="checkbox" id="newsletter" name="newsletter">
                    <label for="newsletter">
                        I want to receive updates about CampusCar services and promotions
                    </label>
                </div> -->

                <button type="submit" class="signup-btn" id="signupBtn">
                    <i class="fa-solid fa-user-plus"></i>
                    Create Account
                </button>
            </form>

            <div class="signup-footer">
                <p>Already have an account? <a href="login.html" class="link">Sign In</a></p>
                <a href="../php/home.php" class="back-link">
                    <i class="fa-solid fa-arrow-left"></i> Back to Home
                </a>
            </div>
        </div>

        <div class="signup-info">
            <h3>Why Join CampusCar?</h3>
            <ul>
                <li><i class="fa-solid fa-shield-check"></i> Verified student community</li>
                <li><i class="fa-solid fa-car"></i> Safe and reliable rides</li>
                <li><i class="fa-solid fa-money-bill-wave"></i> Affordable student rates</li>
                <li><i class="fa-solid fa-users"></i> Connect with campus mates</li>
                <li><i class="fa-solid fa-clock"></i> 24/7 support</li>
            </ul>

            <div class="progress-info">
                <h4>Account Setup Progress</h4>
                <div class="progress-bar">
                    <div class="progress-fill" id="progressFill"></div>
                </div>
                <p>Complete your profile to unlock all features</p>
            </div>

            <div class="support-info">
                <h4>Need Help?</h4>
                <p><i class="fa-solid fa-phone"></i> CampusCar Support: 06-123 4567</p>
                <p><i class="fa-solid fa-envelope"></i> campuscar.team@gmail.com</p>
            </div>
        </div>
    </div>

    <script src="../js/signup.js?v=<?php echo time(); ?>"></script>

</body>

</html>