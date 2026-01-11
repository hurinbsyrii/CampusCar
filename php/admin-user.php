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

// Handle actions (toggle status, delete, check activity)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['user_id'])) {
        $user_id = intval($_POST['user_id']);
        $action = $_POST['action'];

        // --- NEW: AJAX Check for Active Rides/Bookings ---
        if ($action === 'check_activity') {
            // Check for active bookings (Pending/Confirmed)
            $booking_sql = "SELECT COUNT(*) as count FROM booking 
                            WHERE UserID = $user_id 
                            AND BookingStatus IN ('Pending', 'Confirmed')";

            // Check for active rides (Driver with upcoming rides)
            $ride_sql = "SELECT COUNT(*) as count FROM rides r
                         JOIN driver d ON r.DriverID = d.DriverID
                         WHERE d.UserID = $user_id 
                         AND r.Status IN ('available', 'pending')
                         AND r.RideDate >= CURRENT_DATE";

            $bookings = $conn->query($booking_sql)->fetch_assoc()['count'];
            $rides = $conn->query($ride_sql)->fetch_assoc()['count'];

            echo json_encode([
                'status' => 'success',
                'active_bookings' => $bookings,
                'active_rides' => $rides,
                'total_active' => $bookings + $rides
            ]);
            exit(); // Stop script here for AJAX
        }
        // --------------------------------------------------

        if ($action === 'toggle_status') {
            // ... (Your existing toggle logic remains the same) ...
            $result = $conn->query("SELECT Role, FullName FROM user WHERE UserID = $user_id");
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                $new_role = $user['Role'] === 'admin' ? 'user' : 'admin';
                $stmt = $conn->prepare("UPDATE user SET Role = ? WHERE UserID = ?");
                $stmt->bind_param("si", $new_role, $user_id);
                if ($stmt->execute()) {
                    $_SESSION['notification'] = ['message' => "User {$user['FullName']}'s role updated to $new_role", 'type' => 'success'];
                }
                $stmt->close();
            }
        } elseif ($action === 'delete' && isset($_POST['confirm_delete'])) {

            $conn->begin_transaction(); // Start safe transaction

            try {
                // 1. Handle DRIVER Role: Cancel Rides & Notify Passengers
                $driver_check = $conn->query("SELECT DriverID FROM driver WHERE UserID = $user_id");
                if ($driver_check->num_rows > 0) {
                    $driver = $driver_check->fetch_assoc();
                    $driver_id = $driver['DriverID'];

                    // Find active rides
                    $active_rides_query = "SELECT RideID, RideDate, FromLocation, ToLocation 
                                           FROM rides 
                                           WHERE DriverID = $driver_id AND Status NOT IN ('completed', 'cancelled')";
                    $rides_result = $conn->query($active_rides_query);

                    while ($ride = $rides_result->fetch_assoc()) {
                        $ride_id = $ride['RideID'];

                        // Notify all passengers of this ride
                        $passengers_query = "SELECT UserID FROM booking WHERE RideID = $ride_id";
                        $passengers = $conn->query($passengers_query);

                        while ($p = $passengers->fetch_assoc()) {
                            $p_id = $p['UserID'];
                            $msg = "The ride from " . $ride['FromLocation'] . " on " . $ride['RideDate'] . " has been CANCELLED because the driver account was removed.";

                            // Insert Notification
                            $notif_stmt = $conn->prepare("INSERT INTO notifications (UserID, Title, Message, Type, IsRead, CreatedAt) VALUES (?, 'Ride Cancelled (Admin)', ?, 'warning', 0, NOW())");
                            $notif_stmt->bind_param("is", $p_id, $msg);
                            $notif_stmt->execute();
                            $notif_stmt->close();
                        }

                        // Delete bookings for this ride (FK Cleanup)
                        $conn->query("DELETE FROM booking WHERE RideID = $ride_id");
                        // Delete earnings related to this ride
                        $conn->query("DELETE FROM driver_earnings WHERE RideID = $ride_id");
                    }

                    // Delete all rides by this driver
                    $conn->query("DELETE FROM rides WHERE DriverID = $driver_id");
                    // Delete driver earnings
                    $conn->query("DELETE FROM driver_earnings WHERE DriverID = $driver_id");
                    // Delete reviews for this driver
                    $conn->query("DELETE FROM reviews WHERE DriverID = $driver_id");
                    // Remove driver entry
                    $conn->query("DELETE FROM driver WHERE DriverID = $driver_id");
                }

                // 2. Handle PASSENGER Role: Delete Bookings
                // (Optional: You could notify drivers here if you wanted, but usually just deleting the booking is enough)
                $conn->query("DELETE FROM payments WHERE UserID = $user_id"); // Clean payments
                $conn->query("DELETE FROM booking WHERE UserID = $user_id"); // Clean bookings

                // 3. Clean other dependencies
                $conn->query("DELETE FROM notifications WHERE UserID = $user_id");
                $conn->query("DELETE FROM password_reset WHERE UserID = $user_id");
                $conn->query("DELETE FROM reviews WHERE UserID = $user_id");

                // 4. Finally Delete User
                $stmt = $conn->prepare("DELETE FROM user WHERE UserID = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();

                $conn->commit(); // Save changes

                $_SESSION['notification'] = [
                    'message' => "User deleted successfully. Active rides/bookings were cancelled and passengers notified.",
                    'type' => 'success'
                ];
            } catch (Exception $e) {
                $conn->rollback(); // Undo if error
                $_SESSION['notification'] = [
                    'message' => "Error deleting user: " . $e->getMessage(),
                    'type' => 'error'
                ];
            }
        }

        header("Location: admin-user.php");
        exit();
    }
}

