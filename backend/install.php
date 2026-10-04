<?php
// backend/install.php
// Trình cài đặt tự động cho Mắt Bão Cloud Hosting (Browser + CLI)

$isCli = (php_sapi_name() === 'cli');
$lockFile = __DIR__ . '/storage/installed.lock';

function out(string $msg, string $type = 'info'): void {
    global $isCli;
    if ($isCli) {
        $prefix = match($type) {
            'success' => '[OK] ',
            'error'   => '[ERROR] ',
            'warning' => '[WARN] ',
            default   => '[INFO] '
        };
        echo $prefix . $msg . PHP_EOL;
    } else {
        $color = match($type) {
            'success' => '#16a34a',
            'error'   => '#dc2626',
            'warning' => '#d97706',
            default   => '#2563eb'
        };
        echo "<div style='color: {$color}; margin: 6px 0; font-family: monospace;'><strong>" . htmlspecialchars($msg) . "</strong></div>";
    }
}

if (!$isCli) {
    echo '<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8"><title>Cài đặt Hệ thống — Nha Khoa Kim Dung</title></head><body style="font-family: system-ui, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; line-height: 1.6; background: #f8fafc; color: #0f172a;"><div style="background: #fff; padding: 30px; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px rgba(0,0,0,0.05);"><h1 style="font-size: 22px; margin-bottom: 20px;">🦷 Trình Cài Đặt Hệ Thống — Nha Khoa Kim Dung</h1>';
}

if (file_exists($lockFile)) {
    out('Hệ thống đã được cài đặt trước đó! Tệp khóa tồn tại: backend/storage/installed.lock', 'warning');
    out('Để cài đặt lại, vui lòng xóa tệp installed.lock trên máy chủ.', 'warning');
    if (!$isCli) {
        echo '<p style="margin-top: 20px;"><a href="/admin/login.php" style="background: #2563eb; color: #fff; padding: 10px 18px; border-radius: 6px; text-decoration: none; font-weight: 600;">Đi tới trang Quản Trị /admin</a></p></div></body></html>';
    }
    exit;
}

