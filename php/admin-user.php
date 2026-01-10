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

// Handle actions (toggle status, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['user_id'])) {
        $user_id = intval($_POST['user_id']);
        $action = $_POST['action'];
        
        if ($action === 'toggle_status') {
            // Get current status
            $result = $conn->query("SELECT Role, FullName FROM user WHERE UserID = $user_id");
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                $new_role = $user['Role'] === 'admin' ? 'user' : 'admin';
                
                $stmt = $conn->prepare("UPDATE user SET Role = ? WHERE UserID = ?");
                $stmt->bind_param("si", $new_role, $user_id);
                
                if ($stmt->execute()) {
                    $_SESSION['notification'] = [
                        'message' => "User {$user['FullName']}'s role updated to $new_role",
                        'type' => 'success'
                    ];
                }
                $stmt->close();
            }
        }
        elseif ($action === 'delete' && isset($_POST['confirm_delete'])) {
            // Check if user has any bookings or rides
            $check_sql = "SELECT 
                (SELECT COUNT(*) FROM booking WHERE UserID = $user_id) as booking_count,
                (SELECT COUNT(*) FROM driver WHERE UserID = $user_id) as driver_count";
            
            $check_result = $conn->query($check_sql);
            $counts = $check_result->fetch_assoc();
            
            if ($counts['booking_count'] > 0 || $counts['driver_count'] > 0) {
                $_SESSION['notification'] = [
                    'message' => "Cannot delete user. User has existing bookings or driver registrations.",
                    'type' => 'error'
                ];
            } else {
                // Get user info before deletion
                $user_info = $conn->query("SELECT FullName FROM user WHERE UserID = $user_id")->fetch_assoc();
                
                $stmt = $conn->prepare("DELETE FROM user WHERE UserID = ?");
                $stmt->bind_param("i", $user_id);
                
                if ($stmt->execute()) {
                    $_SESSION['notification'] = [
                        'message' => "User {$user_info['FullName']} deleted successfully",
                        'type' => 'success'
                    ];
                }
                $stmt->close();
            }
        }
        
        header("Location: admin-user.php");
        exit();
    }
}

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
                                                    <button class="action-btn view-btn" data-id="<?php echo $user['UserID']; ?>">
                                                        <i class="fa-solid fa-eye"></i> View
                                                    </button>
                                                    
                                                    <?php if ($user['UserID'] != $_SESSION['user_id']): ?>
                                                        <button class="action-btn toggle-btn" 
                                                                data-id="<?php echo $user['UserID']; ?>"
                                                                data-current-role="<?php echo $user['Role']; ?>"
                                                                data-name="<?php echo htmlspecialchars($user['FullName']); ?>">
                                                            <i class="fa-solid fa-user-cog"></i> 
                                                            <?php echo $user['Role'] === 'admin' ? 'Make User' : 'Make Admin'; ?>
                                                        </button>
                                                        
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