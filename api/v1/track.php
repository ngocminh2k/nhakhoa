<?php
// api/v1/track.php

require_once __DIR__ . '/../../backend/core/Response.php';
require_once __DIR__ . '/../../backend/services/AnalyticsService.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    Response::json([], 204);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('METHOD_NOT_ALLOWED', 'Chỉ chấp nhận phương thức POST.', 405);
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

// Generate or retrieve anonymous session cookie
$cookieName = 'kd_sid';
$sessionId = $payload['session_id'] ?? ($_COOKIE[$cookieName] ?? '');
if (!$sessionId || strlen($sessionId) < 16) {
    $sessionId = bin2hex(random_bytes(16));
}

// Set cookie (1 year duration, SameSite Lax, HttpOnly false so frontend JS can read it for booking payload)
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

setcookie($cookieName, $sessionId, [
    'expires'  => time() + 31536000,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $isHttps,
    'httponly' => false,
    'samesite' => 'Lax',
]);

$payload['session_id'] = $sessionId;

try {
    $analytics = new AnalyticsService();
    $analytics->track($payload);

    Response::success([
        'session_id' => $sessionId,
        'tracked'    => true,
    ]);
} catch (Throwable $e) {
    // Non-critical endpoint, fail silently or return 200 with debug info
    Response::success([
        'session_id' => $sessionId,
        'tracked'    => false,
        'warning'    => $e->getMessage(),
    ]);
}
