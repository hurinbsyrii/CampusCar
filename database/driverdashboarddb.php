<?php
require_once 'config/database.php';

header('Content-Type: application/json');

// Get action parameter
$action = $_GET['action'] ?? '';
$driverId = $_GET['driverId'] ?? 0;

// Validate driver ID
if (!$driverId && $action !== 'createRide') {
    echo json_encode(['success' => false, 'message' => 'Invalid driver ID']);
    exit;
}

// Get User ID from driver ID
$driverUserId = getUserIdFromDriverId($driverId);

// Handle different actions
switch ($action) {
    case 'overview':
        getOverviewData($driverUserId, $driverId);
        break;

    case 'rideHistory':
        getRideHistory($driverUserId, $driverId);
        break;

    case 'earnings':
        getEarningsData($driverUserId, $driverId);
        break;

    case 'notifications':
        getNotifications($driverUserId);
        break;

    case 'notificationCount':
        getNotificationCount($driverUserId);
        break;

    case 'profile':
        getProfileData($driverUserId, $driverId);
        break;

    case 'createRide':
        createRide();
        break;

    case 'markAllRead':
        markAllNotificationsAsRead($driverUserId);
        break;

    case 'markAsRead':
        markNotificationAsRead($_GET['notificationId'] ?? 0);
        break;

    case 'paymentProofs':
        $proofs = getPaymentProofs($driverId);
        echo json_encode(['success' => true, 'proofs' => $proofs]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getUserIdFromDriverId($driverId)
{
    global $conn;

    $stmt = $conn->prepare("SELECT UserID FROM driver WHERE DriverID = ?");
    $stmt->bind_param("i", $driverId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        return $row['UserID'];
    }

    return 0;
}

function getOverviewData($userId, $driverId)
{
    global $conn;

    $data = [
        'success' => true,
        'stats' => [],
        'charts' => [],
        'recentActivities' => []
    ];

    // Get stats
    $data['stats'] = getDriverStats($driverId);

    // Get chart data
    $data['charts'] = getChartData($driverId);

    // Get recent activities (last 5)
    $data['recentActivities'] = getRecentActivities($driverId);

    echo json_encode($data);
}

function getDriverStats($driverId)
{
    global $conn;

    $stats = [
        'totalRides' => 0,
        'activeRides' => 0,
        'completedRides' => 0,
        'totalEarnings' => 0
    ];

    // Total rides offered
    $query = "SELECT COUNT(*) as total FROM rides WHERE DriverID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $driverId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['totalRides'] = $row['total'];

    // Active rides (available or in_progress)
    $query = "SELECT COUNT(*) as active FROM rides WHERE DriverID = ? AND Status IN ('available', 'in_progress')";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $driverId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['activeRides'] = $row['active'];

    // Completed rides
    $query = "SELECT COUNT(*) as completed FROM rides WHERE DriverID = ? AND Status = 'completed'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $driverId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['completedRides'] = $row['completed'];

    // Total earnings
    $query = "SELECT COALESCE(SUM(Amount), 0) as total FROM driver_earnings WHERE DriverID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $driverId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['totalEarnings'] = floatval($row['total']);

    return $stats;
}

function getChartData($driverId)
{
    global $conn;

    $charts = [
        'earnings' => ['labels' => [], 'data' => []],
        'gender' => [0, 0],
        'faculty' => ['labels' => [], 'data' => []],
        'payment' => ['labels' => [], 'data' => []]
    ];

    // Monthly earnings for last 6 months
    $query = "
        SELECT 
            DATE_FORMAT(PaymentDate, '%b %Y') as month,
            SUM(Amount) as total
        FROM driver_earnings 
        WHERE DriverID = ? 
          AND PaymentDate >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY YEAR(PaymentDate), MONTH(PaymentDate)
        ORDER BY YEAR(PaymentDate), MONTH(PaymentDate)
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $driverId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $charts['earnings']['labels'][] = $row['month'];
        $charts['earnings']['data'][] = floatval($row['total']);
    }

    // Gender distribution of passengers
    $query = "
        SELECT 
            u.Gender,
            COUNT(DISTINCT b.UserID) as count
        FROM booking b
        JOIN user u ON b.UserID = u.UserID
        JOIN rides r ON b.RideID = r.RideID
        WHERE r.DriverID = ? 
          AND b.BookingStatus IN ('Confirmed', 'Completed', 'Paid')
        GROUP BY u.Gender
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $driverId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        if ($row['Gender'] === 'male') {
            $charts['gender'][0] = intval($row['count']);
        } elseif ($row['Gender'] === 'female') {
            $charts['gender'][1] = intval($row['count']);
        }
    }

    // Faculty distribution
    $query = "
        SELECT 
            u.Faculty,
            COUNT(DISTINCT b.UserID) as count
        FROM booking b
        JOIN user u ON b.UserID = u.UserID
        JOIN rides r ON b.RideID = r.RideID
        WHERE r.DriverID = ? 
          AND b.BookingStatus IN ('Confirmed', 'Completed', 'Paid')
        GROUP BY u.Faculty
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $driverId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $charts['faculty']['labels'][] = $row['Faculty'];
        $charts['faculty']['data'][] = intval($row['count']);
    }

    // Payment methods distribution
    $query = "
        SELECT 
            p.PaymentMethod,
            COUNT(*) as count
        FROM payments p
        JOIN booking b ON p.BookingID = b.BookingID
        JOIN rides r ON b.RideID = r.RideID
        WHERE r.DriverID = ? 
          AND p.PaymentStatus = 'paid'
        GROUP BY p.PaymentMethod
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $driverId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $charts['payment']['labels'][] = ucfirst(str_replace('_', ' ', $row['PaymentMethod']));
        $charts['payment']['data'][] = intval($row['count']);
    }

    return $charts;
}

