<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

try {
    require_auth();

    $employee = trim((string)($_GET['employee'] ?? ''));
    $from = trim((string)($_GET['from'] ?? ''));
    $to = trim((string)($_GET['to'] ?? ''));
    $limit = (int)($_GET['limit'] ?? 200);
    if ($limit <= 0) {
        $limit = 200;
    }
    if ($limit > 1000) {
        $limit = 1000;
    }

    $sql = "SELECT b.employee_id, COALESCE(e.employee_name, '') AS employee_name, b.log_date, b.time_in, b.time_out
            FROM biometrics_logs b
            LEFT JOIN employees e ON e.employee_id = b.employee_id
            WHERE 1=1";

    $params = [];
    $types = '';

    if ($employee !== '') {
        $sql .= " AND (b.employee_id LIKE ? OR e.employee_name LIKE ?)";
        $search = '%' . $employee . '%';
        $params[] = $search;
        $params[] = $search;
        $types .= 'ss';
    }

    if ($from !== '') {
        $sql .= " AND b.log_date >= ?";
        $params[] = $from;
        $types .= 's';
    }

    if ($to !== '') {
        $sql .= " AND b.log_date <= ?";
        $params[] = $to;
        $types .= 's';
    }

    $sql .= " ORDER BY b.log_date DESC, b.time_in DESC LIMIT ?";
    $params[] = $limit;
    $types .= 'i';

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        json_response(['ok' => false, 'error' => 'Failed to prepare attendance query.'], 500);
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $timeInTs = strtotime((string)$row['time_in']);
        $timeOutTs = strtotime((string)$row['time_out']);
        $minutes = 0;
        if ($timeInTs !== false && $timeOutTs !== false && $timeOutTs > $timeInTs) {
            $minutes = (int)round(($timeOutTs - $timeInTs) / 60) - 60;
            if ($minutes < 0) {
                $minutes = 0;
            }
        }

        $status = 'Present';
        if ($minutes < 240) {
            $status = 'Half Day';
        } elseif ($timeInTs !== false && (int)date('H', $timeInTs) >= 9) {
            $status = 'Late';
        }

        $logs[] = [
            'employeeId' => (string)$row['employee_id'],
            'employeeName' => (string)$row['employee_name'],
            'logDate' => (string)$row['log_date'],
            'day' => date('l', strtotime((string)$row['log_date'])),
            'timeIn' => (string)$row['time_in'],
            'timeOut' => (string)$row['time_out'],
            'status' => $status,
            'renderedMinutes' => $minutes,
        ];
    }

    json_response(['ok' => true, 'data' => $logs]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'Failed to fetch attendance logs.'], 500);
}

