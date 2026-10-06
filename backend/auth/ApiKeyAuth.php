<?php
// backend/auth/ApiKeyAuth.php

require_once __DIR__ . '/../core/Database.php';

class ApiKeyAuth {
    public static function extractBearerToken(): ?string {
        $headers = [];
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } else {
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) === 'HTTP_') {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
        }

        // Check Authorization header case-insensitively
        $authHeader = '';
        foreach ($headers as $key => $val) {
            if (strcasecmp($key, 'Authorization') === 0) {
                $authHeader = trim($val);
                break;
            }
        }

        if (!$authHeader) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        }

        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return trim($matches[1]);
        }

        // Also fallback to X-API-Key header if provided
        foreach ($headers as $key => $val) {
            if (strcasecmp($key, 'X-API-Key') === 0) {
                return trim($val);
            }
        }
        if (!empty($_SERVER['HTTP_X_API_KEY'])) {
            return trim($_SERVER['HTTP_X_API_KEY']);
        }

        return null;
    }

    public static function validate(): ?array {
        $token = self::extractBearerToken();
        if (!$token) {
            return null;
        }

        $hash = hash('sha256', $token);
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id, name, active FROM api_keys WHERE key_hash = :hash AND active = 1 LIMIT 1');
        $stmt->execute([':hash' => $hash]);
        $apiKey = $stmt->fetch();

        if ($apiKey) {
            // Update last_used_at timestamp
            $upd = $pdo->prepare('UPDATE api_keys SET last_used_at = NOW() WHERE id = :id');
            $upd->execute([':id' => $apiKey['id']]);
            return $apiKey;
        }

        // Also check if matches AUTOMATION_API_KEY from .env for quick development
        $envKey = getenv('AUTOMATION_API_KEY');
        if ($envKey && hash_equals($envKey, $token)) {
            return ['id' => 0, 'name' => 'Environment Secret Bot', 'active' => 1];
        }

        return null;
    }
}