// ... (Rest of your HTML Code remains exactly the same below) ...
// Ensure you include the rest of your HTML code here

// Get all users
$search_term = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $conn->real_escape_string($_GET['role']) : '';
$faculty_filter = isset($_GET['faculty']) ? $conn->real_escape_string($_GET['faculty']) : '';

// Build query
$sql = "SELECT * FROM user WHERE 1=1";

if (!empty($search_term)) {
    $sql .= " AND (FullName LIKE '%$search_term%' 
                  OR MatricNo LIKE '%$search_term%' 
                  OR Email LIKE '%$search_term%'
                  OR Username LIKE '%$search_term%')";
}

if (!empty($role_filter)) {
    $sql .= " AND Role = '$role_filter'";
}

if (!empty($faculty_filter)) {
    $sql .= " AND Faculty = '$faculty_filter'";
}

$sql .= " ORDER BY UserID DESC";
$result = $conn->query($sql);

// Get statistics
$total_users = $conn->query("SELECT COUNT(*) as count FROM user")->fetch_assoc()['count'];
$admin_users = $conn->query("SELECT COUNT(*) as count FROM user WHERE Role = 'admin'")->fetch_assoc()['count'];
$regular_users = $conn->query("SELECT COUNT(*) as count FROM user WHERE Role = 'user'")->fetch_assoc()['count'];
$driver_users = $conn->query("SELECT COUNT(DISTINCT d.UserID) as count FROM driver d JOIN user u ON d.UserID = u.UserID WHERE d.Status = 'approved'")->fetch_assoc()['count'];

