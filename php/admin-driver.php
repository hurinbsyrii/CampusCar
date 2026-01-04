<?php
session_start();

// Ensure admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Database connection
$servername = "127.0.0.1:3301";
$username = "root";
$password = "";
$dbname = "campuscar";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle actions (approve/reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['driver_id'])) {
        $driver_id = intval($_POST['driver_id']);
        $action = $_POST['action'];

        // Initialize result
        $result = false;

        if ($action === 'approve') {
            // Prepare and execute update for approve (only DriverID placeholder)
            $stmt = $conn->prepare("UPDATE driver SET Status = 'approved', ApprovedDate = NOW() WHERE DriverID = ?");
            $stmt->bind_param("i", $driver_id);
            $result = $stmt->execute();
            $stmt->close();

            $message = "Driver approved successfully!";
            $type = "success";

            // Send notification to driver
            $driver_info = $conn->query("SELECT UserID FROM driver WHERE DriverID = $driver_id")->fetch_assoc();
            if ($driver_info) {
                $notification_sql = "INSERT INTO notifications (UserID, Title, Message, Type, CreatedAt, RelatedID, RelatedType) 
                                     VALUES ({$driver_info['UserID']}, 'Driver Registration Approved', 
                                     'Your driver registration has been approved. You can now offer rides.', 
                                     'success', NOW(), $driver_id, 'driver_approval')";
                $conn->query($notification_sql);
            }
        } elseif ($action === 'reject' && isset($_POST['rejection_reason'])) {
            $rejection_reason = $conn->real_escape_string($_POST['rejection_reason']);
            $sql = "UPDATE driver SET Status = 'rejected', RejectionReason = ? WHERE DriverID = ?";
            $message = "Driver registration rejected.";
            $type = "warning";

            // Prepare statement for rejection (two placeholders)
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $rejection_reason, $driver_id);
            $result = $stmt->execute();
            $stmt->close();

            // Send notification to driver
            $driver_info = $conn->query("SELECT UserID FROM driver WHERE DriverID = $driver_id")->fetch_assoc();
            if ($driver_info) {
                $notification_sql = "INSERT INTO notifications (UserID, Title, Message, Type, CreatedAt, RelatedID, RelatedType) 
                                     VALUES ({$driver_info['UserID']}, 'Driver Registration Rejected', 
                                     'Your driver registration was rejected. Reason: $rejection_reason', 
                                     'warning', NOW(), $driver_id, 'driver_rejection')";
                $conn->query($notification_sql);
            }

            header("Location: admin-driver.php?message=" . urlencode($message) . "&type=$type");
            exit();
        } else {
            // Generic status update (two placeholders)
            $sql = "UPDATE driver SET Status = ? WHERE DriverID = ?";
            $message = "Driver status updated.";
            $type = "info";

            $stmt = $conn->prepare($sql);
            $status = $action;
            $stmt->bind_param("si", $status, $driver_id);
            $result = $stmt->execute();
            $stmt->close();
        }

        if ($result) {
            $_SESSION['notification'] = [
                'message' => $message,
                'type' => $type
            ];
        }

        header("Location: admin-driver.php");
        exit();
    }
}

// Get all drivers with user details - FIXED QUERY
$sql = "SELECT 
    d.*, 
    u.FullName, 
    u.MatricNo, 
    u.Email, 
    u.PhoneNumber, 
    u.Gender,
    u.Faculty
    FROM driver d 
    JOIN user u ON d.UserID = u.UserID 
    ORDER BY 
        CASE d.Status 
            WHEN 'pending' THEN 1 
            WHEN 'rejected' THEN 2 
            ELSE 3 
        END,
        d.RegistrationDate DESC";

$result = $conn->query($sql);

// Get statistics
$total_drivers = $conn->query("SELECT COUNT(*) as count FROM driver")->fetch_assoc()['count'];
$pending_drivers = $conn->query("SELECT COUNT(*) as count FROM driver WHERE Status = 'pending'")->fetch_assoc()['count'];
$approved_drivers = $conn->query("SELECT COUNT(*) as count FROM driver WHERE Status = 'approved'")->fetch_assoc()['count'];
$rejected_drivers = $conn->query("SELECT COUNT(*) as count FROM driver WHERE Status = 'rejected'")->fetch_assoc()['count'];

