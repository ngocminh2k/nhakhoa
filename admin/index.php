<?php
// admin/index.php
// Admin Dashboard & Central Operations Hub

require_once __DIR__ . '/../backend/auth/AdminAuth.php';
require_once __DIR__ . '/../backend/core/Database.php';
require_once __DIR__ . '/../backend/repositories/BookingRepository.php';
require_once __DIR__ . '/../backend/repositories/PostRepository.php';
require_once __DIR__ . '/../backend/repositories/ServiceRepository.php';
require_once __DIR__ . '/../backend/security/Csrf.php';
require_once __DIR__ . '/../backend/security/Sanitizer.php';
require_once __DIR__ . '/layout.php';

AdminAuth::requireAuth();

$pdo = Database::getConnection();
$bookingRepo = new BookingRepository();
$postRepo = new PostRepository();
$serviceRepo = new ServiceRepository();

$alert = ['type' => '', 'msg' => ''];

// Handle direct in-place management actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!Csrf::verifyToken($token)) {
        $alert = ['type' => 'error', 'msg' => 'Phiên làm việc bảo mật đã hết hạn. Vui lòng tải lại trang.'];
    } else {
        $action = $_POST['action'] ?? '';

        // 1. Quick status update for bookings directly from dashboard
        if ($action === 'update_booking_status') {
            $bookingId = (int)($_POST['booking_id'] ?? 0);
            $newStatus = trim($_POST['status'] ?? '');
            $validStatuses = ['new', 'confirmed', 'contacted', 'completed', 'cancelled', 'no_show'];
            if ($bookingId > 0 && in_array($newStatus, $validStatuses, true)) {
                $bookingRepo->updateStatus($bookingId, $newStatus);
                $alert = ['type' => 'success', 'msg' => "Đã cập nhật trạng thái lịch hẹn #{$bookingId} thành '" . htmlspecialchars($newStatus) . "'."];
            }
        }

        // 2. Direct intake: Receptionist records call-in / walk-in appointment
        elseif ($action === 'quick_booking') {
            $name = Sanitizer::text($_POST['customer_name'] ?? '');
            $phone = Sanitizer::phone($_POST['phone'] ?? '');
            $serviceId = (int)($_POST['service_id'] ?? 1);
            $date = trim($_POST['booking_date'] ?? date('Y-m-d'));
            $time = trim($_POST['booking_time'] ?? '09:00');
            $notes = Sanitizer::text($_POST['notes'] ?? 'Khách gọi qua hotline');

            if (!$name || !$phone) {
                $alert = ['type' => 'error', 'msg' => 'Vui lòng nhập họ tên và số điện thoại của bệnh nhân.'];
            } else {
                $service = $serviceRepo->findById($serviceId);
                $duration = $service ? (int)$service['duration_minutes'] : 30;
                $startTime = strlen($time) === 5 ? $time . ':00' : $time;
                $endTime = date('H:i:s', strtotime("{$date} {$startTime} + {$duration} minutes"));

                // Conflict check
                if ($bookingRepo->hasConflict($date, $startTime, $endTime)) {
                    $alert = ['type' => 'error', 'msg' => "Khung giờ {$startTime} ngày " . date('d/m/Y', strtotime($date)) . " đã có lịch hẹn khác. Vui lòng chọn khung giờ khác."];
                } else {
                    $bookingCode = 'KD' . date('ymd') . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
                    $bookingId = $bookingRepo->create([
                        'booking_code'     => $bookingCode,
                        'customer_name'    => $name,
                        'phone'            => $phone,
                        'service_id'       => $serviceId,
                        'booking_date'     => $date,
                        'start_time'       => $startTime,
                        'end_time'         => $endTime,
                        'duration_minutes' => $duration,
                        'status'           => 'confirmed',
                        'notes'            => $notes,
                        'source_page'      => 'admin_intake'
                    ]);
                    $alert = ['type' => 'success', 'msg' => "Đã tiếp nhận lịch hẹn thành công cho khách hàng {$name}! Mã hẹn: {$bookingCode}"];
                }
            }
        }

        // 3. Quick toggle post status
        elseif ($action === 'toggle_post_status') {
            $postId = (int)($_POST['post_id'] ?? 0);
            $newStatus = trim($_POST['status'] ?? '');
            if ($postId > 0 && in_array($newStatus, ['published', 'draft', 'archived'], true)) {
                $postRepo->update($postId, ['status' => $newStatus]);
                $alert = ['type' => 'success', 'msg' => "Đã chuyển trạng thái bài viết #{$postId} sang '{$newStatus}'."];
            }
        }
    }
}

$today = date('Y-m-d');
$sevenDaysAgo = date('Y-m-d', strtotime('-7 days'));
$thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));

