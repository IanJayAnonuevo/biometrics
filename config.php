<?php
// config.php

/**
 * Load .env values into $_ENV / $_SERVER if present.
 * This keeps secrets out of git-tracked files.
 */
function load_env_file(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);
        if ($key === '') {
            continue;
        }

        // Remove surrounding single/double quotes, if any.
        if (
            strlen($value) >= 2 &&
            (($value[0] === '"' && $value[strlen($value) - 1] === '"') ||
            ($value[0] === "'" && $value[strlen($value) - 1] === "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv($key . '=' . $value);
    }
}

function env_value(string $key, string $default = ''): string
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    return ($value === false || $value === null || $value === '') ? $default : (string)$value;
}

load_env_file(__DIR__ . '/.env');

define('DB_HOST', env_value('DB_HOST', 'localhost'));
define('DB_USER', env_value('DB_USER', 'root'));
define('DB_PASS', env_value('DB_PASS', ''));
define('DB_NAME', env_value('DB_NAME', 'biometrics'));
define('PORT', (int)env_value('PORT', '3306'));

// Default admin login for the web UI/API.
define('ADMIN_EMAIL', env_value('ADMIN_EMAIL', ''));
define('ADMIN_PASSWORD', env_value('ADMIN_PASSWORD', ''));

// Microsoft OAuth (Azure App Registration).
// Keep empty to disable Microsoft login.
define('MS_TENANT_ID', env_value('MS_TENANT_ID', 'common'));
define('MS_CLIENT_ID', env_value('MS_CLIENT_ID', ''));
define('MS_CLIENT_SECRET', env_value('MS_CLIENT_SECRET', ''));
define('MS_REDIRECT_URI', env_value('MS_REDIRECT_URI', 'http://localhost/biometrics/login'));

// Comma-separated corporate domains allowed for Microsoft auto-login.
define('MS_ALLOWED_DOMAINS', env_value('MS_ALLOWED_DOMAINS', ''));

// Domains that should auto-provision as admin role.
define('MS_ADMIN_DOMAINS', env_value('MS_ADMIN_DOMAINS', ''));
?>
