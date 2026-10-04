<?php
// admin/analytics.php
// Thống kê truy cập & chuyển đổi đặt lịch

require_once __DIR__ . '/../backend/auth/AdminAuth.php';
require_once __DIR__ . '/../backend/repositories/AnalyticsRepository.php';
require_once __DIR__ . '/layout.php';

AdminAuth::requireAuth();

$analyticsRepo = new AnalyticsRepository();

$daysParam = isset($_GET['days']) ? (int)$_GET['days'] : 30;
$days = in_array($daysParam, [1, 7, 30, 90], true) ? $daysParam : 30;

if ($days === 1) {
    $todayStats = $analyticsRepo->getTodayStats();
    $sessions = $todayStats['sessions'];
    $pageViews = $todayStats['page_views'];
    $bookings = $todayStats['bookings'];
    $conv = $todayStats['conversion'];
} else {
    $summary = $analyticsRepo->getSummaryStats($days);
    $sessions = $summary['sessions'];
    $bookings = $summary['bookings'];
    $conv = $summary['conversion'];

    // Page views count for N days
    $pdo = Database::getConnection();
    $since = date('Y-m-d 00:00:00', strtotime("-{$days} days"));
    $stmtPv = $pdo->prepare('SELECT COUNT(*) FROM page_views WHERE created_at >= :since');
    $stmtPv->execute([':since' => $since]);
    $pageViews = (int)$stmtPv->fetchColumn();
}

$topSources = $analyticsRepo->getTopSources($days, 10);
$topPages = $analyticsRepo->getTopLandingPages($days, 10);

renderAdminHeader('Thống Kê Traffic & Hiệu Quả', 'analytics');
?>

<div class="page-header">
    <h1 class="page-title">📈 Thống Kê & Tỉ Lệ Chuyển Đổi</h1>
    <div style="display: flex; gap: 8px;">
        <a href="/admin/analytics.php?days=1" class="btn <?= $days === 1 ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Hôm nay</a>
        <a href="/admin/analytics.php?days=7" class="btn <?= $days === 7 ? 'btn-primary' : 'btn-secondary' ?> btn-sm">7 ngày</a>
        <a href="/admin/analytics.php?days=30" class="btn <?= $days === 30 ? 'btn-primary' : 'btn-secondary' ?> btn-sm">30 ngày</a>
        <a href="/admin/analytics.php?days=90" class="btn <?= $days === 90 ? 'btn-primary' : 'btn-secondary' ?> btn-sm">90 ngày</a>
    </div>
</div>

<!-- Metrics Overview -->
<div class="grid-cards">
    <div class="card">
        <div class="card-label">Tổng lượt xem trang</div>
        <div class="card-value"><?= number_format($pageViews) ?></div>
        <div class="card-sub"><?= $days === 1 ? 'Trong ngày' : "Trong {$days} ngày qua" ?></div>
    </div>
    <div class="card">
        <div class="card-label">Phiên truy cập (Sessions)</div>
        <div class="card-value"><?= number_format($sessions) ?></div>
        <div class="card-sub">Lượt khách ghé thăm</div>
    </div>
    <div class="card">
        <div class="card-label">Đặt lịch thành công</div>
        <div class="card-value"><?= number_format($bookings) ?></div>
        <div class="card-sub">Khách để lại thông tin</div>
    </div>
    <div class="card">
        <div class="card-label">Tỉ lệ chuyển đổi (CR)</div>
        <div class="card-value" style="color: var(--primary);"><?= $conv ?>%</div>
        <div class="card-sub">Đặt lịch / Phiên truy cập</div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
    <!-- Top Sources -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div style="padding: 16px 20px; border-bottom: 1px solid var(--border); font-weight: 700;">
            🌐 Nguồn truy cập (UTM Source / Referrer)
        </div>
        <div class="table-responsive" style="border: none; box-shadow: none;">
            <table>
                <thead>
                    <tr>
                        <th>Nguồn</th>
                        <th style="text-align: right;">Số phiên</th>
                        <th style="text-align: right;">Tỉ lệ</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($topSources)): ?>
                    <tr>
                        <td colspan="3" style="text-align: center; color: var(--text-muted); padding: 24px;">
                            Chưa có dữ liệu phiên truy cập.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($topSources as $src): ?>
                        <?php
                            $pct = ($sessions > 0) ? round(($src['session_count'] / $sessions) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($src['source'] ?: 'Trực tiếp / direct') ?></strong></td>
                            <td style="text-align: right;"><?= number_format($src['session_count']) ?></td>
                            <td style="text-align: right; color: var(--text-muted); font-size: 12.5px;"><?= $pct ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Top Landing Pages -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div style="padding: 16px 20px; border-bottom: 1px solid var(--border); font-weight: 700;">
            📄 Trang đích phổ biến nhất (Landing Pages)
        </div>
        <div class="table-responsive" style="border: none; box-shadow: none;">
            <table>
                <thead>
                    <tr>
                        <th>Trang đích</th>
                        <th style="text-align: right;">Lượt vào</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($topPages)): ?>
                    <tr>
                        <td colspan="2" style="text-align: center; color: var(--text-muted); padding: 24px;">
                            Chưa có dữ liệu trang đích.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($topPages as $p): ?>
                        <tr>
                            <td>
                                <a href="<?= htmlspecialchars($p['landing_page']) ?>" target="_blank" style="text-decoration: none; color: var(--primary); font-family: monospace; font-size: 13px;">
                                    <?= htmlspecialchars($p['landing_page']) ?> ↗
                                </a>
                            </td>
                            <td style="text-align: right; font-weight: 600;"><?= number_format($p['count']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
renderAdminFooter();
