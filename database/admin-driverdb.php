<?php
// admin-driverdb.php - Database functions for driver management

function getDriverDetails($conn, $driver_id)
{
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
        WHERE d.DriverID = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    return $result->fetch_assoc();
}

function updateDriverStatus($conn, $driver_id, $status, $rejection_reason = null)
{
    if ($status === 'approved') {
        $sql = "UPDATE driver SET Status = ?, ApprovedDate = NOW(), RejectionReason = NULL WHERE DriverID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $status, $driver_id);
    } elseif ($status === 'rejected') {
        $sql = "UPDATE driver SET Status = ?, ApprovedDate = NULL, RejectionReason = ? WHERE DriverID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $status, $rejection_reason, $driver_id);
    } else {
        $sql = "UPDATE driver SET Status = ?, ApprovedDate = NULL WHERE DriverID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $status, $driver_id);
    }

    $result = $stmt->execute();
    $stmt->close();

    if ($result) {
        // Send notification to driver
        $driver_info = getDriverDetails($conn, $driver_id);
        if ($driver_info) {
            sendDriverNotification($conn, $driver_info['UserID'], $status, $driver_id, $rejection_reason);
        }
    }

    return $result;
}

function sendDriverNotification($conn, $user_id, $status, $driver_id, $reason = null)
{
    $messages = [
        'approved' => [
            'title' => 'Driver Registration Approved',
            'message' => 'Your driver registration has been approved. You can now offer rides.',
            'type' => 'success'
        ],
        'rejected' => [
            'title' => 'Driver Registration Rejected',
            'message' => 'Your driver registration was rejected. Reason: ' . $reason,
            'type' => 'warning'
        ],
        'pending' => [
            'title' => 'Driver Status Updated',
            'message' => 'Your driver status has been updated to pending review.',
            'type' => 'info'
        ]
    ];

    $message = $messages[$status] ?? $messages['pending'];

    $sql = "INSERT INTO notifications (UserID, Title, Message, Type, CreatedAt, RelatedID, RelatedType) 
            VALUES (?, ?, ?, ?, NOW(), ?, 'driver_status')";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssi", $user_id, $message['title'], $message['message'], $message['type'], $driver_id);
    $result = $stmt->execute();
    $stmt->close();

    return $result;
}

function getDriverStatistics($conn)
{
    $stats = [];

    // Total counts
    $stats['total'] = $conn->query("SELECT COUNT(*) as count FROM driver")->fetch_assoc()['count'];
    $stats['pending'] = $conn->query("SELECT COUNT(*) as count FROM driver WHERE Status = 'pending'")->fetch_assoc()['count'];
    $stats['approved'] = $conn->query("SELECT COUNT(*) as count FROM driver WHERE Status = 'approved'")->fetch_assoc()['count'];
    $stats['rejected'] = $conn->query("SELECT COUNT(*) as count FROM driver WHERE Status = 'rejected'")->fetch_assoc()['count'];

    // Recent registrations (last 30 days)
    $stats['recent_registrations'] = $conn->query("
        SELECT DATE(RegistrationDate) as date, COUNT(*) as count 
        FROM driver 
        WHERE RegistrationDate >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(RegistrationDate)
        ORDER BY date DESC
    ")->fetch_all(MYSQLI_ASSOC);

    // Average approval time
    $result = $conn->query("
        SELECT AVG(TIMESTAMPDIFF(HOUR, RegistrationDate, ApprovedDate)) as avg_hours
        FROM driver 
        WHERE Status = 'approved' AND ApprovedDate IS NOT NULL
    ");

    if ($result) {
        $row = $result->fetch_assoc();
        $avg_hours = $row['avg_hours'];
        $stats['avg_approval_time'] = $avg_hours ? round($avg_hours, 1) : 'N/A';
    } else {
        $stats['avg_approval_time'] = 'N/A';
    }

    // Drivers by faculty
    $stats['by_faculty'] = $conn->query("
        SELECT u.Faculty, COUNT(*) as count 
        FROM driver d 
        JOIN user u ON d.UserID = u.UserID 
        WHERE d.Status = 'approved'
        GROUP BY u.Faculty
        ORDER BY count DESC
    ")->fetch_all(MYSQLI_ASSOC);

    return $stats;
}

function searchDrivers($conn, $search_term, $status_filter = null)
{
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
        WHERE (u.FullName LIKE ? OR u.MatricNo LIKE ? OR u.Email LIKE ? 
               OR d.LicenseNumber LIKE ? OR d.CarPlateNumber LIKE ?)";

    if ($status_filter && $status_filter !== 'all') {
        $sql .= " AND d.Status = ?";
    }

    $sql .= " ORDER BY d.RegistrationDate DESC";

    $search_param = "%{$search_term}%";

    $stmt = $conn->prepare($sql);

    if ($status_filter && $status_filter !== 'all') {
        $stmt->bind_param(
            "ssssss",
            $search_param,
            $search_param,
            $search_param,
            $search_param,
            $search_param,
            $status_filter
        );
    } else {
        $stmt->bind_param(
            "sssss",
            $search_param,
            $search_param,
            $search_param,
            $search_param,
            $search_param
        );
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    return $result->fetch_all(MYSQLI_ASSOC);
}

function exportDriversData($conn, $format = 'csv')
{
    $sql = "SELECT 
        d.DriverID,
        u.FullName,
        u.MatricNo,
        u.Email,
        u.PhoneNumber,
        u.Gender,
        u.Faculty,
        d.LicenseNumber,
        d.CarModel,
        d.CarPlateNumber,
        d.BankName,
        d.AccountNumber,
        d.AccountName,
        d.Status,
        d.RegistrationDate,
        d.ApprovedDate,
        d.RejectionReason
        FROM driver d 
        JOIN user u ON d.UserID = u.UserID 
        ORDER BY d.RegistrationDate DESC";

    $result = $conn->query($sql);

    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="drivers_export_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');

        // Headers
        fputcsv($output, [
            'Driver ID',
            'Full Name',
            'Matric No',
            'Email',
            'Phone Number',
            'Gender',
            'Faculty',
            'License Number',
            'Car Model',
            'Car Plate',
            'Bank Name',
            'Account Number',
            'Account Name',
            'Status',
            'Registration Date',
            'Approved Date',
            'Rejection Reason'
        ]);

        // Data
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, $row);
        }

        fclose($output);
    } elseif ($format === 'json') {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="drivers_export_' . date('Y-m-d') . '.json"');

        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }

        echo json_encode($data, JSON_PRETTY_PRINT);
    }

    exit();
}
