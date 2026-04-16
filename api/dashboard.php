<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

try {
    require_auth();

    $today = date('Y-m-d');

    $stats = [
        'totalEmployees' => 0,
        'presentToday' => 0,
        'lateToday' => 0,
        'halfDayToday' => 0,
    ];

    $totalRes = $mysqli->query("SELECT COUNT(*) AS c FROM employees");
    if ($totalRes) {
        $stats['totalEmployees'] = (int)$totalRes->fetch_assoc()['c'];
    }

    $attendanceStmt = $mysqli->prepare("
        SELECT b.employee_id, COALESCE(e.employee_name, '') AS employee_name, b.time_in, b.time_out
        FROM biometrics_logs b
        LEFT JOIN employees e ON e.employee_id = b.employee_id
        WHERE b.log_date = ?
        ORDER BY b.time_in ASC
    ");
    if (!$attendanceStmt) {
        json_response(['ok' => false, 'error' => 'Failed to prepare dashboard query.'], 500);
    }
    $attendanceStmt->bind_param('s', $today);
    $attendanceStmt->execute();
    $attendanceRes = $attendanceStmt->get_result();

    $todaysAttendance = [];
    $lateEmployees = [];

    while ($row = $attendanceRes->fetch_assoc()) {
        $stats['presentToday']++;

        $timeInTs = strtotime((string)$row['time_in']);
        $timeOutTs = strtotime((string)$row['time_out']);

        $status = 'Present';
        if ($timeInTs !== false && (int)date('H', $timeInTs) >= 9) {
            $status = 'Late';
            $stats['lateToday']++;
            $lateEmployees[] = (string)$row['employee_name'];
        }

        if ($timeInTs !== false && $timeOutTs !== false && $timeOutTs > $timeInTs) {
            $minutes = (int)round(($timeOutTs - $timeInTs) / 60) - 60;
            if ($minutes < 240) {
                $status = 'Half Day';
                $stats['halfDayToday']++;
            }
        }

        $todaysAttendance[] = [
            'employeeId' => (string)$row['employee_id'],
            'employeeName' => (string)$row['employee_name'],
            'timeIn' => (string)$row['time_in'],
            'status' => $status,
        ];
    }

    json_response([
        'ok' => true,
        'data' => [
            'stats' => $stats,
            'todaysAttendance' => $todaysAttendance,
            'lateEmployees' => array_values(array_unique(array_filter($lateEmployees))),
        ],
    ]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'Failed to fetch dashboard data.'], 500);
}

