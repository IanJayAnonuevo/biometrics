<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

try {
    require_auth();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
    }

    if (!isset($_FILES['datafile'])) {
        json_response(['ok' => false, 'error' => 'No files uploaded.'], 422);
    }

    $fileNames = $_FILES['datafile']['name'] ?? [];
    $tmpNames = $_FILES['datafile']['tmp_name'] ?? [];
    $errors = $_FILES['datafile']['error'] ?? [];

    $totalInserted = 0;
    $totalSkipped = 0;
    $processedFiles = 0;
    $errorFiles = [];

    $checkStmt = $mysqli->prepare("SELECT COUNT(*) AS c FROM biometrics_logs WHERE employee_id = ? AND log_date = ?");
    $insertStmt = $mysqli->prepare("INSERT INTO biometrics_logs (employee_id, log_date, time_in, time_out) VALUES (?, ?, ?, ?)");
    if (!$checkStmt || !$insertStmt) {
        json_response(['ok' => false, 'error' => 'Failed to prepare import queries.'], 500);
    }

    for ($i = 0; $i < count($tmpNames); $i++) {
        $name = (string)($fileNames[$i] ?? 'file');
        $tmp = (string)($tmpNames[$i] ?? '');
        $fileErr = (int)($errors[$i] ?? UPLOAD_ERR_NO_FILE);

        if ($fileErr !== UPLOAD_ERR_OK || $tmp === '' || !is_uploaded_file($tmp)) {
            $errorFiles[] = "{$name} (upload error)";
            continue;
        }

        $processedFiles++;
        $handle = fopen($tmp, 'rb');
        if ($handle === false) {
            $errorFiles[] = "{$name} (unable to open file)";
            continue;
        }

        $logs = [];
        $hasData = false;

        while (($line = fgets($handle)) !== false) {
            $parts = explode("\t", trim($line));
            if (count($parts) < 2) {
                continue;
            }

            $employeeId = trim((string)$parts[0]);
            $datetimeRaw = trim((string)$parts[1]);
            $timestamp = strtotime($datetimeRaw);
            if ($employeeId === '' || $timestamp === false) {
                continue;
            }

            $hasData = true;
            $date = date('Y-m-d', $timestamp);
            $time = date('H:i:s', $timestamp);
            $key = $employeeId . '_' . $date;

            if (!isset($logs[$key])) {
                $logs[$key] = ['in' => null, 'out' => null];
            }

            if ($logs[$key]['in'] === null || $time < $logs[$key]['in']) {
                $logs[$key]['in'] = $time;
            }
            if ($logs[$key]['out'] === null || $time > $logs[$key]['out']) {
                $logs[$key]['out'] = $time;
            }
        }
        fclose($handle);

        if (!$hasData) {
            $errorFiles[] = "{$name} (no valid data found)";
            continue;
        }

        foreach ($logs as $logKey => $log) {
            [$employeeId, $date] = explode('_', $logKey, 2);
            $timeIn = (string)($log['in'] ?? '');
            $timeOut = (string)($log['out'] ?? '');
            if ($employeeId === '' || $date === '' || $timeIn === '' || $timeOut === '') {
                continue;
            }

            $checkStmt->bind_param('ss', $employeeId, $date);
            $checkStmt->execute();
            $res = $checkStmt->get_result();
            $exists = $res ? ((int)($res->fetch_assoc()['c'] ?? 0) > 0) : false;

            if ($exists) {
                $totalSkipped++;
                continue;
            }

            $insertStmt->bind_param('ssss', $employeeId, $date, $timeIn, $timeOut);
            $insertStmt->execute();
            $totalInserted++;
        }
    }

    json_response([
        'ok' => true,
        'data' => [
            'processedFiles' => $processedFiles,
            'inserted' => $totalInserted,
            'skipped' => $totalSkipped,
            'errors' => $errorFiles,
        ],
    ]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'Failed to import attendance files.'], 500);
}

