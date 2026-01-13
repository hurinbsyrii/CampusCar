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

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['user_id'])) {
        $user_id = intval($_POST['user_id']);
        $action = $_POST['action'];

        // --- 1. AJAX Check Activity (Dikemaskini: Kira Penumpang & Pemandu) ---
        if ($action === 'check_activity') {

            // A. Kira booking dia sebagai PENUMPANG (Dia tumpang orang)
            $passenger_sql = "SELECT COUNT(*) as count FROM booking 
                            WHERE UserID = $user_id 
                            AND BookingStatus IN ('Pending', 'Confirmed')";

            // B. Kira booking orang lain pada ride dia (Dia sebagai DRIVER)
            $driver_booking_sql = "SELECT COUNT(*) as count FROM booking b
                                   JOIN rides r ON b.RideID = r.RideID
                                   JOIN driver d ON r.DriverID = d.DriverID
                                   WHERE d.UserID = $user_id 
                                   AND b.BookingStatus IN ('Pending', 'Confirmed')";

            // C. Kira Ride Aktif (Offer yang dia buat)
            $ride_sql = "SELECT COUNT(*) as count FROM rides r
                         JOIN driver d ON r.DriverID = d.DriverID
                         WHERE d.UserID = $user_id 
                         AND r.Status IN ('available', 'pending', 'confirmed')
                         AND r.RideDate >= CURRENT_DATE";

            $pass_count = $conn->query($passenger_sql)->fetch_assoc()['count'];
            $driver_book_count = $conn->query($driver_booking_sql)->fetch_assoc()['count'];
            $rides = $conn->query($ride_sql)->fetch_assoc()['count'];

            // Campurkan dua-dua jenis booking
            $total_active_bookings = $pass_count + $driver_book_count;

            echo json_encode([
                'status' => 'success',
                'active_bookings' => $total_active_bookings, // Total Penumpang + Orang Tumpang Dia
                'active_rides' => $rides,
                'total_active' => $total_active_bookings + $rides
            ]);
            exit();
        }

        // --- 2. Toggle Role ---
        if ($action === 'toggle_status') {
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
        }

        // --- 3. Delete User (Logik: Cancel Ride -> Notify -> Delete) ---
        elseif ($action === 'delete' && isset($_POST['confirm_delete'])) {
            $conn->begin_transaction();

            try {
                // A. Handle Jika User Adalah DRIVER
                $driver_check = $conn->query("SELECT DriverID FROM driver WHERE UserID = $user_id");

                if ($driver_check->num_rows > 0) {
                    $driver = $driver_check->fetch_assoc();
                    $driver_id = $driver['DriverID'];

                    // Cari Active Rides untuk dimaklumkan kepada penumpang
                    $active_rides_query = "SELECT RideID, RideDate, FromLocation, ToLocation 
                                           FROM rides 
                                           WHERE DriverID = $driver_id 
                                           AND Status NOT IN ('cancelled', 'completed', 'expired')";
                    $rides_result = $conn->query($active_rides_query);

                    while ($ride = $rides_result->fetch_assoc()) {
                        $ride_id = $ride['RideID'];

                        // Dapatkan senarai penumpang untuk notifikasi
                        $passengers = $conn->query("SELECT UserID FROM booking WHERE RideID = $ride_id");
                        while ($p = $passengers->fetch_assoc()) {
                            // Masukkan Notifikasi
                            $msg = "The ride from " . $ride['FromLocation'] . " on " . $ride['RideDate'] . " has been CANCELLED by Admin because the driver was removed.";
                            $notif_sql = "INSERT INTO notifications (UserID, Title, Message, Type, IsRead, CreatedAt) 
                                          VALUES (?, 'Ride Cancelled (Admin)', ?, 'warning', 0, NOW())";
                            $stmt = $conn->prepare($notif_sql);
                            $stmt->bind_param("is", $p['UserID'], $msg);
                            $stmt->execute();
                        }

                        // Update status booking ke Cancelled (sebagai rekod sebelum delete, jika perlu)
                        $conn->query("UPDATE booking SET BookingStatus = 'Cancelled', CancellationReason = 'Driver Deleted by Admin' WHERE RideID = $ride_id");
                    }

                    // Padam data berkaitan Driver
                    $conn->query("DELETE FROM booking WHERE RideID IN (SELECT RideID FROM rides WHERE DriverID = $driver_id)");
                    $conn->query("DELETE FROM driver_earnings WHERE DriverID = $driver_id");
                    $conn->query("DELETE FROM reviews WHERE DriverID = $driver_id");
                    $conn->query("DELETE FROM rides WHERE DriverID = $driver_id");
                    $conn->query("DELETE FROM driver WHERE DriverID = $driver_id");
                }

                // ... (Previous code for A. Driver handling remains above) ...

                // B. Handle Jika User Adalah PASSENGER

                // --- STEP 1: Restore Available Seats for Confirmed Bookings ---
                // Cari booking yang statusnya 'Confirmed' sebelum didelete
                $check_seats_sql = "SELECT RideID FROM booking WHERE UserID = $user_id AND BookingStatus = 'Confirmed'";
                $confirmed_bookings = $conn->query($check_seats_sql);

                if ($confirmed_bookings->num_rows > 0) {
                    // Prepare statement untuk update seat
                    $update_seat_stmt = $conn->prepare("UPDATE rides SET AvailableSeats = AvailableSeats + 1 WHERE RideID = ?");

                    while ($row = $confirmed_bookings->fetch_assoc()) {
                        $ride_id_to_update = $row['RideID'];
                        // Tambah balik seat (+1)
                        $update_seat_stmt->bind_param("i", $ride_id_to_update);
                        $update_seat_stmt->execute();
                    }
                    $update_seat_stmt->close();
                }

                // --- STEP 2: Delete Data ---
                $conn->query("DELETE FROM payments WHERE UserID = $user_id");
                $conn->query("DELETE FROM booking WHERE UserID = $user_id"); // Booking deleted after seats returned
                $conn->query("DELETE FROM notifications WHERE UserID = $user_id");
                $conn->query("DELETE FROM password_reset WHERE UserID = $user_id");
                $conn->query("DELETE FROM reviews WHERE UserID = $user_id");

                // C. Akhir Sekali: Padam User
                $stmt = $conn->prepare("DELETE FROM user WHERE UserID = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();

                $conn->commit();

                // ... (Rest of code) ...
                $_SESSION['notification'] = [
                    'message' => "User deleted successfully. Active rides/bookings were cancelled and notifications sent.",
                    'type' => 'success'
                ];
            } catch (Exception $e) {
                $conn->rollback();
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

// ... (Kod search filter anda di atas kekal sama) ...

// --- PAGINATION LOGIC MULA (DIKEMASKINI) ---

// 1. Set berapa data per page
$results_per_page = 6; //tukar nilai ini untuk ubah bilangan data per page

// 2. Dapatkan page semasa dari URL
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

// 3. Kira OFFSET
$page_first_result = ($page - 1) * $results_per_page;

// 4. Bina Query Filter (Untuk digunakan dalam Count & Select)
$where_clause = "WHERE 1=1";

if (!empty($search_term)) {
    $where_clause .= " AND (FullName LIKE '%$search_term%' 
                       OR MatricNo LIKE '%$search_term%' 
                       OR Email LIKE '%$search_term%'
                       OR Username LIKE '%$search_term%')";
}

if (!empty($role_filter)) {
    $where_clause .= " AND Role = '$role_filter'";
}

if (!empty($faculty_filter)) {
    $where_clause .= " AND Faculty = '$faculty_filter'";
}

// 5. Kira TOTAL Data (Guna COUNT(*) lebih tepat & laju)
$count_sql = "SELECT COUNT(*) as total FROM user " . $where_clause;
$count_result = $conn->query($count_sql);
$count_row = $count_result->fetch_assoc();
$number_of_results = $count_row['total'];

// Kira jumlah page
$number_of_pages = ceil($number_of_results / $results_per_page);

// Pastikan page tak lebih dari jumlah page yang ada
if ($page > $number_of_pages && $number_of_pages > 0) {
    $page = $number_of_pages;
    // Kira balik offset jika page berubah
    $page_first_result = ($page - 1) * $results_per_page;
}

// 6. Jalankan Query Sebenar untuk Paparan Data
$sql = "SELECT * FROM user " . $where_clause . " ORDER BY UserID DESC LIMIT " . $page_first_result . ',' . $results_per_page;
$result = $conn->query($sql);

// --- PAGINATION LOGIC TAMAT ---

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
                                                    // Jika dia driver (tak kira admin atau user), tunjuk status driver
                                                    $driver_status = $driver_check->fetch_assoc()['Status'];
                                                    echo '<span class="status-badge status-' . strtolower($driver_status) . '">';
                                                    echo '<i class="fa-solid fa-car"></i> ' . ucfirst($driver_status);
                                                    echo '</span>';
                                                } elseif ($user['Role'] === 'admin') {
                                                    // Jika ADMIN dan bukan driver, tunjuk "Administrator"
                                                    echo '<span class="status-badge status-admin">';
                                                    echo '<i class="fa-solid fa-shield-halved"></i> Administrator';
                                                    echo '</span>';
                                                } else {
                                                    // Jika USER biasa dan bukan driver
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
                                                        <!-- <button class="action-btn toggle-btn"
                                                            data-id="<?php echo $user['UserID']; ?>"
                                                            data-current-role="<?php echo $user['Role']; ?>"
                                                            data-name="<?php echo htmlspecialchars($user['FullName']); ?>">
                                                            <i class="fa-solid fa-user-cog"></i>
                                                            <?php echo $user['Role'] === 'admin' ? 'Make User' : 'Make Admin'; ?>
                                                        </button> -->

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

                        <div class="pagination">
                            <?php
                            // Check supaya function tak clash
                            if (!function_exists('build_url')) {
                                function build_url($page)
                                {
                                    $params = $_GET;
                                    $params['page'] = $page;
                                    return '?' . http_build_query($params);
                                }
                            }
                            ?>

                            <?php if ($page > 1): ?>
                                <a href="<?php echo build_url($page - 1); ?>" class="pagination-btn">
                                    <i class="fa-solid fa-chevron-left"></i> Previous
                                </a>
                            <?php else: ?>
                                <button class="pagination-btn disabled" disabled>
                                    <i class="fa-solid fa-chevron-left"></i> Previous
                                </button>
                            <?php endif; ?>

                            <span class="page-info">
                                Page <?php echo $page; ?> of <?php echo ($number_of_pages > 0) ? $number_of_pages : 1; ?>
                            </span>

                            <?php if ($page < $number_of_pages): ?>
                                <a href="<?php echo build_url($page + 1); ?>" class="pagination-btn">
                                    Next <i class="fa-solid fa-chevron-right"></i>
                                </a>
                            <?php else: ?>
                                <button class="pagination-btn disabled" disabled>
                                    Next <i class="fa-solid fa-chevron-right"></i>
                                </button>
                            <?php endif; ?>
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