<?php
// admin-userdb.php - Database functions for user management

function getUserDetails($conn, $user_id)
{
    $sql = "SELECT 
        u.*,
        COUNT(DISTINCT b.BookingID) as total_bookings,
        COUNT(DISTINCT r.RideID) as total_rides_offered,
        d.Status as driver_status
        FROM user u
        LEFT JOIN booking b ON u.UserID = b.UserID
        LEFT JOIN driver d ON u.UserID = d.UserID
        LEFT JOIN rides r ON d.DriverID = r.DriverID
        WHERE u.UserID = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    return $result->fetch_assoc();
}

function updateUserRole($conn, $user_id, $new_role)
{
    $sql = "UPDATE user SET Role = ? WHERE UserID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $new_role, $user_id);
    $result = $stmt->execute();
    $stmt->close();
    
    if ($result) {
        // Log the role change
        logUserActivity($conn, $user_id, "role_change", "Role changed to $new_role");
    }
    
    return $result;
}

function deleteUser($conn, $user_id)
{
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Check for dependencies
        $check_sql = "SELECT 
            (SELECT COUNT(*) FROM booking WHERE UserID = ?) as booking_count,
            (SELECT COUNT(*) FROM driver WHERE UserID = ?) as driver_count";
        
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("ii", $user_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $counts = $result->fetch_assoc();
        $stmt->close();
        
        if ($counts['booking_count'] > 0 || $counts['driver_count'] > 0) {
            throw new Exception("User has existing bookings or driver registrations");
        }
        
        // Delete from password_reset table first (foreign key constraint)
        $stmt = $conn->prepare("DELETE FROM password_reset WHERE UserID = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        
        // Delete from notifications table
        $stmt = $conn->prepare("DELETE FROM notifications WHERE UserID = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        
        // Delete from user table
        $stmt = $conn->prepare("DELETE FROM user WHERE UserID = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        return $affected_rows > 0;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        return false;
    }
}

function getUserStatistics($conn)
{
    $stats = [];
    
    // Total counts
    $stats['total'] = $conn->query("SELECT COUNT(*) as count FROM user")->fetch_assoc()['count'];
    $stats['admins'] = $conn->query("SELECT COUNT(*) as count FROM user WHERE Role = 'admin'")->fetch_assoc()['count'];
    $stats['users'] = $conn->query("SELECT COUNT(*) as count FROM user WHERE Role = 'user'")->fetch_assoc()['count'];
    
    // New users (last 30 days)
    $stats['new_users_30'] = $conn->query("
        SELECT COUNT(*) as count 
        FROM user 
        WHERE UserID IN (
            SELECT UserID FROM (
                SELECT UserID FROM user 
                ORDER BY UserID DESC LIMIT 50
            ) as recent
        ) AND UserID NOT IN (
            SELECT UserID FROM user WHERE Role = 'admin'
        )
    ")->fetch_assoc()['count'];
    
    // Users by faculty
    $stats['by_faculty'] = $conn->query("
        SELECT Faculty, COUNT(*) as count 
        FROM user 
        WHERE Faculty != '' 
        GROUP BY Faculty 
        ORDER BY count DESC
    ")->fetch_all(MYSQLI_ASSOC);
    
    // Users by gender
    $stats['by_gender'] = $conn->query("
        SELECT Gender, COUNT(*) as count 
        FROM user 
        WHERE Gender != '' 
        GROUP BY Gender 
        ORDER BY count DESC
    ")->fetch_all(MYSQLI_ASSOC);
    
    // Active users (made bookings in last 30 days)
    $stats['active_users'] = $conn->query("
        SELECT COUNT(DISTINCT u.UserID) as count
        FROM user u
        JOIN booking b ON u.UserID = b.UserID
        WHERE b.BookingDateTime >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ")->fetch_assoc()['count'];
    
    return $stats;
}

function searchUsers($conn, $search_term, $filters = [])
{
    $sql = "SELECT * FROM user WHERE 1=1";
    $params = [];
    $types = "";
    
    if (!empty($search_term)) {
        $sql .= " AND (FullName LIKE ? OR MatricNo LIKE ? OR Email LIKE ? OR Username LIKE ?)";
        $search_param = "%{$search_term}%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
        $types .= "ssss";
    }
    
    if (!empty($filters['role'])) {
        $sql .= " AND Role = ?";
        $params[] = $filters['role'];
        $types .= "s";
    }
    
    if (!empty($filters['faculty'])) {
        $sql .= " AND Faculty = ?";
        $params[] = $filters['faculty'];
        $types .= "s";
    }
    
    if (!empty($filters['gender'])) {
        $sql .= " AND Gender = ?";
        $params[] = $filters['gender'];
        $types .= "s";
    }
    
    $sql .= " ORDER BY UserID DESC";
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    return $result->fetch_all(MYSQLI_ASSOC);
}

function logUserActivity($conn, $user_id, $activity_type, $description)
{
    $sql = "INSERT INTO user_activity_log (UserID, ActivityType, Description, CreatedAt) 
            VALUES (?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $user_id, $activity_type, $description);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

function exportUsersData($conn, $format = 'csv')
{
    $sql = "SELECT 
        UserID,
        MatricNo,
        ICNo,
        FullName,
        Username,
        PhoneNumber,
        Email,
        Gender,
        Faculty,
        Role,
        'Active' as Status
        FROM user 
        ORDER BY UserID DESC";
    
    $result = $conn->query($sql);
    
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Headers
        fputcsv($output, [
            'User ID',
            'Matric No',
            'IC No',
            'Full Name',
            'Username',
            'Phone Number',
            'Email',
            'Gender',
            'Faculty',
            'Role',
            'Status'
        ]);
        
        // Data
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, $row);
        }
        
        fclose($output);
    } elseif ($format === 'json') {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d') . '.json"');
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        echo json_encode($data, JSON_PRETTY_PRINT);
    }
    
    exit();
}

function getUsersWithActivity($conn, $limit = 50, $offset = 0)
{
    $sql = "SELECT 
        u.*,
        (SELECT COUNT(*) FROM booking WHERE UserID = u.UserID) as booking_count,
        (SELECT Status FROM driver WHERE UserID = u.UserID LIMIT 1) as driver_status
        FROM user u
        ORDER BY u.UserID DESC
        LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $users = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $users;
}