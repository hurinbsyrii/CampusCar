<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if user is a driver
$servername = "127.0.0.1:3301";
$username = "root";
$password = "";
$dbname = "campuscar";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];
$is_driver = false;
$driver_id = null;
$user_gender = '';
$has_existing_rides = false;
$existing_rides = [];

// Get user gender
$user_sql = "SELECT Gender FROM user WHERE UserID = ?";
$stmt = $conn->prepare($user_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
if ($user_result->num_rows > 0) {
    $user_data = $user_result->fetch_assoc();
    $user_gender = $user_data['Gender'];
}
$stmt->close();

// Check if user is a driver
$driver_check_sql = "SELECT DriverID FROM driver WHERE UserID = ?";
$stmt = $conn->prepare($driver_check_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $is_driver = true;
    $driver_data = $result->fetch_assoc();
    $driver_id = $driver_data['DriverID'];
    
    // Get driver's existing active rides for today and future dates
    $current_date = date('Y-m-d');
    $rides_sql = "SELECT RideID, FromLocation, ToLocation, RideDate, DepartureTime, Status 
                  FROM rides 
                  WHERE DriverID = ? 
                  AND RideDate >= ?
                  AND Status IN ('available', 'in_progress')
                  ORDER BY RideDate, DepartureTime";
    
    $rides_stmt = $conn->prepare($rides_sql);
    $rides_stmt->bind_param("is", $driver_id, $current_date);
    $rides_stmt->execute();
    $rides_result = $rides_stmt->get_result();
    
    if ($rides_result->num_rows > 0) {
        $has_existing_rides = true;
        while ($ride = $rides_result->fetch_assoc()) {
            $existing_rides[] = $ride;
        }
    }
    $rides_stmt->close();
}
$stmt->close();
$conn->close();

if (!$is_driver) {
    header("Location: userdashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offer a Ride - CampusCar</title>
    <link rel="stylesheet" href="../css/rideoffer.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .notification-toast {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            min-width: 300px;
            max-width: 400px;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .schedule-conflict {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .existing-rides-list {
            margin-top: 10px;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .existing-ride-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 12px;
            margin-bottom: 5px;
            background: white;
            border-radius: 6px;
            border-left: 4px solid #3498db;
        }
        
        .existing-ride-item.cancelled {
            border-left-color: #e74c3c;
            opacity: 0.7;
        }
        
        .existing-ride-item.completed {
            border-left-color: #2ecc71;
            opacity: 0.7;
        }
        
        .ride-time {
            font-weight: bold;
            color: #2c3e50;
        }
        
        .ride-location {
            font-size: 0.9em;
            color: #7f8c8d;
        }
        
        .ride-status {
            font-size: 0.8em;
            padding: 2px 8px;
            border-radius: 12px;
            background: #ecf0f1;
        }
        
        .status-available {
            background: #d4edda;
            color: #155724;
        }
        
        .status-in_progress {
            background: #cce5ff;
            color: #004085;
        }

        .autocomplete-container {
    position: relative;
}

.autocomplete-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #ddd;
    border-radius: 0 0 8px 8px;
    max-height: 250px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.autocomplete-item {
    padding: 10px 15px;
    cursor: pointer;
    transition: background 0.2s;
    border-bottom: 1px solid #f0f0f0;
    display: flex;
    align-items: center;
}

.autocomplete-item:last-child {
    border-bottom: none;
}

.autocomplete-item:hover {
    background: #f5f5f5;
}

.autocomplete-item i {
    margin-right: 10px;
    color: #3498db;
    width: 20px;
}

.btn-small {
    padding: 8px 16px;
    background: var(--primary-color);
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.9em;
    transition: background 0.3s;
}

.btn-small:hover {
    background: var(--primary-color-dark);
}
    </style>
</head>

<body>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="notification-toast">
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?php echo $_SESSION['error']; ?>
                <button type="button" class="close" onclick="this.parentElement.remove()">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['warning'])): ?>
        <div class="notification-toast">
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <?php echo $_SESSION['warning']; ?>
                <button type="button" class="close" onclick="this.parentElement.remove()">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
        </div>
        <?php unset($_SESSION['warning']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="notification-toast">
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-circle-check"></i>
                <?php echo $_SESSION['success']; ?>
                <button type="button" class="close" onclick="this.parentElement.remove()">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <div class="offer-container">
        <div class="offer-card">
            <div class="offer-header">
                <div class="logo">
                    <i class="fa-solid fa-car-side"></i>
                    <span>CampusCar</span>
                </div>
                <h1>Offer a Ride</h1>
                <p>Share your journey and help fellow students travel around campus</p>
                
                <?php if ($has_existing_rides): ?>
                    <div class="schedule-conflict">
                        <h4><i class="fa-solid fa-calendar-check"></i> Your Upcoming Rides</h4>
                        <p>Please check your schedule to avoid conflicts:</p>
                        <div class="existing-rides-list">
                            <?php foreach ($existing_rides as $ride): ?>
                                <div class="existing-ride-item <?php echo strtolower($ride['Status']); ?>">
                                    <div>
                                        <div class="ride-time">
                                            <?php echo date('d M Y', strtotime($ride['RideDate'])) . ' at ' . date('h:i A', strtotime($ride['DepartureTime'])); ?>
                                        </div>
                                        <div class="ride-location">
                                            <?php echo $ride['FromLocation'] . ' → ' . $ride['ToLocation']; ?>
                                        </div>
                                    </div>
                                    <span class="ride-status status-<?php echo $ride['Status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $ride['Status'])); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <small><i class="fa-solid fa-info-circle"></i> You cannot offer another ride on the same date and time.</small>
                    </div>
                <?php endif; ?>
            </div>

            <form id="rideOfferForm" action="../database/rideofferdb.php" method="POST" class="offer-form">
                <!-- Route Information -->
                <div class="form-section">
                    <h3><i class="fa-solid fa-route"></i> Route Information</h3>
                    <div class="form-row">
                        <div class="form-group">
    <label for="fromLocation">
        <i class="fa-solid fa-location-dot"></i>
        From Location *
    </label>
    <div class="input-group autocomplete-container">
        <input type="text" id="fromLocation" name="fromLocation" required
               placeholder="e.g., Mahkota Parade, KL Sentral, Library"
               class="location-autocomplete">
        <input type="hidden" id="fromLocationLat" name="fromLocationLat">
        <input type="hidden" id="fromLocationLng" name="fromLocationLng">
        <div class="autocomplete-dropdown" id="fromAutocompleteDropdown"></div>
    </div>
    <small class="help-text">Starting point of your ride</small>
    <small class="error-text" id="fromLocationError"></small>
</div>

<div class="form-group">
    <label for="toLocation">
        <i class="fa-solid fa-location-dot"></i>
        To Location *
    </label>
    <div class="input-group autocomplete-container">
        <input type="text" id="toLocation" name="toLocation" required
               placeholder="e.g., University Campus, Faculty Building"
               class="location-autocomplete">
        <input type="hidden" id="toLocationLat" name="toLocationLat">
        <input type="hidden" id="toLocationLng" name="toLocationLng">
        <div class="autocomplete-dropdown" id="toAutocompleteDropdown"></div>
    </div>
    <small class="help-text">Destination of your ride</small>
    <small class="error-text" id="toLocationError"></small>
</div>

<!-- Map Preview Container -->
<div id="mapPreviewContainer" style="display: none; margin-top: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
        <h4><i class="fa-solid fa-map"></i> Route Preview</h4>
        <button type="button" id="confirmRoute" class="btn-small" style="display: none;">
            <i class="fa-solid fa-check"></i> Confirm Route
        </button>
    </div>
    <div id="map" style="height: 300px; border-radius: 8px;"></div>
</div>
                    </div>
                </div>

                <!-- Date & Time -->
                <div class="form-section">
                    <h3><i class="fa-solid fa-clock"></i> Schedule</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="rideDate">
                                <i class="fa-solid fa-calendar"></i>
                                Date *
                            </label>
                            <div class="input-group">
                                <input type="date" id="rideDate" name="rideDate" required
                                    min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <small class="help-text">Select the ride date</small>
                            <small class="error-text" id="rideDateError"></small>
                        </div>

                        <div class="form-group">
                            <label for="departureTime">
                                <i class="fa-solid fa-clock"></i>
                                Departure Time *
                            </label>
                            <div class="input-group">
                                <input type="time" id="departureTime" name="departureTime" required>
                            </div>
                            <small class="help-text">When you plan to depart</small>
                            <small class="error-text" id="departureTimeError"></small>
                        </div>
                    </div>
                </div>

                <!-- Ride Details -->
                <div class="form-section">
                    <h3><i class="fa-solid fa-car"></i> Ride Details</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="availableSeats">
                                <i class="fa-solid fa-user-friends"></i>
                                Available Pax *
                            </label>
                            <div class="input-group">
                                <input type="number" id="availableSeats" name="availableSeats" required
                                    min="1" max="7" value="1">
                            </div>
                            <small class="help-text">Number of available pax (1-7)</small>
                            <small class="error-text" id="availableSeatsError"></small>
                        </div>

                        <div class="form-group">
                            <label for="pricePerSeat">
                                <i class="fa-solid fa-money-bill-wave"></i>
                                Price per Pax (RM) *
                            </label>
                            <div class="input-group">
                                <input type="number" id="pricePerSeat" name="pricePerSeat" required
                                    min="1" step="0.01" placeholder="0.00">
                            </div>
                            <small class="help-text">Price per passenger in RM</small>
                            <small class="error-text" id="pricePerSeatError"></small>
                        </div>
                    </div>

                    <!-- Girls Only Checkbox -->
                    <div class="form-group" style="margin-top: 20px;">
                        <div class="checkbox-group">
                            <input type="checkbox" id="femaleOnly" name="femaleOnly" value="1"
                                <?php echo ($user_gender === 'female') ? '' : 'disabled'; ?>>
                            <label for="femaleOnly" class="checkbox-label">
                                <i class="fa-solid fa-venus"></i>
                                <span>Girls Only Ride</span>
                                <?php if ($user_gender !== 'female'): ?>
                                    <small class="help-text" style="color: var(--error-color); margin-left: 10px;">
                                        (Available for female drivers only)
                                    </small>
                                <?php endif; ?>
                            </label>
                        </div>
                        <small class="help-text">
                            Only female passengers can book this ride.
                            <?php if ($user_gender === 'female'): ?>
                                Available for you as a female driver.
                            <?php endif; ?>
                        </small>
                    </div>
                </div>

                <!-- Additional Information -->
                <div class="form-section">
                    <h3><i class="fa-solid fa-info-circle"></i> Additional Information</h3>
                    <div class="form-group">
                        <label for="rideDescription">
                            <i class="fa-solid fa-comment"></i>
                            Ride Description (Optional)
                        </label>
                        <div class="input-group">
                            <textarea id="rideDescription" name="rideDescription" rows="4"
                                placeholder="Any additional information about the ride, meeting point, car details, or special instructions..."></textarea>
                        </div>
                        <small class="help-text">Help passengers know what to expect</small>
                    </div>
                </div>

                <!-- Quick Tips -->
                <div class="tips-section">
                    <h4><i class="fa-solid fa-lightbulb"></i> Tips for a Great Ride Offer</h4>
                    <div class="tips-grid">
                        <div class="tip-item">
                            <i class="fa-solid fa-clock"></i>
                            <span>Be punctual with your departure time</span>
                        </div>
                        <div class="tip-item">
                            <i class="fa-solid fa-map-marker-alt"></i>
                            <span>Specify clear meeting points</span>
                        </div>
                        <div class="tip-item">
                            <i class="fa-solid fa-car"></i>
                            <span>Mention your car model and color</span>
                        </div>
                        <div class="tip-item">
                            <i class="fa-solid fa-comments"></i>
                            <span>Communicate with passengers</span>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" onclick="goBack()" class="btn-secondary">
                        <i class="fa-solid fa-arrow-left"></i>
                        Back to Dashboard
                    </button>
                    <button type="submit" class="btn-primary" id="submitBtn">
                        <i class="fa-solid fa-car-side"></i>
                        Offer Ride
                    </button>
                </div>
            </form>
        </div>

        <div class="offer-info">
            <div class="info-card">
                <h3><i class="fa-solid fa-chart-line"></i> Why Offer Rides?</h3>
                <ul class="benefits-list">
                    <li>
                        <i class="fa-solid fa-coins"></i>
                        <div>
                            <strong>Earn Extra Income</strong>
                            <span>Share fuel costs and earn money</span>
                        </div>
                    </li>
                    <li>
                        <i class="fa-solid fa-users"></i>
                        <div>
                            <strong>Meet New People</strong>
                            <span>Connect with fellow students</span>
                        </div>
                    </li>
                    <li>
                        <i class="fa-solid fa-leaf"></i>
                        <div>
                            <strong>Eco-Friendly</strong>
                            <span>Reduce carbon footprint</span>
                        </div>
                    </li>
                    <li>
                        <i class="fa-solid fa-star"></i>
                        <div>
                            <strong>Build Reputation</strong>
                            <span>Get ratings and reviews</span>
                        </div>
                    </li>
                </ul>
            </div>

            <div class="stats-card">
                <h4><i class="fa-solid fa-chart-bar"></i> Ride Statistics</h4>
                <div class="stats-grid">
                    <div class="stat-item">
                        <i class="fa-solid fa-car"></i>
                        <div class="stat-info">
                            <span class="stat-number">85%</span>
                            <span class="stat-label">Ride Completion Rate</span>
                        </div>
                    </div>
                    <div class="stat-item">
                        <i class="fa-solid fa-user-check"></i>
                        <div class="stat-info">
                            <span class="stat-number">4.8/5</span>
                            <span class="stat-label">Average Rating</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="schedule-rules-card">
                <h4><i class="fa-solid fa-calendar-times"></i> Schedule Rules</h4>
                <ul class="rules-list">
                    <li>
                        <i class="fa-solid fa-ban"></i>
                        <span>Cannot offer multiple rides on same date & time</span>
                    </li>
                    <li>
                        <i class="fa-solid fa-clock"></i>
                        <span>Minimum 2 hours gap between rides recommended</span>
                    </li>
                    <li>
                        <i class="fa-solid fa-calendar-check"></i>
                        <span>Check your existing rides before offering</span>
                    </li>
                    <li>
                        <i class="fa-solid fa-exclamation-triangle"></i>
                        <span>Conflicts will be automatically prevented</span>
                    </li>
                </ul>
            </div>

            <div class="support-card">
                <h4><i class="fa-solid fa-headset"></i> Need Help?</h4>
                <p>Contact our support team for assistance with ride offerings.</p>
                <div class="contact-info">
                    <p><i class="fa-solid fa-phone"></i> 06-123 4567</p>
                    <p><i class="fa-solid fa-envelope"></i> campuscar.team@gmail.com</p>
                </div>
            </div>
        </div>
    </div>

    <script src="../js/rideoffer.js?v=<?php echo time(); ?>"></script>
    <script>
        // Remove notification after 5 seconds
        setTimeout(() => {
            const notifications = document.querySelectorAll('.notification-toast');
            notifications.forEach(notification => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            });
        }, 5000);
    </script>
</body>

</html>