// Get unique faculties for filter
$faculties_result = $conn->query("SELECT DISTINCT Faculty FROM user WHERE Faculty != '' ORDER BY Faculty");
$faculties = [];
while ($row = $faculties_result->fetch_assoc()) {
    $faculties[] = $row['Faculty'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - CampusCar Admin</title>
    <link rel="stylesheet" href="../css/admin-user.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admindashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
    <div class="admin-container">
        <!-- Sidebar -->
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
                    <li class="nav-item active">
                        <a href="admin-user.php" class="nav-link">
                            <i class="fa-solid fa-users"></i>
                            <span>Users</span>
                        </a>
                    </li>
                    <li class="nav-item">
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
                        <span>CampusCar <span class="admin-badge">User Management</span></span>
                    </div>
                    <div class="header-info">
                        <div class="today-stats">
                            <span class="stat-badge">
                                <i class="fa-solid fa-users"></i> <?php echo $total_users; ?> total
                            </span>
                            <span class="stat-badge">
                                <i class="fa-solid fa-user-shield"></i> <?php echo $admin_users; ?> admins
                            </span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- User Management Content -->
            <main class="dashboard-content">
                <!-- Stats Overview -->
                <section class="stats-section">
                    <h2><i class="fa-solid fa-user-gear"></i> User Management</h2>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon total">
                                <i class="fa-solid fa-users"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Total Users</h3>
                                <span class="stat-number"><?php echo $total_users; ?></span>
                                <span class="stat-change">All registered users</span>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon admin">
                                <i class="fa-solid fa-user-shield"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Admins</h3>
                                <span class="stat-number"><?php echo $admin_users; ?></span>
                                <span class="stat-change">System administrators</span>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon user">
                                <i class="fa-solid fa-user"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Regular Users</h3>
                                <span class="stat-number"><?php echo $regular_users; ?></span>
                                <span class="stat-change">Standard users</span>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon driver">
                                <i class="fa-solid fa-id-card"></i>
                            </div>
                            <div class="stat-info">
                                <h3>Active Drivers</h3>
                                <span class="stat-number"><?php echo $driver_users; ?></span>
                                <span class="stat-change">Approved drivers</span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- User Management Section -->
                <section class="users-section">
                    <div class="section-header">
                        <h3><i class="fa-solid fa-list"></i> All Users</h3>
                        <div class="section-actions">
                            <form method="GET" class="search-filter-form">
                                <div class="search-box">
                                    <i class="fa-solid fa-search"></i>
                                    <input type="text" name="search" placeholder="Search users..."
                                        value="<?php echo htmlspecialchars($search_term); ?>">
                                </div>

                                <div class="filter-controls">
                                    <select name="role" class="filter-select">
                                        <option value="">All Roles</option>
                                        <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        <option value="user" <?php echo $role_filter === 'user' ? 'selected' : ''; ?>>User</option>
                                    </select>

                                    <select name="faculty" class="filter-select">
                                        <option value="">All Faculties</option>
                                        <?php foreach ($faculties as $faculty): ?>
                                            <option value="<?php echo htmlspecialchars($faculty); ?>"
                                                <?php echo $faculty_filter === $faculty ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($faculty); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <button type="submit" class="filter-btn">
                                        <i class="fa-solid fa-filter"></i> Filter
                                    </button>

                                    <a href="admin-user.php" class="clear-btn">
                                        <i class="fa-solid fa-times"></i> Clear
                                    </a>
                                </div>
                            </form>
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

                    <div class="users-table-container">
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th>User Info</th>
                                    <th>Contact Details</th>
                                    <th>Academic Info</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result->num_rows > 0): ?>
                                    <?php while ($user = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <div class="user-info">
                                                    <div class="user-avatar">
                                                        <i class="fa-solid fa-user-circle"></i>
                                                    </div>
                                                    <div class="user-details">
                                                        <h4><?php echo htmlspecialchars($user['FullName']); ?></h4>
                                                        <p>
                                                            <i class="fa-solid fa-user"></i>
                                                            <?php echo htmlspecialchars($user['Username']); ?>
                                                        </p>
                                                        <p>
                                                            <i class="fa-solid fa-id-card"></i>
                                                            <?php echo htmlspecialchars($user['ICNo']); ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="contact-info">
                                                    <p>
                                                        <i class="fa-solid fa-envelope"></i>
                                                        <?php echo htmlspecialchars($user['Email']); ?>
                                                    </p>
                                                    <p>
                                                        <i class="fa-solid fa-phone"></i>
                                                        <?php echo htmlspecialchars($user['PhoneNumber']); ?>
                                                    </p>
                                                    <p>
                                                        <i class="fa-solid fa-venus-mars"></i>
                                                        <?php echo htmlspecialchars($user['Gender']); ?>
                                                    </p>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="academic-info">
                                                    <p>
                                                        <strong>Matric No:</strong>
                                                        <?php echo htmlspecialchars($user['MatricNo']); ?>
                                                    </p>
                                                    <p>
                                                        <strong>Faculty:</strong>
                                                        <?php echo htmlspecialchars($user['Faculty']); ?>
                                                    </p>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="role-badge role-<?php echo strtolower($user['Role']); ?>">
                                                    <?php echo ucfirst($user['Role']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                // Check if user is an active driver
                                                $driver_check = $conn->query("SELECT Status FROM driver WHERE UserID = {$user['UserID']}");
                                                if ($driver_check->num_rows > 0) {
                                                    $driver_status = $driver_check->fetch_assoc()['Status'];
                                                    echo '<span class="status-badge status-' . strtolower($driver_status) . '">';
                                                    echo '<i class="fa-solid fa-car"></i> ' . ucfirst($driver_status);
                                                    echo '</span>';
                                                } else {
                                                    echo '<span class="status-badge status-user">Regular User</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <!-- <button class="action-btn view-btn" data-id="<?php echo $user['UserID']; ?>">
                                                        <i class="fa-solid fa-eye"></i> View
                                                    </button> -->

                                                    <?php if ($user['UserID'] != $_SESSION['user_id']): ?>


                                                        <button class="action-btn delete-btn"
                                                            data-id="<?php echo $user['UserID']; ?>"
                                                            data-name="<?php echo htmlspecialchars($user['FullName']); ?>">
                                                            <i class="fa-solid fa-trash"></i> Delete
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="current-user">Current User</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="no-data-cell">
                                            <div class="no-data">
                                                <i class="fa-solid fa-users-slash"></i>
                                                <p>No users found</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>

                        <!-- Pagination -->
                        <div class="pagination">
                            <button class="pagination-btn disabled">
                                <i class="fa-solid fa-chevron-left"></i> Previous
                            </button>
                            <span class="page-info">Page 1 of 1</span>
                            <button class="pagination-btn disabled">
                                Next <i class="fa-solid fa-chevron-right"></i>
                            </button>
                        </div>
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
                                <input type="hidden" name="user_id" id="modalUserId">
                                <input type="hidden" name="action" id="modalAction">

                                <div id="toggleSection" class="form-section" style="display: none;">
                                    <div class="confirmation-message">
                                        <i class="fa-solid fa-user-cog"></i>
                                        <p id="toggleMessage"></p>
                                    </div>
                                </div>

                                <div id="deleteSection" class="form-section" style="display: none;">
                                    <div class="confirmation-message">
                                        <i class="fa-solid fa-exclamation-triangle"></i>
                                        <p id="deleteMessage"></p>
                                    </div>
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" name="confirm_delete" required>
                                            I confirm that I want to delete this user. This action cannot be undone.
                                        </label>
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

    <script src="../js/admin-user.js?v=<?php echo time(); ?>"></script>
</body>

</html>
<?php $conn->close(); ?>