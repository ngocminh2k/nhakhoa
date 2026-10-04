<?php
// backend/core/Response.php

class Response {
    public static function json(array $data, int $statusCode = 200, array $headers = []): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Allow CORS if needed by public site
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

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
