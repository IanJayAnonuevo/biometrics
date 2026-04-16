<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
    }

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();

    json_response(['ok' => true]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'Logout failed.'], 500);
}

