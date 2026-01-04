<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../php/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Check whether this user has a driver record in the DB
$stmt = $conn->prepare("SELECT DriverID FROM driver WHERE UserID = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    // Not a driver — redirect back to user dashboard
    header('Location: userdashboard.php');
    exit();
}
$row = $res->fetch_assoc();
$driver_id = $row['DriverID'];

// Fetch comprehensive data for display
$data = [];

// Get driver details
$driver_query = $conn->prepare("
    SELECT d.*, u.* 
    FROM driver d 
    JOIN user u ON d.UserID = u.UserID 
    WHERE d.DriverID = ?
");
$driver_query->bind_param('i', $driver_id);
$driver_query->execute();
$driver_result = $driver_query->get_result();
$data['driver'] = $driver_result->fetch_assoc();

// Get rides data
$rides_query = $conn->prepare("
    SELECT * FROM rides 
    WHERE DriverID = ? 
    ORDER BY RideDate DESC, DepartureTime DESC
");
$rides_query->bind_param('i', $driver_id);
$rides_query->execute();
$data['rides'] = $rides_query->get_result()->fetch_all(MYSQLI_ASSOC);

// Get bookings for driver's rides
$ride_ids = array_column($data['rides'], 'RideID');
if (!empty($ride_ids)) {
    $placeholders = implode(',', array_fill(0, count($ride_ids), '?'));
    $bookings_query = $conn->prepare("
        SELECT b.*, u.FullName, u.Email, u.PhoneNumber 
        FROM booking b 
        JOIN user u ON b.UserID = u.UserID 
        WHERE b.RideID IN ($placeholders) 
        ORDER BY b.BookingDateTime DESC
    ");
    $bookings_query->bind_param(str_repeat('i', count($ride_ids)), ...$ride_ids);
    $bookings_query->execute();
    $data['bookings'] = $bookings_query->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $data['bookings'] = [];
}

// Get driver earnings
$earnings_query = $conn->prepare("
    SELECT de.*, r.FromLocation, r.ToLocation, b.TotalPrice, u.FullName as PassengerName 
    FROM driver_earnings de 
    JOIN rides r ON de.RideID = r.RideID 
    JOIN booking b ON de.BookingID = b.BookingID 
    JOIN user u ON b.UserID = u.UserID 
    WHERE de.DriverID = ? 
    ORDER BY de.PaymentDate DESC
");
$earnings_query->bind_param('i', $driver_id);
$earnings_query->execute();
$data['earnings'] = $earnings_query->get_result()->fetch_all(MYSQLI_ASSOC);

// Get notifications
$notifications_query = $conn->prepare("
    SELECT * FROM notifications 
    WHERE UserID = ? 
    ORDER BY CreatedAt DESC
");
$notifications_query->bind_param('i', $user_id);
$notifications_query->execute();
$data['notifications'] = $notifications_query->get_result()->fetch_all(MYSQLI_ASSOC);

// Get payments related to driver's rides
if (!empty($ride_ids)) {
    $payments_query = $conn->prepare("
        SELECT p.*, b.RideID, u.FullName 
        FROM payments p 
        JOIN booking b ON p.BookingID = b.BookingID 
        JOIN user u ON p.UserID = u.UserID 
        WHERE b.RideID IN ($placeholders) 
        ORDER BY p.PaymentDate DESC
    ");
    $payments_query->bind_param(str_repeat('i', count($ride_ids)), ...$ride_ids);
    $payments_query->execute();
    $data['payments'] = $payments_query->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $data['payments'] = [];
}

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Dashboard - CampusCar</title>
    <link rel="stylesheet" href="../css/admindashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/driverdashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
</head>

<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <div class="admin-avatar">
                    <i class="fa-solid fa-car-side"></i>
                </div>
                <div class="admin-details">
                    <h3><?php echo htmlspecialchars($data['driver']['FullName']); ?></h3>
                    <span class="admin-role">Driver ID: <?php echo $driver_id; ?></span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <ul>
                    <li class="nav-item active" data-target="dashboard">
                        <a href="#" class="nav-link"><i class="fa-solid fa-chart-line"></i><span>Dashboard</span></a>
                    </li>
                    <li class="nav-item" data-target="rides" data-count="<?php echo count($data['rides']); ?>">
                        <a href="#" class="nav-link"><i class="fa-solid fa-car"></i><span>All Rides</span><span class="new-dot" aria-hidden="true"></span></a>
                    </li>
                    <li class="nav-item" data-target="bookings" data-count="<?php echo count($data['bookings']); ?>">
                        <a href="#" class="nav-link"><i class="fa-solid fa-calendar-check"></i><span>Bookings</span><span class="new-dot" aria-hidden="true"></span></a>
                    </li>
                    <li class="nav-item" data-target="earnings" data-count="<?php echo count($data['earnings']); ?>">
                        <a href="#" class="nav-link"><i class="fa-solid fa-money-bill-wave"></i><span>Earnings</span><span class="new-dot" aria-hidden="true"></span></a>
                    </li>
                    <li class="nav-item" data-target="payments" data-count="<?php echo count($data['payments']); ?>">
                        <a href="#" class="nav-link"><i class="fa-solid fa-credit-card"></i><span>Payments</span><span class="new-dot" aria-hidden="true"></span></a>
                    </li>
                    <li class="nav-item" data-target="notifications" data-count="<?php echo count($data['notifications']); ?>">
                        <a href="#" class="nav-link"><i class="fa-solid fa-bell"></i><span>Notifications</span><span class="new-dot" aria-hidden="true"></span></a>
                    </li>
                    <li class="nav-item" data-target="profile">
                        <a href="#" class="nav-link"><i class="fa-solid fa-user"></i><span>My Profile</span></a>
                    </li>
                    <li class="nav-item">
                        <a href="../php/logout.php" class="nav-link logout"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <div class="admin-main">
            <header class="admin-header">
                <div class="header-content">
                    <div class="logo">
                        <i class="fa-solid fa-car"></i>
                        <span>CampusCar <span class="admin-badge">Driver Dashboard</span></span>
                    </div>
                    <div class="header-info">
                        <a href="../php/userdashboard.php" class="back-btn" title="Return to User Dashboard">
                            <i class="fa-solid fa-arrow-left"></i>
                            <span>Back</span>
                        </a>
                        <div class="current-time">
                            <i class="fa-solid fa-clock"></i>
                            <span id="currentTime"><?php echo date('Y-m-d H:i:s'); ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <main class="dashboard-content">
                <!-- Dashboard Overview -->
                <section id="dashboard" class="dashboard-section active">
                    <h2><i class="fa-solid fa-chart-line"></i> Dashboard Overview</h2>

                    <!-- Quick Stats -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon drivers"><i class="fa-solid fa-car"></i></div>
                            <div class="stat-info">
                                <h3><?php echo count($data['rides']); ?></h3>
                                <span class="stat-change">Total Rides</span>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon bookings"><i class="fa-solid fa-users"></i></div>
                            <div class="stat-info">
                                <h3><?php echo count($data['bookings']); ?></h3>
                                <span class="stat-change">Total Bookings</span>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon users"><i class="fa-solid fa-money-bill"></i></div>
                            <div class="stat-info">
                                <h3>RM <?php
                                        $total_earnings = array_sum(array_column($data['earnings'], 'Amount'));
                                        echo number_format($total_earnings, 2);
                                        ?></h3>
                                <span class="stat-change">Total Earnings</span>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon rides"><i class="fa-solid fa-bell"></i></div>
                            <div class="stat-info">
                                <h3><?php echo count($data['notifications']); ?></h3>
                                <span class="stat-change">Notifications</span>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activities -->
                    <div class="recent-activities">
                        <h3><i class="fa-solid fa-history"></i> Recent Activities</h3>
                        <div class="activities-list">
                            <?php
                            $recent_activities = array_merge(
                                array_slice($data['rides'], 0, 3),
                                array_slice($data['bookings'], 0, 3),
                                array_slice($data['notifications'], 0, 3)
                            );

                            usort($recent_activities, function ($a, $b) {
                                $dateA = isset($a['CreatedAt']) ? $a['CreatedAt'] : (isset($a['BookingDateTime']) ? $a['BookingDateTime'] : (isset($a['RideDate']) ? $a['RideDate'] . ' ' . $a['DepartureTime'] : ''));
                                $dateB = isset($b['CreatedAt']) ? $b['CreatedAt'] : (isset($b['BookingDateTime']) ? $b['BookingDateTime'] : (isset($b['RideDate']) ? $b['RideDate'] . ' ' . $b['DepartureTime'] : ''));
                                return strtotime($dateB) - strtotime($dateA);
                            });

                            foreach (array_slice($recent_activities, 0, 6) as $activity):
                                if (isset($activity['FromLocation'])): // Ride
                            ?>
                                    <div class="activity-item">
                                        <i class="fa-solid fa-car activity-icon"></i>
                                        <div class="activity-content">
                                            <p>New ride from <?php echo htmlspecialchars($activity['FromLocation']); ?> to <?php echo htmlspecialchars($activity['ToLocation']); ?></p>
                                            <small><?php echo $activity['RideDate'] . ' ' . $activity['DepartureTime']; ?></small>
                                        </div>
                                    </div>
                                <?php elseif (isset($activity['BookingDateTime'])): // Booking 
                                ?>
                                    <div class="activity-item">
                                        <i class="fa-solid fa-user-check activity-icon"></i>
                                        <div class="activity-content">
                                            <p>New booking by <?php echo htmlspecialchars($activity['FullName']); ?></p>
                                            <small><?php echo $activity['BookingDateTime']; ?></small>
                                        </div>
                                    </div>
                                <?php elseif (isset($activity['Message'])): // Notification 
                                ?>
                                    <div class="activity-item">
                                        <i class="fa-solid fa-bell activity-icon"></i>
                                        <div class="activity-content">
                                            <p><?php echo htmlspecialchars($activity['Title']); ?></p>
                                            <small><?php echo $activity['CreatedAt']; ?></small>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>

                <!-- All Rides Section -->
                <section id="rides" class="dashboard-section">
                    <h2><i class="fa-solid fa-car"></i> All Rides (<?php echo count($data['rides']); ?>)</h2>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Ride ID</th>
                                    <th>From</th>
                                    <th>To</th>
                                    <th>Date & Time</th>
                                    <th>Seats</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th>Female Only</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['rides'] as $ride): ?>
                                    <tr>
                                        <td>#<?php echo $ride['RideID']; ?></td>
                                        <td><?php echo htmlspecialchars($ride['FromLocation']); ?></td>
                                        <td><?php echo htmlspecialchars($ride['ToLocation']); ?></td>
                                        <td><?php echo $ride['RideDate'] . ' ' . $ride['DepartureTime']; ?></td>
                                        <td><?php echo $ride['AvailableSeats']; ?></td>
                                        <td>RM <?php echo number_format($ride['PricePerSeat'], 2); ?></td>
                                        <td><span class="status-badge <?php echo $ride['Status']; ?>"><?php echo ucfirst($ride['Status']); ?></span></td>
                                        <td><?php echo $ride['FemaleOnly'] ? 'Yes' : 'No'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Bookings Section -->
                <section id="bookings" class="dashboard-section">
                    <h2><i class="fa-solid fa-calendar-check"></i> All Bookings (<?php echo count($data['bookings']); ?>)</h2>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Booking ID</th>
                                    <th>Passenger</th>
                                    <th>Date & Time</th>
                                    <th>Seats</th>
                                    <th>Total Price</th>
                                    <th>Status</th>
                                    <th>Cancellation Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['bookings'] as $booking): ?>
                                    <tr>
                                        <td>#<?php echo $booking['BookingID']; ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($booking['FullName']); ?><br>
                                            <small><?php echo $booking['Email']; ?></small>
                                        </td>
                                        <td><?php echo $booking['BookingDateTime']; ?></td>
                                        <td><?php echo $booking['NoOfSeats']; ?></td>
                                        <td>RM <?php echo number_format($booking['TotalPrice'], 2); ?></td>
                                        <td><span class="status-badge <?php echo strtolower($booking['BookingStatus']); ?>"><?php echo $booking['BookingStatus']; ?></span></td>
                                        <td><?php echo htmlspecialchars($booking['CancellationReason'] ?: 'N/A'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Earnings Section -->
                <section id="earnings" class="dashboard-section">
                    <h2><i class="fa-solid fa-money-bill-wave"></i> Earnings (<?php echo count($data['earnings']); ?>)</h2>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Earning ID</th>
                                    <th>Ride</th>
                                    <th>Passenger</th>
                                    <th>Amount</th>
                                    <th>Payment Date</th>
                                    <th>Created At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['earnings'] as $earning): ?>
                                    <tr>
                                        <td>#<?php echo $earning['EarningID']; ?></td>
                                        <td>
                                            From: <?php echo htmlspecialchars($earning['FromLocation']); ?><br>
                                            To: <?php echo htmlspecialchars($earning['ToLocation']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($earning['PassengerName']); ?></td>
                                        <td class="text-success">RM <?php echo number_format($earning['Amount'], 2); ?></td>
                                        <td><?php echo $earning['PaymentDate']; ?></td>
                                        <td><?php echo $earning['CreatedAt']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Payments Section -->
                <!-- Payments Section -->
                <section id="payments" class="dashboard-section">
                    <h2><i class="fa-solid fa-credit-card"></i> Payments (<?php echo count($data['payments']); ?>)</h2>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Payment ID</th>
                                    <th>Booking ID</th>
                                    <th>Passenger</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                    <th>Payment Date</th>
                                    <th>Proof</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['payments'] as $payment): ?>
                                    <tr>
                                        <td>#<?php echo $payment['PaymentID']; ?></td>
                                        <td>#<?php echo $payment['BookingID']; ?></td>
                                        <td><?php echo htmlspecialchars($payment['FullName']); ?></td>
                                        <td>RM <?php echo number_format($payment['Amount'], 2); ?></td>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $payment['PaymentMethod'])); ?></td>
                                        <td><span class="status-badge <?php echo $payment['PaymentStatus']; ?>"><?php echo ucfirst($payment['PaymentStatus']); ?></span></td>
                                        <td><?php echo $payment['PaymentDate'] ?: 'N/A'; ?></td>
                                        <td>
                                            <?php if (!empty($payment['ProofPath']) && $payment['ProofPath'] !== 'NULL'): ?>
                                                <button class="view-proof-btn"
                                                    data-proof="<?php echo htmlspecialchars($payment['ProofPath']); ?>"
                                                    data-payment-id="<?php echo $payment['PaymentID']; ?>"
                                                    data-passenger="<?php echo htmlspecialchars($payment['FullName']); ?>"
                                                    data-amount="RM <?php echo number_format($payment['Amount'], 2); ?>">
                                                    <i class="fa-solid fa-eye"></i> View Proof
                                                </button>
                                            <?php else: ?>
                                                <span class="no-proof">No Proof</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Notifications Section -->
                <section id="notifications" class="dashboard-section">
                    <h2><i class="fa-solid fa-bell"></i> Notifications (<?php echo count($data['notifications']); ?>)</h2>
                    <div class="notifications-list">
                        <?php foreach ($data['notifications'] as $notification): ?>
                            <div class="notification-item <?php echo $notification['IsRead'] ? 'read' : 'unread'; ?>">
                                <div class="notification-icon">
                                    <i class="fa-solid fa-<?php echo $notification['Type'] === 'success' ? 'check-circle' : ($notification['Type'] === 'warning' ? 'exclamation-triangle' : 'info-circle'); ?>"></i>
                                </div>
                                <div class="notification-content">
                                    <h4><?php echo htmlspecialchars($notification['Title']); ?></h4>
                                    <p><?php echo htmlspecialchars($notification['Message']); ?></p>
                                    <div class="notification-meta">
                                        <span class="notification-type">Type: <?php echo ucfirst($notification['Type']); ?></span>
                                        <span class="notification-time"><?php echo $notification['CreatedAt']; ?></span>
                                        <?php if ($notification['RelatedID']): ?>
                                            <span class="notification-related">Related ID: <?php echo $notification['RelatedID']; ?> (<?php echo $notification['RelatedType']; ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- Profile Section -->
                <section id="profile" class="dashboard-section">
                    <h2><i class="fa-solid fa-user"></i> My Profile</h2>
                    <div class="profile-container">
                        <div class="profile-header">
                            <div class="profile-avatar">
                                <i class="fa-solid fa-user-circle"></i>
                            </div>
                            <div class="profile-info">
                                <h3><?php echo htmlspecialchars($data['driver']['FullName']); ?></h3>
                                <p>Driver ID: <?php echo $driver_id; ?></p>
                                <p>Status: <span class="status-badge <?php echo $data['driver']['Status']; ?>"><?php echo ucfirst($data['driver']['Status']); ?></span></p>
                            </div>
                        </div>

                        <div class="profile-details">
                            <div class="detail-section">
                                <h4><i class="fa-solid fa-id-card"></i> Personal Information</h4>
                                <div class="detail-grid">
                                    <div class="detail-item">
                                        <span class="detail-label">Full Name:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($data['driver']['FullName']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Matric No:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($data['driver']['MatricNo']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">IC No:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($data['driver']['ICNo']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Email:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($data['driver']['Email']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Phone:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($data['driver']['PhoneNumber']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Gender:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($data['driver']['Gender']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Faculty:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($data['driver']['Faculty']); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="detail-section">
                                <h4><i class="fa-solid fa-car"></i> Driver Information</h4>
                                <div class="detail-grid">
                                    <div class="detail-item">
                                        <span class="detail-label">License Number:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($data['driver']['LicenseNumber']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Car Model:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($data['driver']['CarModel']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Car Plate:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($data['driver']['CarPlateNumber']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Bank Name:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($data['driver']['BankName'] ?: 'N/A'); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Account Number:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($data['driver']['AccountNumber'] ?: 'N/A'); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Account Name:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($data['driver']['AccountName'] ?: 'N/A'); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Registration Date:</span>
                                        <span class="detail-value"><?php echo $data['driver']['RegistrationDate']; ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Approved Date:</span>
                                        <span class="detail-value"><?php echo $data['driver']['ApprovedDate'] ?: 'N/A'; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <!-- Add this CSS in driverdashboard.css or in a style tag -->
    <style>
        .dashboard-section {
            display: none;
            padding: 20px;
        }

        .dashboard-section.active {
            display: block;
        }

        .table-container {
            overflow-x: auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            background: #f8f9fa;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
        }

        .data-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #dee2e6;
        }

        .data-table tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-badge.completed,
        .status-badge.paid,
        .status-badge.confirmed {
            background: #d4edda;
            color: #155724;
        }

        .status-badge.cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .status-badge.pending,
        .status-badge.available {
            background: #fff3cd;
            color: #856404;
        }

        .status-badge.in_progress {
            background: #cce5ff;
            color: #004085;
        }

        .status-badge.expired {
            background: #e2e3e5;
            color: #383d41;
        }

        .notifications-list {
            max-height: 500px;
            overflow-y: auto;
        }

        .notification-item {
            background: white;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #3498db;
        }

        .notification-item.unread {
            border-left-color: #e74c3c;
            background: #fff5f5;
        }

        .notification-item .notification-icon {
            float: left;
            margin-right: 15px;
            color: #3498db;
            font-size: 20px;
        }

        .notification-item.unread .notification-icon {
            color: #e74c3c;
        }

        .notification-content h4 {
            margin: 0 0 5px 0;
            color: #2c3e50;
        }

        .notification-content p {
            margin: 0 0 10px 0;
            color: #7f8c8d;
        }

        .notification-meta {
            font-size: 12px;
            color: #95a5a6;
        }

        .notification-meta span {
            margin-right: 15px;
        }

        .profile-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }

        .profile-avatar {
            font-size: 60px;
            color: #3498db;
            margin-right: 20px;
        }

        .profile-info h3 {
            margin: 0 0 5px 0;
            color: #2c3e50;
        }

        .profile-info p {
            margin: 5px 0;
            color: #7f8c8d;
        }

        .detail-section {
            margin-bottom: 30px;
        }

        .detail-section h4 {
            color: #3498db;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dashed #eee;
        }

        .detail-label {
            font-weight: 600;
            color: #34495e;
        }

        .detail-value {
            color: #7f8c8d;
        }

        .recent-activities {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
        }

        .activities-list {
            margin-top: 15px;
        }

        .activity-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            margin-right: 15px;
            color: #3498db;
            font-size: 18px;
        }

        .activity-content p {
            margin: 0 0 5px 0;
            color: #2c3e50;
        }

        .activity-content small {
            color: #95a5a6;
            font-size: 12px;
        }

        .text-success {
            color: #28a745;
            font-weight: 600;
        }
    </style>

    <script>
        // Navigation functionality
        document.addEventListener('DOMContentLoaded', function() {
            const navItems = document.querySelectorAll('.nav-item');
            const sections = document.querySelectorAll('.dashboard-section');

            navItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();

                    // Remove active class from all items and sections
                    navItems.forEach(i => i.classList.remove('active'));
                    sections.forEach(s => s.classList.remove('active'));

                    // Add active class to clicked item
                    this.classList.add('active');

                    // Show corresponding section
                    const targetId = this.getAttribute('data-target');
                    document.getElementById(targetId).classList.add('active');
                });
            });

            // Update current time
            function updateCurrentTime() {
                const now = new Date();
                document.getElementById('currentTime').textContent =
                    now.toISOString().slice(0, 19).replace('T', ' ');
            }

            updateCurrentTime();
            setInterval(updateCurrentTime, 1000);

            // Add hover effects to tables
            const tables = document.querySelectorAll('.data-table');
            tables.forEach(table => {
                const rows = table.querySelectorAll('tbody tr');
                rows.forEach(row => {
                    row.addEventListener('mouseenter', function() {
                        this.style.backgroundColor = '#f8f9fa';
                    });
                    row.addEventListener('mouseleave', function() {
                        this.style.backgroundColor = '';
                    });
                });
            });
        });

        // Payment Proof Modal Functionality
        document.addEventListener('DOMContentLoaded', function() {
            // View proof buttons
            const viewProofButtons = document.querySelectorAll('.view-proof-btn');
            const proofModal = document.createElement('div');
            proofModal.className = 'proof-modal';
            proofModal.innerHTML = `
        <div class="proof-modal-content">
            <div class="proof-modal-header">
                <h3><i class="fa-solid fa-file-invoice-dollar"></i> Payment Proof</h3>
                <button class="close-proof-modal">&times;</button>
            </div>
            <div class="proof-details">
                <div class="proof-detail-item">
                    <span class="proof-detail-label">Payment ID:</span>
                    <span class="proof-detail-value" id="modal-payment-id"></span>
                </div>
                <div class="proof-detail-item">
                    <span class="proof-detail-label">Passenger:</span>
                    <span class="proof-detail-value" id="modal-passenger"></span>
                </div>
                <div class="proof-detail-item">
                    <span class="proof-detail-label">Amount:</span>
                    <span class="proof-detail-value" id="modal-amount"></span>
                </div>
            </div>
            <div class="proof-image-container">
                <img id="modal-proof-image" src="" alt="Payment Proof" class="proof-image">
                <div class="proof-message">
                    <p><i class="fa-solid fa-info-circle"></i> This is the payment proof uploaded by the passenger. Please verify the payment details.</p>
                </div>
            </div>
            <div class="proof-actions">
                <button class="download-proof-btn" id="download-proof">
                    <i class="fa-solid fa-download"></i> Download Proof
                </button>
            </div>
        </div>
    `;

            document.body.appendChild(proofModal);

            // Add event listeners to view proof buttons
            viewProofButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const proofPath = this.getAttribute('data-proof');
                    const paymentId = this.getAttribute('data-payment-id');
                    const passenger = this.getAttribute('data-passenger');
                    const amount = this.getAttribute('data-amount');

                    // Set modal content
                    document.getElementById('modal-payment-id').textContent = `#${paymentId}`;
                    document.getElementById('modal-passenger').textContent = passenger;
                    document.getElementById('modal-amount').textContent = amount;

                    // Set image source
                    const imgElement = document.getElementById('modal-proof-image');
                    imgElement.src = '../' + proofPath;
                    imgElement.alt = `Payment Proof for Payment ID #${paymentId}`;

                    // Setup download button
                    const downloadBtn = document.getElementById('download-proof');
                    downloadBtn.onclick = function() {
                        downloadPaymentProof(proofPath, paymentId);
                    };

                    // Show modal
                    proofModal.style.display = 'block';
                });
            });

            // Close modal when clicking X
            proofModal.querySelector('.close-proof-modal').addEventListener('click', function() {
                proofModal.style.display = 'none';
            });

            // Close modal when clicking outside
            proofModal.addEventListener('click', function(e) {
                if (e.target === proofModal) {
                    proofModal.style.display = 'none';
                }
            });

            // Escape key to close modal
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && proofModal.style.display === 'block') {
                    proofModal.style.display = 'none';
                }
            });
        });

        // Function to download payment proof
        function downloadPaymentProof(proofPath, paymentId) {
            // Create a temporary anchor element
            const link = document.createElement('a');
            link.href = '../' + proofPath;
            link.download = `payment-proof-${paymentId}.${proofPath.split('.').pop()}`;
            link.target = '_blank';

            // Trigger download
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            // Show success message
            showToast('Proof download started', 'success');
        }

        // Toast notification function
        function showToast(message, type = 'info') {
            // Remove existing toast if any
            const existingToast = document.querySelector('.toast-notification');
            if (existingToast) {
                existingToast.remove();
            }

            // Create toast element
            const toast = document.createElement('div');
            toast.className = `toast-notification toast-${type}`;
            toast.innerHTML = `
        <div class="toast-content">
            <i class="fa-solid ${type === 'success' ? 'fa-check-circle' : 'fa-info-circle'}"></i>
            <span>${message}</span>
        </div>
        <button class="toast-close">&times;</button>
    `;

            // Add to body
            document.body.appendChild(toast);

            // Add styles for toast
            if (!document.querySelector('#toast-styles')) {
                const style = document.createElement('style');
                style.id = 'toast-styles';
                style.textContent = `
            .toast-notification {
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: white;
                padding: 15px 20px;
                border-radius: 10px;
                box-shadow: 0 5px 20px rgba(0,0,0,0.15);
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 15px;
                z-index: 3000;
                animation: slideInRight 0.3s ease;
                max-width: 350px;
            }
            
            .toast-success {
                border-left: 4px solid var(--success-color);
            }
            
            .toast-info {
                border-left: 4px solid var(--primary-color);
            }
            
            .toast-content {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .toast-content i {
                font-size: 1.2rem;
            }
            
            .toast-success .toast-content i {
                color: var(--success-color);
            }
            
            .toast-info .toast-content i {
                color: var(--primary-color);
            }
            
            .toast-close {
                background: none;
                border: none;
                font-size: 1.2rem;
                color: #6b7280;
                cursor: pointer;
                padding: 0;
                line-height: 1;
            }
            
            @keyframes slideInRight {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
        `;
                document.head.appendChild(style);
            }

            // Close button functionality
            toast.querySelector('.toast-close').addEventListener('click', function() {
                toast.remove();
            });

            // Auto remove after 5 seconds
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 5000);
        }

        // simple green-dot logic: shows when count > last-seen, hides on click
        document.addEventListener('DOMContentLoaded', function() {
            const sections = ['rides', 'bookings', 'earnings', 'payments', 'notifications'];
            sections.forEach(sec => {
                const li = document.querySelector(`.nav-item[data-target="${sec}"]`);
                if (!li) return;
                const count = parseInt(li.getAttribute('data-count') || '0', 10);
                const seenKey = `sidebar_seen_${sec}`;
                const seen = parseInt(localStorage.getItem(seenKey) || '0', 10);
                const dot = li.querySelector('.new-dot');
                if (dot && count > seen && count > 0) dot.classList.add('visible');

                li.addEventListener('click', function() {
                    // mark as seen: store current count snapshot
                    localStorage.setItem(seenKey, String(count));
                    if (dot) dot.classList.remove('visible');
                });
            });
        });
    </script>
</body>

</html>
<?php $conn->close(); ?>