<?php
// backend/auth/AdminAuth.php

require_once __DIR__ . '/../core/Database.php';

class AdminAuth {
    public static function initSession(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        session_set_cookie_params([
            'lifetime' => 86400 * 7, // 7 days
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name('kd_admin_sess');
        session_start();
    }

    public static function check(): bool {
        self::initSession();
        return !empty($_SESSION['admin_logged_in']) && !empty($_SESSION['admin_id']);
    }

    public static function requireAuth(): void {
        if (!self::check()) {
            if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
                header('Content-Type: application/json', true, 401);
                echo json_encode(['success' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Vui lòng đăng nhập lại.']]);
                exit;
            }
            header('Location: /admin/login.php');
            exit;
        }
    }

    public static function user(): ?array {
        if (!self::check()) return null;
        return [
            'id'    => $_SESSION['admin_id'],
            'email' => $_SESSION['admin_email'],
            'name'  => $_SESSION['admin_name'] ?? 'Admin',
        ];
    }

    public static function attempt(string $email, string $password): bool {
        self::initSession();
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id, email, password_hash, name FROM admins WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_email'] = $admin['email'];
            $_SESSION['admin_name'] = $admin['name'];
            return true;
        }

        return false;
    }

    public static function logout(): void {
        self::initSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }
}
