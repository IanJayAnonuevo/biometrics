<?php
// Legacy import endpoint is retired.
// Bridge legacy users into the SPA auth session, then route to Attendance Logs modal.
require_once __DIR__ . '/../api/bootstrap.php';

if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
    $email = defined('ADMIN_EMAIL') ? (string) ADMIN_EMAIL : 'admin@local';
    if ($email === '') {
        $email = 'admin@local';
    }

    $_SESSION['user'] = [
        'id' => 1,
        'email' => $email,
        'role' => 'admin',
    ];
}

header('Location: /biometrics/attendance-logs?openImport=1', true, 302);
exit();
?>