function getRecentActivities($driverId)
{
    global $conn;

    $activities = [];

    $query = "
        SELECT 
            Title,
            Message,
            Type,
            CreatedAt
        FROM notifications n
        JOIN user u ON n.UserID = u.UserID
        JOIN driver d ON u.UserID = d.UserID
        WHERE d.DriverID = ?
        ORDER BY CreatedAt DESC
        LIMIT 5
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $driverId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $activities[] = [
            'message' => $row['Message'],
            'type' => $row['Type'],
            'time' => timeAgo($row['CreatedAt'])
        ];
    }

    return $activities;
}

function getRideHistory($userId, $driverId)
{
    global $conn;

    $page = $_GET['page'] ?? 1;
    $status = $_GET['status'] ?? 'all';
    $date = $_GET['date'] ?? '';
    $limit = 10;
    $offset = ($page - 1) * $limit;

    // Build query with filters
    $query = "
        SELECT 
            r.RideID as id,
            r.FromLocation as from_loc,
            r.ToLocation as to_loc,
            CONCAT(r.RideDate, ' ', r.DepartureTime) as datetime,
            r.AvailableSeats as seats,
            r.PricePerSeat as price,
            r.Status,
            COALESCE(SUM(de.Amount), 0) as earnings,
            COUNT(b.BookingID) as passengers
        FROM rides r
        LEFT JOIN booking b ON r.RideID = b.RideID 
            AND b.BookingStatus IN ('Confirmed', 'Completed', 'Paid')
        LEFT JOIN driver_earnings de ON r.RideID = de.RideID
        WHERE r.DriverID = ?
    ";

    $params = [$driverId];
    $types = "i";

    if ($status !== 'all') {
        $query .= " AND r.Status = ?";
        $params[] = $status;
        $types .= "s";
    }

    if ($date !== '') {
        $query .= " AND DATE(r.RideDate) = ?";
        $params[] = $date;
        $types .= "s";
    }

    $query .= " GROUP BY r.RideID ORDER BY r.RideDate DESC, r.DepartureTime DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $rides = [];
    while ($row = $result->fetch_assoc()) {
        $rideDate = new DateTime($row['datetime']);

        $rides[] = [
            'id' => $row['id'],
            'date' => $rideDate->format('Y-m-d'),
            'time' => $rideDate->format('H:i'),
            'from' => $row['from_loc'],
            'to' => $row['to_loc'],
            'passengers' => $row['passengers'],
            'earnings' => number_format($row['earnings'], 2),
            'status' => $row['Status']
        ];
    }

    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) as total FROM rides WHERE DriverID = ?";
    if ($status !== 'all') {
        $countQuery .= " AND Status = ?";
    }
    if ($date !== '') {
        $countQuery .= " AND DATE(RideDate) = ?";
    }

    $stmt = $conn->prepare($countQuery);

    if ($status !== 'all' && $date !== '') {
        $stmt->bind_param("iss", $driverId, $status, $date);
    } elseif ($status !== 'all') {
        $stmt->bind_param("is", $driverId, $status);
    } elseif ($date !== '') {
        $stmt->bind_param("is", $driverId, $date);
    } else {
        $stmt->bind_param("i", $driverId);
    }

    $stmt->execute();
    $countResult = $stmt->get_result();
    $total = $countResult->fetch_assoc()['total'];
    $totalPages = ceil($total / $limit);

    echo json_encode([
        'success' => true,
        'rides' => $rides,
        'totalPages' => $totalPages,
        'currentPage' => $page
    ]);
}

