<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = current_user();
if (!$user) {
    json_response(['ok' => true, 'data' => null]);
}

json_response(['ok' => true, 'data' => $user]);

