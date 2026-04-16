<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function parse_domains(string $csv): array
{
    if (trim($csv) === '') {
        return [];
    }
    $parts = array_map('trim', explode(',', strtolower($csv)));
    return array_values(array_filter($parts, static fn($d) => $d !== ''));
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
    }

    $body = read_json_body();
    $email = strtolower(trim((string)($body['email'] ?? '')));
    $password = (string)($body['password'] ?? '');

    if ($email === '' || $password === '') {
        json_response(['ok' => false, 'error' => 'Email and password are required.'], 422);
    }

    $emailParts = explode('@', $email);
    $emailDomain = strtolower(trim((string)end($emailParts)));
    if ($emailDomain === '' || $emailDomain === 'localhost') {
        json_response(['ok' => false, 'error' => 'Please use your company email address.'], 403);
    }

    $allowedDomains = parse_domains(defined('MS_ALLOWED_DOMAINS') ? (string)MS_ALLOWED_DOMAINS : '');
    if (!empty($allowedDomains) && !in_array($emailDomain, $allowedDomains, true)) {
        json_response(['ok' => false, 'error' => 'This email domain is not allowed in this system.'], 403);
    }

    $stmt = $mysqli->prepare("SELECT id, email, password_hash, role FROM users WHERE email = ? LIMIT 1");
    if (!$stmt) {
        json_response(['ok' => false, 'error' => 'Failed to prepare login query.'], 500);
    }
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;

    if (!$row || !password_verify($password, (string)$row['password_hash'])) {
        json_response(['ok' => false, 'error' => 'Invalid credentials.'], 401);
    }

    $_SESSION['user'] = [
        'id' => (int)$row['id'],
        'email' => (string)$row['email'],
        'role' => (string)$row['role'],
    ];

    json_response(['ok' => true, 'data' => $_SESSION['user']]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'Login failed.'], 500);
}

