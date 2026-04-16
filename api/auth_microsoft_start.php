<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (
    !defined('MS_CLIENT_ID') || MS_CLIENT_ID === '' ||
    !defined('MS_CLIENT_SECRET') || MS_CLIENT_SECRET === '' ||
    !defined('MS_REDIRECT_URI') || MS_REDIRECT_URI === ''
) {
    header('Location: /biometrics/login?ms_error=not_configured');
    exit;
}

$tenant = defined('MS_TENANT_ID') && MS_TENANT_ID !== '' ? MS_TENANT_ID : 'common';
$state = bin2hex(random_bytes(16));
$_SESSION['ms_oauth_state'] = $state;

$params = [
    'client_id' => MS_CLIENT_ID,
    'response_type' => 'code',
    'redirect_uri' => MS_REDIRECT_URI,
    'response_mode' => 'query',
    'scope' => 'openid profile email User.Read',
    'state' => $state,
    'prompt' => 'select_account',
];

$authorizeUrl = "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/authorize?" . http_build_query($params);
header("Location: {$authorizeUrl}");
exit;

