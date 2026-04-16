<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function ensure_users_table(mysqli $mysqli): void
{
    $mysqli->query("
        CREATE TABLE IF NOT EXISTS users (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(190) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('admin','employee') NOT NULL DEFAULT 'admin',
            auth_provider ENUM('local','microsoft') NOT NULL DEFAULT 'local',
            microsoft_oid VARCHAR(190) NULL UNIQUE,
            display_name VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

function ensure_users_columns(mysqli $mysqli): void
{
    $required = [
        "auth_provider ENUM('local','microsoft') NOT NULL DEFAULT 'local'",
        "microsoft_oid VARCHAR(190) NULL UNIQUE",
        "display_name VARCHAR(255) NULL",
    ];

    foreach ($required as $columnDef) {
        $columnName = strtok($columnDef, ' ');
        $check = $mysqli->query("SHOW COLUMNS FROM users LIKE '{$columnName}'");
        if ($check && $check->num_rows > 0) {
            continue;
        }
        $mysqli->query("ALTER TABLE users ADD COLUMN {$columnDef}");
    }
}

function ensure_default_admin(mysqli $mysqli): void
{
    if (!defined('ADMIN_EMAIL') || !defined('ADMIN_PASSWORD')) {
        return;
    }

    $email = (string)ADMIN_EMAIL;
    $password = (string)ADMIN_PASSWORD;
    if ($email === '' || $password === '') {
        return;
    }

    $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        return;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $role = 'admin';
    $insert = $mysqli->prepare("INSERT INTO users (email, password_hash, role) VALUES (?, ?, ?)");
    if (!$insert) {
        return;
    }
    $insert->bind_param('sss', $email, $hash, $role);
    $insert->execute();
}

function current_user(): ?array
{
    if (!isset($_SESSION) || !isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
        return null;
    }
    return $_SESSION['user'];
}

function require_auth(): array
{
    $user = current_user();
    if (!$user) {
        json_response(['ok' => false, 'error' => 'Unauthorized. Please login.'], 401);
    }
    return $user;
}

// Ensure auth storage exists and seed an admin on first run
ensure_users_table($mysqli);
ensure_users_columns($mysqli);
ensure_default_admin($mysqli);

