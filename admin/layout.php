<?php
// admin/layout.php
// Reusable admin template wrapper with Greenfield Luxury Dental Design System

function renderAdminHeader(string $title, string $activeNav = 'dashboard'): void {
    $admin = AdminAuth::user();
    $name = htmlspecialchars($admin['name'] ?? 'Admin', ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> — Quản Trị Nha Khoa Kim Dung</title>
    <link rel="icon" type="image/x-icon" href="https://nhakhoakimdung.vn/upload/photo/url-1-1753346947.webp">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
    <style>
        :root {
            --bg: #FAF8F5;
            --surface: #ffffff;
            --border: #e4e4e7;
            --border-gold: rgba(234, 191, 14, 0.4);
            --text-main: #18181b;
            --text-muted: #64748b;
            --primary: #005A36;
            --primary-hover: #004529;
            --primary-light: #e6f4ee;
            --gold: #EABF0E;
            --gold-hover: #D4AC0B;
            --gold-light: #FFFDF6;
            --gold-text: #854d0e;
            --success: #15803d;
            --success-light: #dcfce7;
            --warning: #b45309;
            --warning-light: #fef3c7;
            --danger: #b91c1c;
            --danger-light: #fee2e2;
            --font: 'Be Vietnam Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            font-family: var(--font);
            background: var(--bg);
            color: var(--text-main);
            font-size: 14px;
            line-height: 1.5;
            width: 100%;
            max-width: 100vw;
            overflow-x: hidden;
        }

        /* Layout Grid */
        .admin-wrap {
            display: flex;
            min-height: 100vh;
            width: 100%;
            max-width: 100vw;
            overflow-x: hidden;
        }
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #004529 0%, #005A36 60%, #003620 100%);
            color: #FAF8F5;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            border-right: 1px solid rgba(234, 191, 14, 0.25);
            box-shadow: 2px 0 12px rgba(0, 0, 0, 0.04);
        }
        .sidebar-brand {
            padding: 24px 20px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
            text-align: center;
        }
        .sidebar-brand img {
            max-height: 48px;
            width: auto;
            margin-bottom: 10px;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
        }
        .sidebar-brand-name {
            font-size: 15px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }
        .sidebar-brand-sub {
            font-size: 11px;
            color: var(--gold);
            font-weight: 600;
            letter-spacing: 0.04em;
            margin-top: 2px;
        }
        .sidebar-nav {
            list-style: none;
            padding: 20px 14px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 16px;
            border-radius: 10px;
            color: rgba(255, 255, 255, 0.82);
            text-decoration: none;
            font-weight: 600;
            font-size: 13.5px;
            transition: all 0.18s ease;
        }
        .sidebar-nav a i {
            font-size: 15px;
            width: 20px;
            text-align: center;
            color: rgba(234, 191, 14, 0.8);
            transition: color 0.18s;
        }
        .sidebar-nav a:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }
        .sidebar-nav a:hover i {
            color: var(--gold);
        }
        .sidebar-nav a.active {
            background: rgba(234, 191, 14, 0.18);
            color: #ffffff;
            border-left: 3px solid var(--gold);
            padding-left: 13px;
        }
        .sidebar-nav a.active i {
            color: var(--gold);
        }
        .sidebar-footer {
            padding: 18px 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.12);
            font-size: 12.5px;
            color: rgba(255, 255, 255, 0.7);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(0, 0, 0, 0.15);
        }
        .sidebar-footer a {
            color: #fca5a5;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.15s;
        }
        .sidebar-footer a:hover {
            color: #f87171;
            text-decoration: underline;
        }

        /* Main Content */
        .content {
            flex: 1;
            min-width: 0;
            padding: 32px 40px;
            max-width: 1440px;
            margin: 0 auto;
            width: calc(100% - 260px);
            box-sizing: border-box;
        }
        pre {
            white-space: pre-wrap;
            word-break: break-word;
            overflow-wrap: break-word;
            max-width: 100%;
        }
        code {
            word-break: break-word;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }
        .page-title {
            font-size: 23px;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: -0.01em;
        }

        /* Cards & Metric Tiles */
        .grid-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }
        .card {
            background: var(--surface);
            border: 1px solid var(--border-gold);
            border-radius: 14px;
            padding: 22px;
            box-shadow: 0 4px 16px rgba(0, 90, 54, 0.04);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .card-label {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-muted);
            margin-bottom: 8px;
        }
        .card-value {
            font-size: 30px;
            font-weight: 800;
            color: var(--primary);
            line-height: 1.1;
        }
        .card-sub {
            font-size: 12.5px;
            color: var(--text-muted);
            margin-top: 6px;
        }

        /* Tables */
        .table-responsive {
            background: var(--surface);
            border: 1px solid var(--border-gold);
            border-radius: 14px;
            overflow-x: auto;
            box-shadow: 0 4px 16px rgba(0, 90, 54, 0.04);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        th {
            background: #FFFDF6;
            padding: 13px 18px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--primary);
            border-bottom: 1px solid var(--border-gold);
        }
        td {
            padding: 15px 18px;
            border-bottom: 1px solid #f1f1f4;
            vertical-align: middle;
        }
        tr:last-child td {
            border-bottom: none;
        }
        tr:hover td {
            background: #fafaf8;
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 99px;
            font-size: 11.5px;
            font-weight: 700;
            letter-spacing: 0.02em;
        }
        .badge-new { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
        .badge-confirmed { background: var(--success-light); color: var(--success); border: 1px solid #bbf7d0; }
        .badge-contacted { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
        .badge-completed { background: #f3e8ff; color: #7e22ce; border: 1px solid #e9d5ff; }
        .badge-cancelled { background: var(--danger-light); color: var(--danger); border: 1px solid #fecaca; }
        .badge-no_show { background: #f4f4f5; color: #52525b; border: 1px solid #e4e4e7; }
        .badge-synced { background: var(--success-light); color: var(--success); }
        .badge-failed { background: var(--danger-light); color: var(--danger); }
        .badge-pending { background: var(--warning-light); color: var(--warning); }
        .badge-published { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
        .badge-draft { background: #f4f4f5; color: #52525b; border: 1px solid #d4d4d8; }
        .badge-archived { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        /* Buttons & Forms */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 9px 16px;
            border-radius: 8px;
            font-size: 13.5px;
            font-weight: 700;
            border: 1px solid transparent;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s;
            font-family: inherit;
        }
        .btn-primary {
            background: var(--primary);
            color: #fff;
            box-shadow: 0 2px 6px rgba(0, 90, 54, 0.2);
        }
        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }
        .btn-secondary {
            background: #fff;
            border-color: #d4d4d8;
            color: var(--text-main);
        }
        .btn-secondary:hover {
            background: #FAF8F5;
            border-color: var(--primary);
            color: var(--primary);
        }
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
            border-radius: 6px;
        }
        .btn-danger {
            background: var(--danger);
            color: #fff;
        }
        .btn-danger:hover {
            background: #991b1b;
        }

        input, select, textarea {
            font-family: inherit;
            font-size: 13.5px;
            padding: 9px 12px;
            border: 1px solid #d4d4d8;
            border-radius: 8px;
            background: #fff;
            color: var(--text-main);
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 90, 54, 0.12);
        }
        .form-inline { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .alert {
            padding: 12px 18px;
            border-radius: 10px;
            margin-bottom: 24px;
            font-size: 14px;
            font-weight: 600;
        }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        @media (max-width: 900px) {
            .admin-wrap { flex-direction: column; }
            .sidebar { width: 100%; }
            .content { width: 100%; padding: 20px 16px; }
        }
    </style>
</head>
<body>
<div class="admin-wrap">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <img src="https://nhakhoakimdung.vn/upload/photo/url-1-1753346899.webp" alt="Nha Khoa Kim Dung">
            <div class="sidebar-brand-name">Nha Khoa Kim Dung</div>
            <div class="sidebar-brand-sub">Trung Tâm Quản Trị Phòng Khám</div>
        </div>
        <ul class="sidebar-nav">
            <li><a href="/admin/index.php" class="<?= $activeNav === 'dashboard' ? 'active' : '' ?>"><i class="fa-solid fa-chart-pie"></i> Tổng quan điều hành</a></li>
            <li><a href="/admin/bookings.php" class="<?= $activeNav === 'bookings' ? 'active' : '' ?>"><i class="fa-solid fa-calendar-check"></i> Quản lý đặt lịch</a></li>
            <li><a href="/admin/services.php" class="<?= $activeNav === 'services' ? 'active' : '' ?>"><i class="fa-solid fa-tooth"></i> Danh mục dịch vụ</a></li>
            <li><a href="/admin/schedule.php" class="<?= $activeNav === 'schedule' ? 'active' : '' ?>"><i class="fa-regular fa-clock"></i> Khung giờ &amp; Lịch nghỉ</a></li>
            <li><a href="/admin/posts.php" class="<?= $activeNav === 'posts' ? 'active' : '' ?>"><i class="fa-solid fa-newspaper"></i> Bài viết chuyên đề</a></li>
            <li><a href="/admin/api_keys.php" class="<?= $activeNav === 'api_keys' ? 'active' : '' ?>"><i class="fa-solid fa-key"></i> Tích hợp API</a></li>
            <li><a href="/admin/analytics.php" class="<?= $activeNav === 'analytics' ? 'active' : '' ?>"><i class="fa-solid fa-arrow-trend-up"></i> Thống kê &amp; Chuyển đổi</a></li>
            <li style="margin-top: 12px; border-top: 1px solid rgba(255,255,255,0.12); padding-top: 12px;">
                <a href="/website/index.html" target="_blank"><i class="fa-solid fa-arrow-up-right-from-square"></i> Xem website ↗</a>
            </li>
        </ul>
        <div class="sidebar-footer">
            <span>👤 <?= $name ?></span>
            <a href="/admin/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Đăng xuất</a>
        </div>
    </aside>
    <main class="content">
<?php
}

function renderAdminFooter(): void {
?>
    </main>
</div>
</body>
</html>
<?php
}