function getEarningsData($userId, $driverId)
{
    global $conn;

    $data = [
        'success' => true,
        'summary' => [],
        'recentPayments' => [],
        'earningsTrend' => []
    ];

    // Get earnings summary
    $query = "
        SELECT 
            COALESCE(SUM(Amount), 0) as total_earnings,
            COALESCE(AVG(Amount), 0) as avg_per_ride,
            COALESCE(SUM(CASE WHEN MONTH(PaymentDate) = MONTH(NOW()) 
                AND YEAR(PaymentDate) = YEAR(NOW()) THEN Amount ELSE 0 END), 0) as monthly_earnings
        FROM driver_earnings 
        WHERE DriverID = ?
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $driverId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $data['summary'] = [
            'totalEarnings' => floatval($row['total_earnings']),
            'monthlyEarnings' => floatval($row['monthly_earnings']),
            'averagePerRide' => floatval($row['avg_per_ride'])
        ];
    }

    // Get recent payments
    $query = "
        SELECT 
            de.PaymentDate as date,
            de.BookingID as booking_id,
            de.Amount,
            p.PaymentMethod,
            p.PaymentStatus
        FROM driver_earnings de
        JOIN payments p ON de.BookingID = p.BookingID
        WHERE de.DriverID = ?
        ORDER BY de.PaymentDate DESC
        LIMIT 10
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $driverId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $paymentDate = new DateTime($row['date']);

        $data['recentPayments'][] = [
            'date' => $paymentDate->format('Y-m-d H:i'),
            'bookingId' => $row['booking_id'],
            'amount' => number_format($row['Amount'], 2),
            'method' => ucfirst(str_replace('_', ' ', $row['PaymentMethod'])),
            'status' => $row['PaymentStatus']
        ];
    }

    // Get earnings trend for chart
    $query = "
        SELECT 
            DATE_FORMAT(PaymentDate, '%b %d') as day,
            SUM(Amount) as daily_earnings
        FROM driver_earnings 
        WHERE DriverID = ? 
          AND PaymentDate >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(PaymentDate)
        ORDER BY DATE(PaymentDate)
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $driverId);
    $stmt->execute();
    $result = $stmt->get_result();

    $data['earningsTrend'] = [
        'labels' => [],
        'data' => []
    ];

    while ($row = $result->fetch_assoc()) {
        $data['earningsTrend']['labels'][] = $row['day'];
        $data['earningsTrend']['data'][] = floatval($row['daily_earnings']);
    }

    echo json_encode($data);
}

function getNotifications($userId)
{
    global $conn;

    $query = "
        SELECT 
            NotificationID as id,
            Title,
            Message,
            Type,
            IsRead,
            CreatedAt
        FROM notifications 
        WHERE UserID = ?
        ORDER BY CreatedAt DESC
        LIMIT 20
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $notifications = [];
    $unreadCount = 0;

    while ($row = $result->fetch_assoc()) {
        $notificationDate = new DateTime($row['CreatedAt']);

        $notifications[] = [
            'id' => $row['id'],
            'title' => $row['Title'],
            'message' => $row['Message'],
            'type' => $row['Type'],
            'unread' => !$row['IsRead'],
            'time' => timeAgo($row['CreatedAt'])
        ];

        if (!$row['IsRead']) {
            $unreadCount++;
        }
    }

    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unreadCount' => $unreadCount
    ]);
}

function getNotificationCount($userId)
{
    global $conn;

    $query = "SELECT COUNT(*) as count FROM notifications WHERE UserID = ? AND IsRead = 0";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    echo json_encode([
        'success' => true,
        'count' => intval($row['count'])
    ]);
}

function getProfileData($userId, $driverId)
{
    global $conn;

    $query = "
        SELECT 
            u.FullName,
            u.Email,
            u.PhoneNumber,
            d.LicenseNumber,
            d.CarModel,
            d.CarPlateNumber,
            d.BankName,
            d.AccountNumber,
            d.Status
        FROM user u
        JOIN driver d ON u.UserID = d.UserID
        WHERE d.DriverID = ?
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $driverId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'profile' => [
                'fullName' => $row['FullName'],
                'email' => $row['Email'],
                'phone' => $row['PhoneNumber'],
                'licenseNumber' => $row['LicenseNumber'],
                'carModel' => $row['CarModel'],
                'carPlate' => $row['CarPlateNumber'],
                'bankName' => $row['BankName'],
                'accountNumber' => $row['AccountNumber'],
                'status' => $row['Status']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Profile not found']);
    }
}

