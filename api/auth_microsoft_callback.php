<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function redirect_to_login(string $errorCode): void
{
    header('Location: /biometrics/login?ms_error=' . urlencode($errorCode));
    exit;
}

function parse_domains(string $csv): array
{
    if (trim($csv) === '') {
        return [];
    }
    $parts = array_map('trim', explode(',', strtolower($csv)));
    return array_values(array_filter($parts, static fn($d) => $d !== ''));
}

if (isset($_GET['error'])) {
    redirect_to_login('microsoft_denied');
}

$state = (string)($_GET['state'] ?? '');
if ($state === '' || !isset($_SESSION['ms_oauth_state']) || $state !== $_SESSION['ms_oauth_state']) {
    redirect_to_login('invalid_state');
}
unset($_SESSION['ms_oauth_state']);

$code = (string)($_GET['code'] ?? '');
if ($code === '') {
    redirect_to_login('missing_code');
}

if (
    !defined('MS_CLIENT_ID') || MS_CLIENT_ID === '' ||
    !defined('MS_CLIENT_SECRET') || MS_CLIENT_SECRET === '' ||
    !defined('MS_REDIRECT_URI') || MS_REDIRECT_URI === ''
) {
    redirect_to_login('not_configured');
}

$tenant = defined('MS_TENANT_ID') && MS_TENANT_ID !== '' ? MS_TENANT_ID : 'common';
$tokenUrl = "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token";

$postFields = http_build_query([
    'client_id' => MS_CLIENT_ID,
    'client_secret' => MS_CLIENT_SECRET,
    'grant_type' => 'authorization_code',
    'code' => $code,
    'redirect_uri' => MS_REDIRECT_URI,
    'scope' => 'openid profile email User.Read',
]);

$ch = curl_init($tokenUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postFields,
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_TIMEOUT => 20,
]);
$tokenRaw = curl_exec($ch);
$tokenErr = curl_error($ch);
curl_close($ch);

if ($tokenRaw === false || $tokenErr !== '') {
    redirect_to_login('token_request_failed');
}

$tokenPayload = json_decode($tokenRaw, true);
$accessToken = is_array($tokenPayload) ? (string)($tokenPayload['access_token'] ?? '') : '';
if ($accessToken === '') {
    redirect_to_login('token_missing');
}

$userCh = curl_init('https://graph.microsoft.com/v1.0/me?$select=id,mail,userPrincipalName,displayName');
curl_setopt_array($userCh, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $accessToken,
        'Accept: application/json',
    ],
    CURLOPT_TIMEOUT => 20,
]);
$userRaw = curl_exec($userCh);
$userErr = curl_error($userCh);
curl_close($userCh);

if ($userRaw === false || $userErr !== '') {
    redirect_to_login('user_request_failed');
}

$me = json_decode($userRaw, true);
if (!is_array($me)) {
    redirect_to_login('invalid_user_payload');
}

$microsoftOid = trim((string)($me['id'] ?? ''));
$email = strtolower(trim((string)($me['mail'] ?? $me['userPrincipalName'] ?? '')));
$displayName = trim((string)($me['displayName'] ?? ''));

if ($microsoftOid === '' || $email === '') {
    redirect_to_login('email_or_oid_missing');
}

$emailParts = explode('@', $email);
$emailDomain = strtolower(trim((string)end($emailParts)));
if ($emailDomain === '' || $emailDomain === 'localhost') {
    redirect_to_login('invalid_email_domain');
}

$allowedDomains = parse_domains(defined('MS_ALLOWED_DOMAINS') ? (string)MS_ALLOWED_DOMAINS : '');
if (!empty($allowedDomains) && !in_array($emailDomain, $allowedDomains, true)) {
    redirect_to_login('unauthorized_domain');
}

$adminDomains = parse_domains(defined('MS_ADMIN_DOMAINS') ? (string)MS_ADMIN_DOMAINS : '');

$stmt = $mysqli->prepare("SELECT id, email, role FROM users WHERE microsoft_oid = ? OR email = ? LIMIT 1");
if (!$stmt) {
    redirect_to_login('db_prepare_failed');
}
$stmt->bind_param('ss', $microsoftOid, $email);
$stmt->execute();
$res = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;

if ($user) {
    $id = (int)$user['id'];
    $role = (string)$user['role'];
    $update = $mysqli->prepare("UPDATE users SET email = ?, microsoft_oid = ?, display_name = ?, auth_provider = 'microsoft' WHERE id = ?");
    if ($update) {
        $update->bind_param('sssi', $email, $microsoftOid, $displayName, $id);
        $update->execute();
    }
} else {
    $passwordHash = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
    $role = in_array($emailDomain, $adminDomains, true) ? 'admin' : 'employee';
    $insert = $mysqli->prepare("
        INSERT INTO users (email, password_hash, role, auth_provider, microsoft_oid, display_name)
        VALUES (?, ?, ?, 'microsoft', ?, ?)
    ");
    if (!$insert) {
        redirect_to_login('db_insert_failed');
    }
    $insert->bind_param('sssss', $email, $passwordHash, $role, $microsoftOid, $displayName);
    $insert->execute();
    $id = (int)$insert->insert_id;
}

$_SESSION['user'] = [
    'id' => $id,
    'email' => $email,
    'role' => $role,
];

$target = $role === 'employee' ? '/biometrics/employee/dashboard' : '/biometrics/admin/dashboard';
header("Location: {$target}");
exit;