// Get recent pending drivers (last 7 days)
$recent_pending = $conn->query("SELECT COUNT(*) as count FROM driver WHERE Status = 'pending' AND RegistrationDate >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Management - CampusCar Admin</title>
    <link rel="stylesheet" href="../css/admin-driver.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admindashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
    <div class="admin-container">
        <!-- Include Sidebar from admindashboard -->
        <?php
        // We'll include just the sidebar structure without the full dashboard
        // Let's create a simplified sidebar include
        ?>
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <div class="admin-avatar">
                    <i class="fa-solid fa-user-shield"></i>
                </div>
                <div class="admin-details">
                    <h3>Admin Panel</h3>
                    <span class="admin-role"><?php echo $_SESSION['full_name'] ?? 'Admin'; ?></span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <ul>
                    <li class="nav-item">
                        <a href="admindashboard.php" class="nav-link">
                            <i class="fa-solid fa-gauge-high"></i>
                            <span>Admin Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="admin-user.php" class="nav-link">
                            <i class="fa-solid fa-users"></i>
                            <span>Users</span>
                        </a>
                    </li>
                    <li class="nav-item active">
                        <a href="admin-driver.php" class="nav-link">
                            <i class="fa-solid fa-id-card"></i>
                            <span>Drivers</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="admin-rides.php" class="nav-link">
                            <i class="fa-solid fa-car"></i>
                            <span>Rides</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="admin-booking.php" class="nav-link">
                            <i class="fa-solid fa-ticket"></i>
                            <span>Bookings</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="admin-reports.php" class="nav-link">
                            <i class="fa-solid fa-chart-pie"></i>
                            <span>Reports</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../php/logout.php" class="nav-link logout">
                            <i class="fa-solid fa-right-from-bracket"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <div class="admin-main">
            <!-- Header -->
            <header class="admin-header">
                <div class="header-content">
                    <button id="sidebarToggle" class="sidebar-toggle">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <div class="logo">
                        <i class="fa-solid fa-car-side"></i>
                        <span>CampusCar <span class="admin-badge">Driver Management</span></span>
                    </div>
                    <div class="header-info">
                        <div class="today-stats">
                            <span class="stat-badge">
                                <i class="fa-solid fa-clock"></i> <?php echo $pending_drivers; ?> pending
                            </span>
                            <span class="stat-badge">
                                <i class="fa-solid fa-check-circle"></i> <?php echo $approved_drivers; ?> approved
                            </span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Driver Management Content -->
            <main class="dashboard-content">
                <!-- Stats Overview -->
                <section class="stats-section">
                    <h2><i class="fa-solid fa-id-card"></i> Driver Management</h2>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon total">
                                <i class="fa-solid fa-users"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Total Drivers</h3>
                                <span class="stat-number"><?php echo $total_drivers; ?></span>
                                <span class="stat-change">All registered drivers</span>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon pending">
                                <i class="fa-solid fa-clock"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Pending Review</h3>
                                <span class="stat-number"><?php echo $pending_drivers; ?></span>
                                <span class="stat-change"><?php echo $recent_pending; ?> new this week</span>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon approved">
                                <i class="fa-solid fa-check-circle"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Approved</h3>
                                <span class="stat-number"><?php echo $approved_drivers; ?></span>
                                <span class="stat-change">Active drivers</span>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon rejected">
                                <i class="fa-solid fa-times-circle"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Rejected</h3>
                                <span class="stat-number"><?php echo $rejected_drivers; ?></span>
                                <span class="stat-change">Rejected applications</span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Driver List -->
                <section class="drivers-section">
                    <div class="section-header">
                        <h3><i class="fa-solid fa-list"></i> All Driver Applications</h3>
                        <div class="section-actions">
                            <div class="filter-controls">
                                <button class="filter-btn active" data-filter="all">All</button>
                                <button class="filter-btn" data-filter="pending">Pending</button>
                                <button class="filter-btn" data-filter="approved">Approved</button>
                                <button class="filter-btn" data-filter="rejected">Rejected</button>
                            </div>
                            <button id="refreshBtn" class="refresh-btn">
                                <i class="fa-solid fa-rotate"></i> Refresh
                            </button>
                        </div>
                    </div>

                    <?php if (isset($_SESSION['notification'])): ?>
                        <div class="notification <?php echo $_SESSION['notification']['type']; ?>">
                            <i class="fa-solid fa-<?php echo $_SESSION['notification']['type'] === 'success' ? 'check' : 'exclamation-triangle'; ?>"></i>
                            <span><?php echo $_SESSION['notification']['message']; ?></span>
                            <button class="close-notification"><i class="fa-solid fa-times"></i></button>
                        </div>
                        <?php unset($_SESSION['notification']); ?>
                    <?php endif; ?>

                    <div class="drivers-table-container">
                        <table class="drivers-table">
                            <thead>
                                <tr>
                                    <th>Driver Info</th>
                                    <th>Vehicle Details</th>
                                    <th>Bank Details</th>
                                    <th>Registration Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result->num_rows > 0): ?>
                                    <?php while ($driver = $result->fetch_assoc()): ?>
                                        <tr class="driver-row" data-status="<?php echo strtolower($driver['Status']); ?>">
                                            <td>
                                                <div class="driver-info">
                                                    <div class="driver-avatar">
                                                        <i class="fa-solid fa-user-circle"></i>
                                                    </div>
                                                    <div class="driver-details">
                                                        <h4><?php echo htmlspecialchars($driver['FullName']); ?></h4>
                                                        <p>
                                                            <i class="fa-solid fa-id-card"></i> <?php echo htmlspecialchars($driver['MatricNo']); ?>
                                                        </p>
                                                        <p>
                                                            <i class="fa-solid fa-envelope"></i> <?php echo htmlspecialchars($driver['Email']); ?>
                                                        </p>
                                                        <p>
                                                            <i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($driver['PhoneNumber']); ?>
                                                        </p>
                                                        <p>
                                                            <i class="fa-solid fa-graduation-cap"></i> <?php echo htmlspecialchars($driver['Faculty']); ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="vehicle-info">
                                                    <p><strong>License:</strong> <?php echo htmlspecialchars($driver['LicenseNumber']); ?></p>
                                                    <p><strong>Car Model:</strong> <?php echo htmlspecialchars($driver['CarModel']); ?></p>
                                                    <p><strong>Plate No:</strong> <?php echo htmlspecialchars($driver['CarPlateNumber']); ?></p>
                                                    <?php if (!empty($driver['PaymentQRCode'])): ?>
                                                        <p><strong>QR Code:</strong>
                                                            <a href="../<?php echo htmlspecialchars($driver['PaymentQRCode']); ?>" target="_blank" class="qr-link">
                                                                <i class="fa-solid fa-qrcode"></i> View QR
                                                            </a>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="bank-info">
                                                    <?php if (!empty($driver['BankName'])): ?>
                                                        <p><strong>Bank:</strong> <?php echo htmlspecialchars($driver['BankName']); ?></p>
                                                        <p><strong>Account No:</strong> <?php echo htmlspecialchars($driver['AccountNumber']); ?></p>
                                                        <p><strong>Account Name:</strong> <?php echo htmlspecialchars($driver['AccountName']); ?></p>
                                                    <?php else: ?>
                                                        <p class="no-data">No bank details</p>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="date-info">
                                                    <p><strong>Applied:</strong>
                                                        <?php
                                                        if (!empty($driver['RegistrationDate'])) {
                                                            echo date('M d, Y', strtotime($driver['RegistrationDate']));
                                                        } else {
                                                            echo 'N/A';
                                                        }
                                                        ?>
                                                    </p>
                                                    <?php if ($driver['Status'] === 'approved' && !empty($driver['ApprovedDate'])): ?>
                                                        <p><strong>Approved:</strong> <?php echo date('M d, Y', strtotime($driver['ApprovedDate'])); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower($driver['Status']); ?>">
                                                    <?php echo ucfirst($driver['Status']); ?>
                                                </span>
                                                <?php if ($driver['Status'] === 'rejected' && !empty($driver['RejectionReason'])): ?>
                                                    <div class="rejection-reason">
                                                        <small><i class="fa-solid fa-exclamation-circle"></i> <?php echo htmlspecialchars($driver['RejectionReason']); ?></small>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <?php if ($driver['Status'] === 'pending'): ?>
                                                        <button class="action-btn approve-btn" data-id="<?php echo $driver['DriverID']; ?>">
                                                            <i class="fa-solid fa-check"></i> Approve
                                                        </button>
                                                        <button class="action-btn reject-btn" data-id="<?php echo $driver['DriverID']; ?>">
                                                            <i class="fa-solid fa-times"></i> Reject
                                                        </button>
                                                    <?php elseif ($driver['Status'] === 'approved'): ?>
                                                        <button class="action-btn revoke-btn" data-id="<?php echo $driver['DriverID']; ?>">
                                                            <i class="fa-solid fa-ban"></i> Revoke
                                                        </button>
                                                    <?php elseif ($driver['Status'] === 'rejected'): ?>
                                                        <button class="action-btn review-btn" data-id="<?php echo $driver['DriverID']; ?>">
                                                            <i class="fa-solid fa-eye"></i> Review
                                                        </button>
                                                    <?php endif; ?>

                                                    <button class="action-btn view-btn" data-id="<?php echo $driver['DriverID']; ?>">
                                                        <i class="fa-solid fa-eye"></i> View
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="no-data-cell">
                                            <div class="no-data">
                                                <i class="fa-solid fa-users-slash"></i>
                                                <p>No driver registrations found</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Action Modal -->
                <div id="actionModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3 id="modalTitle"></h3>
                            <button class="close-modal">&times;</button>
                        </div>
                        <div class="modal-body">
                            <form id="actionForm" method="POST">
                                <input type="hidden" name="driver_id" id="modalDriverId">
                                <input type="hidden" name="action" id="modalAction">

                                <div id="rejectionSection" class="form-section" style="display: none;">
                                    <div class="form-group">
                                        <label for="rejection_reason">
                                            <i class="fa-solid fa-exclamation-circle"></i>
                                            Rejection Reason *
                                        </label>
                                        <textarea id="rejection_reason" name="rejection_reason"
                                            rows="4" placeholder="Please provide a reason for rejection..."
                                            required></textarea>
                                        <small class="help-text">This will be shown to the driver</small>
                                    </div>
                                </div>

                                <div id="approvalSection" class="form-section" style="display: none;">
                                    <div class="confirmation-message">
                                        <i class="fa-solid fa-check-circle"></i>
                                        <p>Are you sure you want to approve this driver? They will be able to offer rides immediately.</p>
                                    </div>
                                </div>

                                <div class="modal-actions">
                                    <button type="button" class="btn-secondary close-modal-btn">Cancel</button>
                                    <button type="submit" class="btn-primary" id="modalSubmitBtn"></button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="../js/admin-driver.js?v=<?php echo time(); ?>"></script>
</body>

</html>
<?php $conn->close(); ?>