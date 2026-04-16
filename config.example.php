<?php
// Copy this file as config.php only if you do not want .env.
// Recommended: keep config.php as-is and use .env instead.

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'biometrics');
define('PORT', 3306);

define('ADMIN_EMAIL', '');
define('ADMIN_PASSWORD', '');

define('MS_TENANT_ID', 'common');
define('MS_CLIENT_ID', '');
define('MS_CLIENT_SECRET', '');
define('MS_REDIRECT_URI', 'http://localhost/biometrics/login');

define('MS_ALLOWED_DOMAINS', '');
define('MS_ADMIN_DOMAINS', '');
?>
