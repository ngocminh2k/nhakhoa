<?php
// admin/login.php

require_once __DIR__ . '/../backend/auth/AdminAuth.php';
require_once __DIR__ . '/../backend/security/RateLimiter.php';
require_once __DIR__ . '/../backend/security/Csrf.php';

AdminAuth::initSession();

if (AdminAuth::check()) {
    header('Location: /admin/index.php');
    exit;
}

$error = '';
$emailVal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Rate Limiting: 5 attempts per 15 min per IP
    if (!RateLimiter::check('admin_login', 5, 900)) {
        $error = 'Bạn đã thử đăng nhập thất bại quá nhiều lần. Vui lòng đợi 15 phút.';
    } else {
        $token = $_POST['csrf_token'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $emailVal = htmlspecialchars($email, ENT_QUOTES);

        if (!Csrf::verifyToken($token)) {
            $error = 'Phiên bảo mật đã hết hạn. Vui lòng tải lại trang.';
        } elseif (empty($email) || empty($password)) {
            $error = 'Vui lòng nhập đầy đủ Email và Mật khẩu.';
        } else {
            try {
                if (AdminAuth::attempt($email, $password)) {
                    header('Location: /admin/index.php');
                    exit;
                } else {
                    $error = 'Email hoặc mật khẩu không chính xác.';
                }
            } catch (Throwable $e) {
                $error = (getenv('APP_ENV') === 'development') ? ('Lỗi hệ thống: ' . $e->getMessage()) : 'Lỗi hệ thống. Vui lòng thử lại sau.';
            }
        }
    }
}

$csrfToken = Csrf::generateToken();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập Quản Trị — Nha Khoa Kim Dung</title>
    <link rel="icon" type="image/x-icon" href="https://nhakhoakimdung.vn/upload/photo/url-1-1753346947.webp">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-gradient: linear-gradient(0deg, rgba(234, 191, 14, 0.13) 0%, rgba(255, 253, 246, 1) 100%);
            --surface: #ffffff;
            --border: rgba(234, 191, 14, 0.35);
            --text-main: #18181b;
            --text-muted: #64748b;
            --primary: #005A36;
            --primary-hover: #004529;
            --gold: #EABF0E;
            --gold-hover: #D4AC0B;
            --danger: #dc2626;
            --danger-light: #fef2f2;
            --font: 'Be Vietnam Pro', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: var(--font);
            background: var(--bg-gradient);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .login-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            box-shadow: 0 10px 30px -5px rgba(0, 90, 54, 0.08), 0 4px 12px rgba(234, 191, 14, 0.12);
            width: 100%;
            max-width: 420px;
            padding: 40px 32px;
            position: relative;
        }
        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 40px;
            right: 40px;
            height: 4px;
            background: linear-gradient(90deg, #005A36, #EABF0E, #005A36);
            border-radius: 4px;
        }
        .login-brand {
            text-align: center;
            margin-bottom: 8px;
        }
        .login-brand img {
            max-height: 52px;
            width: auto;
            margin-bottom: 12px;
        }
        .login-brand h1 {
            font-size: 20px;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: -0.01em;
        }
        .login-subtitle {
            font-size: 13.5px;
            color: var(--text-muted);
            text-align: center;
            margin-bottom: 24px;
        }
        .alert-error {
            background: var(--danger-light);
            color: var(--danger);
            border: 1px solid #fecaca;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 18px;
        }
        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--text-main);
        }
        input {
            width: 100%;
            padding: 11px 14px;
            font-size: 14px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #fff;
            color: var(--text-main);
            font-family: inherit;
            transition: all 0.15s;
        }
        input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 90, 54, 0.15);
        }
        .btn-submit {
            width: 100%;
            padding: 12px;
            font-size: 14.5px;
            font-weight: 700;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.15s, transform 0.1s;
            margin-top: 8px;
            font-family: inherit;
        }
        .btn-submit:hover {
            background: var(--primary-hover);
        }
        .btn-submit:active {
            transform: scale(0.99);
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 24px;
            font-size: 13px;
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 500;
        }
        .back-link:hover {
            color: var(--primary);
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="login-card">
    <div class="login-brand">
        <img src="https://nhakhoakimdung.vn/upload/photo/url-1-1753346899.webp" alt="Nha Khoa Kim Dung">
        <h1>NHA KHOA KIM DUNG</h1>
    </div>
    <div class="login-subtitle">Cổng Quản Trị Hệ Thống &amp; Đặt Lịch Khám</div>

    <?php if ($error): ?>
        <div class="alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/admin/login.php" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <div class="form-group">
            <label for="email">Email Quản trị viên</label>
            <input type="email" id="email" name="email" required autofocus value="<?= $emailVal ?>" placeholder="admin@nhakhoakimdung.com">
        </div>
        <div class="form-group">
            <label for="password">Mật khẩu</label>
            <input type="password" id="password" name="password" required placeholder="••••••••">
        </div>
        <button type="submit" class="btn-submit">Đăng nhập Quản Trị</button>
    </form>
    <a href="/website/index.html" class="back-link">← Quay lại trang chủ website</a>
</div>
</body>
</html>