// 1. Today Booking Stats
$stmtToday = $pdo->prepare('
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status = "confirmed" THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN status = "new" THEN 1 ELSE 0 END) as new_count,
        SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed
    FROM bookings
    WHERE booking_date = :today
');
$stmtToday->execute([':today' => $today]);
$todayStats = $stmtToday->fetch() ?: ['total' => 0, 'confirmed' => 0, 'new_count' => 0, 'completed' => 0];

// 2. 7-Day & 30-Day Stats
$stmt7d = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE booking_date >= :since');
$stmt7d->execute([':since' => $sevenDaysAgo]);
$bookings7d = (int)$stmt7d->fetchColumn();

$stmt30d = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE booking_date >= :since');
$stmt30d->execute([':since' => $thirtyDaysAgo]);
$bookings30d = (int)$stmt30d->fetchColumn();

// 3. Traffic & Conversion rate (30 days)
$stmtVisits = $pdo->prepare('SELECT COUNT(*) FROM sessions WHERE created_at >= :since');
$stmtVisits->execute([':since' => $thirtyDaysAgo . ' 00:00:00']);
$sessions30d = (int)$stmtVisits->fetchColumn();
$convRate30d = $sessions30d > 0 ? round(($bookings30d / $sessions30d) * 100, 2) : 0;

