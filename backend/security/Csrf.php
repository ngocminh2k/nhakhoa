<?php
// backend/security/Csrf.php

class Csrf {
    private const SESSION_KEY = 'kd_csrf_token';

    public static function generateToken(): string {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::SESSION_KEY];
    }

    public static function verifyToken(?string $token): bool {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $sessionToken = $_SESSION[self::SESSION_KEY] ?? '';
        if (!$sessionToken || !$token) {
            return false;
        }
        return hash_equals($sessionToken, $token);
    }
}
