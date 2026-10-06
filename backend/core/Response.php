<?php
// backend/core/Response.php

class Response {
    public static function json(array $data, int $statusCode = 200, array $headers = []): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // CORS — whitelist only known origins
        $allowed = ['https://nhakhoakimdung.com', 'https://www.nhakhoakimdung.com'];
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if (in_array($origin, $allowed, true)) {
            header("Access-Control-Allow-Origin: $origin");
            header('Vary: Origin');
        } elseif (getenv('APP_ENV') === 'development' && preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#', $origin)) {
            // Allow local origin only in dev
            header("Access-Control-Allow-Origin: $origin");
            header('Vary: Origin');
        }
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

        foreach ($headers as $key => $val) {
            header("$key: $val");
        }

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(array $data = [], int $statusCode = 200): void {
        self::json(array_merge(['success' => true], $data), $statusCode);
    }

    public static function error(string $code, string $message, int $statusCode = 400, array $extra = []): void {
        self::json(array_merge([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ]
        ], $extra), $statusCode);
    }
}