// 4. Pending / Action-required Bookings
$stmtPending = $pdo->query('
    SELECT b.*, s.name as service_name
    FROM bookings b
    LEFT JOIN services s ON b.service_id = s.id
    WHERE b.status IN ("new", "confirmed", "contacted")
    ORDER BY b.booking_date ASC, b.start_time ASC
    LIMIT 10
');
$pendingBookings = $stmtPending->fetchAll();

// 5. Recent 5 Articles (Real database posts)
$recentPosts = $postRepo->getAllPosts(5);

// 6. Active services list for quick intake
$activeServices = $serviceRepo->getActiveServices();

$csrfToken = Csrf::generateToken();

renderAdminHeader('Tổng Quan Hoạt Động & Quản Lý Trung Tâm', 'dashboard');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">📊 Trung Tâm Điều Hành Phòng Khám</h1>
        <div style="font-size: 13.5px; color: var(--text-muted); margin-top: 4px;">
            Hệ thống Quản lý Đặt hẹn &amp; Nội dung Chuyên khoa Nha Khoa Kim Dung — Hôm nay: <strong style="color: var(--primary);"><?= date('d/m/Y') ?></strong>
        </div>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="#quickIntake" class="btn btn-primary" onclick="document.getElementById('intakeCustomerName').focus();">
            📞 + Tiếp nhận khách hẹn
        </a>
        <a href="/admin/posts.php?new=1" class="btn btn-secondary">
            📝 + Viết bài đăng mới
        </a>
    </div>
</div>

<?php if ($alert['msg']): ?>
    <div class="alert alert-<?= $alert['type'] ?>">
        <?= $alert['type'] === 'success' ? '✅' : '⚠️' ?> <?= htmlspecialchars($alert['msg']) ?>
    </div>
<?php endif; ?>

<!-- KPI Cards -->
<div class="grid-cards">
    <div class="card">
        <div class="card-label">Lịch hẹn hôm nay</div>
        <div class="card-value"><?= (int)$todayStats['total'] ?></div>
        <div class="card-sub">
            <span style="color: #b45309; font-weight: 700;"><?= (int)$todayStats['new_count'] ?> mới</span>,
            <span style="color: var(--primary); font-weight: 700;"><?= (int)$todayStats['confirmed'] ?> đã xác nhận</span>
        </div>
    </div>
    <div class="card">
        <div class="card-label">Đặt lịch 7 ngày qua</div>
        <div class="card-value"><?= $bookings7d ?></div>
        <div class="card-sub">Lượt bệnh nhân đăng ký khám</div>
    </div>
    <div class="card">
        <div class="card-label">Đặt lịch 30 ngày qua</div>
        <div class="card-value"><?= $bookings30d ?></div>
        <div class="card-sub">Tỉ lệ chuyển đổi: <strong style="color: var(--primary);"><?= $convRate30d ?>%</strong></div>
    </div>
    <div class="card">
        <div class="card-label">Lượt truy cập 30 ngày</div>
        <div class="card-value"><?= number_format($sessions30d) ?></div>
        <div class="card-sub">Phiên người dùng website</div>
    </div>
</div>

<!-- SECTION 1: IN-PLACE BOOKING MANAGEMENT -->
<div class="card" style="padding: 0; overflow: hidden; margin-bottom: 28px;">
    <div style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: #FFFDF6;">
        <div>
            <strong style="font-size: 16px; color: var(--primary);">⚡ Lịch Hẹn Cần Xử Lý Trực Tiếp (<?= count($pendingBookings) ?>)</strong>
            <div style="font-size: 12.5px; color: var(--text-muted); margin-top: 2px;">Cập nhật trạng thái trực tiếp: Gọi điện xác nhận, đổi trạng thái bệnh nhân</div>
        </div>
        <a href="/admin/bookings.php" class="btn btn-secondary btn-sm">Xem tất cả lịch hẹn →</a>
    </div>

    <div class="table-responsive" style="border: none; border-radius: 0; box-shadow: none;">
        <table>
            <thead>
                <tr>
                    <th>Mã Đơn</th>
                    <th>Khách Hàng</th>
                    <th>Dịch Vụ Khám</th>
                    <th>Ngày &amp; Giờ Hẹn</th>
                    <th>Trạng Thái</th>
                    <th style="text-align: right; min-width: 220px;">Thao Tác Trực Tiếp</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($pendingBookings)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 36px;">
                        🎉 Hiện không có lịch hẹn mới nào cần xử lý.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($pendingBookings as $b): ?>
                    <tr>
                        <td>
                            <strong style="font-family: monospace; color: var(--primary); font-size: 13.5px;"><?= htmlspecialchars($b['booking_code']) ?></strong>
                            <div style="font-size: 11.5px; color: var(--text-muted);"><?= date('H:i d/m', strtotime($b['created_at'])) ?></div>
                        </td>
                        <td>
                            <div style="font-weight: 700; color: #18181b; font-size: 14.5px;"><?= htmlspecialchars($b['customer_name']) ?></div>
                            <div style="margin-top: 2px;">
                                <a href="tel:<?= htmlspecialchars($b['phone']) ?>" style="color: var(--primary); text-decoration: none; font-weight: 600; font-size: 13px;">
                                    📞 <?= htmlspecialchars($b['phone']) ?>
                                </a>
                            </div>
                            <?php if (!empty($b['notes'])): ?>
                                <div style="font-size: 12px; color: #64748b; margin-top: 4px; font-style: italic;">
                                    "<?= htmlspecialchars($b['notes']) ?>"
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="font-weight: 600; color: #27272a;"><?= htmlspecialchars($b['service_name'] ?? 'Khám tổng quát') ?></span>
                        </td>
                        <td>
                            <div style="font-weight: 700; color: #18181b;"><?= date('d/m/Y', strtotime($b['booking_date'])) ?></div>
                            <div style="font-size: 12.5px; color: var(--text-muted);">
                                ⏰ <?= substr($b['start_time'], 0, 5) ?> - <?= substr($b['end_time'], 0, 5) ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-<?= $b['status'] ?>">
                                <?php
                                    $labels = [
                                        'new' => 'Mới đăng ký',
                                        'confirmed' => 'Đã xác nhận',
                                        'contacted' => 'Đã liên hệ',
                                        'completed' => 'Đã khám xong',
                                        'cancelled' => 'Đã hủy',
                                        'no_show' => 'Khách vắng mặt'
                                    ];
                                    echo $labels[$b['status']] ?? htmlspecialchars($b['status']);
                                ?>
                            </span>
                        </td>
                        <td style="text-align: right;">
                            <form method="POST" action="/admin/index.php" style="display: inline-flex; gap: 6px; justify-content: flex-end; align-items: center;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="action" value="update_booking_status">
                                <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">

                                <?php if ($b['status'] !== 'confirmed'): ?>
                                    <button type="submit" name="status" value="confirmed" class="btn btn-primary btn-sm" title="Xác nhận lịch hẹn">
                                        ✓ Xác nhận
                                    </button>
                                <?php endif; ?>

                                <?php if ($b['status'] !== 'contacted'): ?>
                                    <button type="submit" name="status" value="contacted" class="btn btn-secondary btn-sm" title="Đã gọi điện tư vấn">
                                        📞 Đã gọi
                                    </button>
                                <?php endif; ?>

                                <?php if ($b['status'] !== 'completed'): ?>
                                    <button type="submit" name="status" value="completed" class="btn btn-secondary btn-sm" style="border-color: #005A36; color: #005A36;" title="Đã khám hoàn tất">
                                        🏥 Xong
                                    </button>
                                <?php endif; ?>

                                <button type="submit" name="status" value="cancelled" class="btn btn-danger btn-sm" onclick="return confirm('Bạn có chắc chắn muốn hủy lịch hẹn này?');" title="Hủy lịch">
                                    ✕
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div style="display: grid; grid-template-columns: 3fr 2fr; gap: 24px; margin-bottom: 28px;">

    <!-- SECTION 2: ARTICLES MANAGEMENT -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: #FFFDF6;">
            <div>
                <strong style="font-size: 15px; color: var(--primary);">📰 Bài Viết &amp; Tin Tức Mới Nhất</strong>
                <div style="font-size: 12px; color: var(--text-muted);">Các bài đã đăng lên hệ thống và bài viết qua API</div>
            </div>
            <div style="display: flex; gap: 8px;">
                <a href="/admin/posts.php?new=1" class="btn btn-primary btn-sm">+ Thêm bài</a>
                <a href="/admin/posts.php" class="btn btn-secondary btn-sm">Quản lý</a>
            </div>
        </div>

        <div class="table-responsive" style="border: none; box-shadow: none;">
            <table>
                <thead>
                    <tr>
                        <th>Tiêu đề bài viết</th>
                        <th>Trạng thái</th>
                        <th>Ngày tạo</th>
                        <th style="text-align: right;">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($recentPosts)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 24px;">
                            Chưa có bài viết nào trong cơ sở dữ liệu.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentPosts as $p): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 700; color: #18181b; font-size: 13.5px;"><?= htmlspecialchars($p['title']) ?></div>
                                <div style="font-size: 11.5px; color: var(--text-muted); font-family: monospace;">
                                    /tin-tuc/<?= htmlspecialchars($p['slug']) ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-<?= $p['status'] ?>">
                                    <?= $p['status'] === 'published' ? 'Đã xuất bản' : ($p['status'] === 'draft' ? 'Bản nháp' : 'Lưu trữ') ?>
                                </span>
                            </td>
                            <td style="font-size: 12.5px; color: var(--text-muted);">
                                <?= date('d/m/Y', strtotime($p['created_at'])) ?>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <?php if ($p['status'] === 'published'): ?>
                                    <a href="/tin-tuc/<?= htmlspecialchars($p['slug']) ?>" target="_blank" class="btn btn-secondary btn-sm" title="Mở trang xem bài viết trên giao diện website">
                                        👁️ Xem web ↗
                                    </a>
                                <?php endif; ?>
                                <a href="/admin/posts.php?edit=<?= $p['id'] ?>" class="btn btn-secondary btn-sm" title="Chỉnh sửa nội dung">
                                    ✏️ Sửa
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- SECTION 3: QUICK APPOINTMENT INTAKE (HOTLINE / WALK-IN) -->
    <div class="card" id="quickIntake" style="border: 2px solid rgba(0, 90, 54, 0.2);">
        <div style="font-size: 15px; font-weight: 700; color: var(--primary); margin-bottom: 12px; border-bottom: 1px solid var(--border); padding-bottom: 10px; display: flex; align-items: center; gap: 8px;">
            <span>📞 Tiếp Nhận Khách Hẹn Nhanh</span>
            <span style="font-size: 11px; font-weight: normal; background: rgba(234, 191, 14, 0.2); color: #854d0e; padding: 2px 8px; border-radius: 99px;">Hotline 0862 960 886</span>
        </div>

        <form method="POST" action="/admin/index.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="quick_booking">

            <div style="margin-bottom: 12px;">
                <label style="display: block; font-size: 12.5px; font-weight: 600; margin-bottom: 4px;">Họ tên khách hàng *</label>
                <input type="text" id="intakeCustomerName" name="customer_name" required placeholder="Ví dụ: Nguyễn Văn A" style="width: 100%; padding: 8px 12px; font-size: 13.5px;">
            </div>

            <div style="margin-bottom: 12px;">
                <label style="display: block; font-size: 12.5px; font-weight: 600; margin-bottom: 4px;">Số điện thoại *</label>
                <input type="tel" name="phone" required placeholder="Ví dụ: 0987654321" style="width: 100%; padding: 8px 12px; font-size: 13.5px;">
            </div>

            <div style="margin-bottom: 12px;">
                <label style="display: block; font-size: 12.5px; font-weight: 600; margin-bottom: 4px;">Dịch vụ yêu cầu</label>
                <select name="service_id" style="width: 100%; padding: 8px 12px; font-size: 13.5px;">
                    <?php foreach ($activeServices as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (<?= (int)$s['duration_minutes'] ?>p)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                <div>
                    <label style="display: block; font-size: 12.5px; font-weight: 600; margin-bottom: 4px;">Ngày hẹn</label>
                    <input type="date" name="booking_date" value="<?= date('Y-m-d') ?>" style="width: 100%; padding: 8px 10px; font-size: 13px;">
                </div>
                <div>
                    <label style="display: block; font-size: 12.5px; font-weight: 600; margin-bottom: 4px;">Giờ hẹn</label>
                    <input type="time" name="booking_time" value="09:00" style="width: 100%; padding: 8px 10px; font-size: 13px;">
                </div>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 12.5px; font-weight: 600; margin-bottom: 4px;">Ghi chú tư vấn</label>
                <textarea name="notes" rows="2" placeholder="Tình trạng răng miệng, yêu cầu khám..." style="width: 100%; padding: 8px 10px; font-size: 13px;"></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 10px; font-weight: 700;">
                ✓ Lưu Lịch Hẹn Ngay
            </button>
        </form>
    </div>

</div>

<?php
renderAdminFooter();
?>