function createRide()
{
    global $conn;

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        return;
    }

    // Validate required fields
    $required = ['driverId', 'fromLocation', 'toLocation', 'rideDate', 'departureTime', 'availableSeats', 'pricePerSeat'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
            return;
        }
    }

    // Insert ride
    $query = "
        INSERT INTO rides (
            DriverID, FromLocation, ToLocation, RideDate, DepartureTime, 
            AvailableSeats, PricePerSeat, RideDescription, Status, FemaleOnly
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'available', ?)
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param(
        "issssidsi",
        $input['driverId'],
        $input['fromLocation'],
        $input['toLocation'],
        $input['rideDate'],
        $input['departureTime'],
        $input['availableSeats'],
        $input['pricePerSeat'],
        $input['description'] ?? '',
        $input['femaleOnly'] ?? 0
    );

    if ($stmt->execute()) {
        $rideId = $conn->insert_id;

        // Create notification for driver
        createRideNotification($input['driverId'], $rideId);

        echo json_encode(['success' => true, 'rideId' => $rideId]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create ride']);
    }
}

function createRideNotification($driverId, $rideId)
{
    global $conn;

    // Get driver user ID
    $query = "SELECT UserID FROM driver WHERE DriverID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $driverId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $userId = $row['UserID'];

        // Get ride details for notification message
        $query = "SELECT FromLocation, ToLocation, RideDate, DepartureTime, PricePerSeat FROM rides WHERE RideID = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $rideId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($ride = $result->fetch_assoc()) {
            $rideDate = new DateTime($ride['RideDate'] . ' ' . $ride['DepartureTime']);

            $message = sprintf(
                "You have successfully offered a ride from %s to %s on %s. Price: RM%.2f per seat.",
                $ride['FromLocation'],
                $ride['ToLocation'],
                $rideDate->format('d M Y \a\t h:i A'),
                $ride['PricePerSeat']
            );

            $query = "
                INSERT INTO notifications (UserID, Title, Message, Type, RelatedID, RelatedType)
                VALUES (?, 'Ride Offered', ?, 'success', ?, 'ride')
            ";

            $stmt = $conn->prepare($query);
            $stmt->bind_param("isi", $userId, $message, $rideId);
            $stmt->execute();
        }
    }
}

function markAllNotificationsAsRead($userId)
{
    global $conn;

    $query = "UPDATE notifications SET IsRead = 1 WHERE UserID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update notifications']);
    }
}

function markNotificationAsRead($notificationId)
{
    global $conn;

    $query = "UPDATE notifications SET IsRead = 1 WHERE NotificationID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $notificationId);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update notification']);
    }
}

// Helper function to format time ago
function timeAgo($datetime)
{
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;

    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M d, Y', $time);
    }
}

// Add this function to driverdashboarddb.php
function getPaymentProofs($driverId)
{
    global $conn;

    $query = "
        SELECT 
            p.PaymentID,
            p.ProofPath,
            p.Amount,
            p.PaymentMethod,
            p.PaymentStatus,
            p.PaymentDate,
            u.FullName as PassengerName,
            r.FromLocation,
            r.ToLocation
        FROM payments p
        JOIN booking b ON p.BookingID = b.BookingID
        JOIN rides r ON b.RideID = r.RideID
        JOIN user u ON p.UserID = u.UserID
        WHERE r.DriverID = ? 
          AND p.ProofPath IS NOT NULL 
          AND p.ProofPath != ''
        ORDER BY p.PaymentDate DESC
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $driverId);
    $stmt->execute();
    $result = $stmt->get_result();

    $proofs = [];
    while ($row = $result->fetch_assoc()) {
        $proofs[] = [
            'paymentId' => $row['PaymentID'],
            'proofPath' => $row['ProofPath'],
            'amount' => number_format($row['Amount'], 2),
            'method' => ucfirst(str_replace('_', ' ', $row['PaymentMethod'])),
            'status' => $row['PaymentStatus'],
            'date' => $row['PaymentDate'],
            'passenger' => $row['PassengerName'],
            'ride' => $row['FromLocation'] . ' → ' . $row['ToLocation']
        ];
    }

    return $proofs;
}