try {
    // 1. Kiểm tra môi trường & thư mục storage
    out('1. Kiểm tra cấu trúc thư mục lưu trữ...', 'info');
    $storageDir = __DIR__ . '/storage';
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0755, true);
    }
    $rateLimitDir = $storageDir . '/ratelimit';
    if (!is_dir($rateLimitDir)) {
        mkdir($rateLimitDir, 0755, true);
    }
    out('Thư mục storage và cache đã sẵn sàng.', 'success');

    // 2. Kết nối cơ sở dữ liệu MariaDB
    out('2. Kiểm tra kết nối MariaDB / MySQL...', 'info');
    require_once __DIR__ . '/core/Database.php';
    $pdo = Database::getConnection();
    out('Kết nối MariaDB thành công!', 'success');

    // 3. Khởi tạo Bảng Dữ Liệu (schema.sql)
    out('3. Khởi tạo cấu trúc bảng dữ liệu (schema.sql)...', 'info');
    $schemaPath = __DIR__ . '/../database/schema.sql';
    if (!file_exists($schemaPath)) {
        throw new RuntimeException("Không tìm thấy tệp {$schemaPath}");
    }
    $schemaSql = file_get_contents($schemaPath);
    $pdo->exec($schemaSql);
    out('Đã tạo thành công 10 bảng dữ liệu cốt lõi.', 'success');

    // 4. Nạp Dữ Liệu Mẫu (seed.sql)
    out('4. Nạp dữ liệu mặc định giờ khám và dịch vụ (seed.sql)...', 'info');
    $seedPath = __DIR__ . '/../database/seed.sql';
    if (file_exists($seedPath)) {
        $seedSql = file_get_contents($seedPath);
        $pdo->exec($seedSql);
        out('Nạp dữ liệu giờ khám và dịch vụ thành công.', 'success');
    }

    // 5. Tạo tài khoản Admin mặc định
    out('5. Kiểm tra tài khoản Quản trị viên (Admin)...', 'info');
    $adminEmail = 'admin@nhakhoakimdung.com';
    $defaultPassword = bin2hex(random_bytes(6)); // Tạo mật khẩu ngẫu nhiên an toàn nếu chưa có

    $checkAdmin = $pdo->prepare('SELECT id, email FROM admins WHERE email = :email LIMIT 1');
    $checkAdmin->execute([':email' => $adminEmail]);
    if (!$checkAdmin->fetch()) {
        $passHash = password_hash($defaultPassword, PASSWORD_BCRYPT);
        $insertAdmin = $pdo->prepare('
            INSERT INTO admins (email, password_hash, name, role)
            VALUES (:email, :hash, :name, "super_admin")
        ');
        $insertAdmin->execute([
            ':email' => $adminEmail,
            ':hash'  => $passHash,
            ':name'  => 'Quản Trị Viên',
        ]);
        out("Tài khoản admin mặc định được khởi tạo:", 'success');
        out("  - Email: {$adminEmail}", 'info');
        out("  - Mật khẩu tạm thời: {$defaultPassword}", 'warning');
        out("  (Vui lòng đăng nhập và đổi mật khẩu ngay sau khi hoàn tất cài đặt)", 'warning');
    } else {
        out("Tài khoản admin {$adminEmail} đã tồn tại sẵn.", 'info');
    }

    // 6. Tạo API Key cho dịch vụ đăng bài tự động
    out('6. Kiểm tra khóa API (API Key)...', 'info');
    $checkKey = $pdo->query("SELECT id, name FROM api_keys WHERE name = 'Auto Poster' LIMIT 1")->fetch();
    $rawApiKey = '';
    if (!$checkKey) {
        $rawApiKey = 'kd_' . bin2hex(random_bytes(24));
        $keyHash = hash('sha256', $rawApiKey);
        $insertKey = $pdo->prepare('
            INSERT INTO api_keys (name, key_hash, active)
            VALUES ("Auto Poster", :hash, 1)
        ');
        $insertKey->execute([':hash' => $keyHash]);
        out("Khởi tạo API Key thành công:", 'success');
        out("  - Tên: Auto Poster", 'info');
        out("  - Khóa (Chỉ hiển thị 1 lần): {$rawApiKey}", 'warning');
    } else {
        out("Khóa API đã tồn tại.", 'info');
    }

    // 7. Tạo file khóa cài đặt
    file_put_contents($lockFile, 'Installed on ' . date('c'));
    out('7. Tạo tệp khóa an ninh backend/storage/installed.lock.', 'success');

    out('====================================================', 'info');
    out('🎉 CÀI ĐẶT HỆ THỐNG THÀNH CÔNG VÀ SẴN SÀNG VẬN HÀNH!', 'success');

    if (!$isCli) {
        echo '<div style="margin-top: 24px; padding: 16px; background: #eff6ff; border-radius: 6px; border: 1px solid #bfdbfe;">';
        echo '<h3 style="margin-bottom: 8px; color: #1e40af;">Bước tiếp theo:</h3>';
        echo '<ul style="margin-left: 20px; font-size: 14px;">';
        echo '<li>Truy cập <a href="/admin/login.php" style="color: #2563eb; font-weight: 600;">Trang Quản Trị (/admin/login.php)</a>.</li>';
        echo '<li>Cấu hình tài khoản dịch vụ Google Sheets trong <code>backend/config/google_sheets.json</code> để kích hoạt đồng bộ tự động.</li>';
        echo '<li>Thiết lập Cronjob trên Mắt Bão cPanel chạy mỗi 5 phút: <code>php /path/to/backend/jobs/retry_google_sheet.php</code></li>';
        echo '</ul></div>';
        echo '</div></body></html>';
    }

} catch (Throwable $e) {
    out('CÀI ĐẶT THẤT BẠI: ' . $e->getMessage(), 'error');
    if (!$isCli) {
        echo '</div></body></html>';
    }
}
