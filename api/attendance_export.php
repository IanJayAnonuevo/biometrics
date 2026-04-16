<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_auth_or_401(): void
{
    if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
        http_response_code(401);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Unauthorized. Please login.";
        exit;
    }
}

function csv_cell(string $value): string
{
    // We output via fputcsv below, so this is mostly for ensuring strings.
    return $value;
}

require_auth_or_401();

$filter = strtolower(trim((string)($_GET['filter'] ?? 'all')));
$employee = trim((string)($_GET['employee'] ?? ''));
$month = trim((string)($_GET['month'] ?? '')); // YYYY-MM
$from = trim((string)($_GET['from'] ?? ''));   // YYYY-MM-DD
$to = trim((string)($_GET['to'] ?? ''));       // YYYY-MM-DD

$timestamp = date('Ymd_His');
$filename = "Attendance_Report_{$timestamp}.csv";

$sql = "SELECT b.employee_id, COALESCE(e.employee_name, '') AS employee_name, b.log_date, b.time_in, b.time_out
        FROM biometrics_logs b
        LEFT JOIN employees e ON e.employee_id = b.employee_id
        WHERE 1=1";

$params = [];
$types = '';

if ($filter === 'employee') {
    if ($employee === '') {
        http_response_code(422);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Employee is required.";
        exit;
    }

    $sql .= " AND (b.employee_id LIKE ? OR e.employee_name LIKE ?)";
    $search = '%' . $employee . '%';
    $params[] = $search;
    $params[] = $search;
    $types .= 'ss';
    $safe = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $employee);
    $filename = "Attendance_Report_Employee_{$safe}_{$timestamp}.csv";
} elseif ($filter === 'monthly') {
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        http_response_code(422);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Month must be YYYY-MM.";
        exit;
    }
    $start = $month . '-01';
    $end = date('Y-m-t', strtotime($start));

    $sql .= " AND b.log_date BETWEEN ? AND ?";
    $params[] = $start;
    $params[] = $end;
    $types .= 'ss';
    $filename = "Monthly_Attendance_Report_{$month}_{$timestamp}.csv";
} elseif ($filter === 'range') {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        http_response_code(422);
        header('Content-Type: text/plain; charset=utf-8');
        echo "From/To must be YYYY-MM-DD.";
        exit;
    }
    $sql .= " AND b.log_date BETWEEN ? AND ?";
    $params[] = $from;
    $params[] = $to;
    $types .= 'ss';
    $filename = "Attendance_Report_{$from}_to_{$to}_{$timestamp}.csv";
} else {
    // all
    $filter = 'all';
    $filename = "Complete_Attendance_Report_{$timestamp}.csv";
}

$sql .= " ORDER BY b.employee_id ASC, b.log_date ASC, b.time_in ASC";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Failed to prepare export query.";
    exit;
}

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Stream CSV to browser (Excel-friendly)
if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);
header('Cache-Control: max-age=0');

$out = fopen('php://output', 'w');
if ($out === false) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Failed to open output stream.";
    exit;
}

// UTF-8 BOM for Excel
fwrite($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($out, ['Employee ID', 'Employee Name', 'Date', 'Day', 'Time In', 'Time Out', 'Status', 'Rendered Hours']);

while ($row = $result->fetch_assoc()) {
    $timeIn = (string)($row['time_in'] ?? '');
    $timeOut = (string)($row['time_out'] ?? '');
    $timeInTs = strtotime($timeIn);
    $timeOutTs = strtotime($timeOut);

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

    $hours = (int)floor($minutes / 60);
    $mins = $minutes % 60;
    $rendered = sprintf('%dh %dm', $hours, $mins);

    $logDate = (string)($row['log_date'] ?? '');
    $day = $logDate !== '' ? date('l', strtotime($logDate)) : '';

    fputcsv($out, [
        csv_cell(sprintf('%04d', (int)$row['employee_id'])),
        csv_cell((string)$row['employee_name']),
        csv_cell($logDate),
        csv_cell($day),
        csv_cell($timeInTs !== false ? date('h:i A', $timeInTs) : $timeIn),
        csv_cell($timeOutTs !== false ? date('h:i A', $timeOutTs) : $timeOut),
        csv_cell($status),
        csv_cell($rendered),
    ]);
}

fclose($out);
exit;

