<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

try {
    require_auth();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $q = trim((string)($_GET['q'] ?? ''));

        $sql = "SELECT employee_id, employee_name FROM employees";
        $types = '';
        $params = [];
        if ($q !== '') {
            $sql .= " WHERE employee_id LIKE ? OR employee_name LIKE ?";
            $search = '%' . $q . '%';
            $params[] = $search;
            $params[] = $search;
            $types .= 'ss';
        }
        $sql .= " ORDER BY employee_name ASC";

        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            json_response(['ok' => false, 'error' => 'Failed to prepare employee query.'], 500);
        }

        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $employees = [];
        while ($row = $result->fetch_assoc()) {
            $employees[] = [
                'employeeId' => (string)$row['employee_id'],
                'employeeName' => (string)$row['employee_name'],
            ];
        }

        json_response(['ok' => true, 'data' => $employees]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = read_json_body();
        $employeeId = trim((string)($body['employeeId'] ?? ''));
        $employeeName = trim((string)($body['employeeName'] ?? ''));

        if ($employeeId === '' || $employeeName === '') {
            json_response(['ok' => false, 'error' => 'employeeId and employeeName are required.'], 422);
        }

        $stmt = $mysqli->prepare("INSERT INTO employees (employee_id, employee_name) VALUES (?, ?)");
        if (!$stmt) {
            json_response(['ok' => false, 'error' => 'Failed to prepare insert query.'], 500);
        }
        $stmt->bind_param('ss', $employeeId, $employeeName);
        $stmt->execute();

        json_response([
            'ok' => true,
            'data' => [
                'employeeId' => $employeeId,
                'employeeName' => $employeeName,
            ],
        ], 201);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $employeeId = trim((string)($_GET['employeeId'] ?? ''));
        if ($employeeId === '') {
            json_response(['ok' => false, 'error' => 'employeeId is required.'], 422);
        }

        $stmt = $mysqli->prepare("DELETE FROM employees WHERE employee_id = ?");
        if (!$stmt) {
            json_response(['ok' => false, 'error' => 'Failed to prepare delete query.'], 500);
        }
        $stmt->bind_param('s', $employeeId);
        $stmt->execute();

        json_response(['ok' => true]);
    }

    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
} catch (mysqli_sql_exception $e) {
    $message = $e->getCode() === 1062
        ? 'Employee ID already exists.'
        : 'Database operation failed.';
    json_response(['ok' => false, 'error' => $message], 400);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'Unexpected API error.'], 500);
}

