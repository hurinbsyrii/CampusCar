<?php
// admindashboarddb.php - Additional database functions for admin dashboard

function getAdminStats($conn)
{
    $stats = [];

    // Today's stats
    $stats['today_users'] = $conn->query("SELECT COUNT(*) as count FROM user WHERE DATE(CreatedAt) = CURDATE() AND Role = 'user'")->fetch_assoc()['count'];
    $stats['today_rides'] = $conn->query("SELECT COUNT(*) as count FROM rides WHERE DATE(RideDate) = CURDATE()")->fetch_assoc()['count'];
    $stats['today_bookings'] = $conn->query("SELECT COUNT(*) as count FROM booking WHERE DATE(BookingDateTime) = CURDATE()")->fetch_assoc()['count'];

    // Monthly stats
    $stats['monthly_users'] = $conn->query("SELECT COUNT(*) as count FROM user WHERE MONTH(CreatedAt) = MONTH(CURDATE()) AND YEAR(CreatedAt) = YEAR(CURDATE()) AND Role = 'user'")->fetch_assoc()['count'];
    $stats['monthly_rides'] = $conn->query("SELECT COUNT(*) as count FROM rides WHERE MONTH(RideDate) = MONTH(CURDATE()) AND YEAR(RideDate) = YEAR(CURDATE())")->fetch_assoc()['count'];
    $stats['monthly_bookings'] = $conn->query("SELECT COUNT(*) as count FROM booking WHERE MONTH(BookingDateTime) = MONTH(CURDATE()) AND YEAR(BookingDateTime) = YEAR(CURDATE())")->fetch_assoc()['count'];

    // Popular routes
    $stats['popular_routes'] = $conn->query("SELECT FromLocation, ToLocation, COUNT(*) as ride_count FROM rides GROUP BY FromLocation, ToLocation ORDER BY ride_count DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

    // Active drivers
    $stats['active_drivers'] = $conn->query("SELECT COUNT(DISTINCT DriverID) as count FROM rides WHERE RideDate >= CURDATE() OR Status = 'available'")->fetch_assoc()['count'];

    return $stats;
}

function getAdminReport($conn, $startDate, $endDate)
{
    $report = [];

    $report['period_users'] = $conn->query("SELECT COUNT(*) as count FROM user WHERE CreatedAt BETWEEN '$startDate' AND '$endDate' AND Role = 'user'")->fetch_assoc()['count'];
    $report['period_drivers'] = $conn->query("SELECT COUNT(*) as count FROM driver d JOIN user u ON d.UserID = u.UserID WHERE u.CreatedAt BETWEEN '$startDate' AND '$endDate'")->fetch_assoc()['count'];
    $report['period_rides'] = $conn->query("SELECT COUNT(*) as count FROM rides WHERE RideDate BETWEEN '$startDate' AND '$endDate'")->fetch_assoc()['count'];
    $report['period_bookings'] = $conn->query("SELECT COUNT(*) as count FROM booking WHERE BookingDateTime BETWEEN '$startDate' AND '$endDate'")->fetch_assoc()['count'];

    // Booking status breakdown
    $report['booking_status'] = $conn->query("SELECT BookingStatus, COUNT(*) as count FROM booking WHERE BookingDateTime BETWEEN '$startDate' AND '$endDate' GROUP BY BookingStatus")->fetch_all(MYSQLI_ASSOC);

    // Ride status breakdown
    $report['ride_status'] = $conn->query("SELECT Status, COUNT(*) as count FROM rides WHERE RideDate BETWEEN '$startDate' AND '$endDate' GROUP BY Status")->fetch_all(MYSQLI_ASSOC);

    return $report;
}

function deleteUser($conn, $userID)
{
    // First check if user is a driver
    $driverCheck = $conn->query("SELECT DriverID FROM driver WHERE UserID = $userID");

    if ($driverCheck->num_rows > 0) {
        $driverID = $driverCheck->fetch_assoc()['DriverID'];

        // Delete driver's rides
        $conn->query("DELETE FROM rides WHERE DriverID = $driverID");

        // Delete driver record
        $conn->query("DELETE FROM driver WHERE UserID = $userID");
    }

    // Delete user's bookings
    $conn->query("DELETE FROM booking WHERE UserID = $userID");

    // Delete user's notifications
    $conn->query("DELETE FROM notifications WHERE UserID = $userID");

    // Finally delete user
    $result = $conn->query("DELETE FROM user WHERE UserID = $userID");

    return $result;
}

function getSystemLogs($conn, $limit = 100)
{
    // This would need a logs table
    // For now, we can return booking and ride creation as logs
    $logs = [];

    // Get recent bookings as logs
    $bookingLogs = $conn->query("SELECT 
        'booking' as type,
        CONCAT('Booking #', BookingID) as title,
        CONCAT('User booked ', NoOfSeats, ' seat(s)') as description,
        BookingDateTime as timestamp
        FROM booking 
        ORDER BY BookingDateTime DESC 
        LIMIT $limit")->fetch_all(MYSQLI_ASSOC);

    // Get recent rides as logs
    $rideLogs = $conn->query("SELECT 
        'ride' as type,
        CONCAT('Ride #', RideID) as title,
        CONCAT('Ride from ', FromLocation, ' to ', ToLocation) as description,
        CONCAT(RideDate, ' ', DepartureTime) as timestamp
        FROM rides 
        ORDER BY RideDate DESC, DepartureTime DESC 
        LIMIT $limit")->fetch_all(MYSQLI_ASSOC);

    // Get user registrations as logs
    $userLogs = $conn->query("SELECT 
        'user' as type,
        'User Registration' as title,
        CONCAT(FullName, ' (', MatricNo, ') registered') as description,
        CreatedAt as timestamp
        FROM user 
        ORDER BY CreatedAt DESC 
        LIMIT $limit")->fetch_all(MYSQLI_ASSOC);

    // Combine and sort by timestamp
    $allLogs = array_merge($bookingLogs, $rideLogs, $userLogs);
    usort($allLogs, function ($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });

    return array_slice($allLogs, 0, $limit);
}
