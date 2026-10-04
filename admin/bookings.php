<?php
// admin/bookings.php
// Quản lý danh sách đặt lịch khám

require_once __DIR__ . '/../backend/auth/AdminAuth.php';
require_once __DIR__ . '/../backend/repositories/BookingRepository.php';
require_once __DIR__ . '/../backend/repositories/ServiceRepository.php';
require_once __DIR__ . '/../backend/services/GoogleSheetsService.php';
require_once __DIR__ . '/../backend/services/AvailabilityService.php';
require_once __DIR__ . '/../backend/security/Csrf.php';
require_once __DIR__ . '/layout.php';

AdminAuth::requireAuth();

$bookingRepo = new BookingRepository();
$serviceRepo = new ServiceRepository();
$services = $serviceRepo->getActiveServices();

$alert = ['type' => '', 'msg' => ''];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!Csrf::verifyToken($token)) {
        $alert = ['type' => 'error', 'msg' => 'Mã bảo mật CSRF không hợp lệ hoặc đã hết hạn.'];
    } else {
        $action = $_POST['action'] ?? '';
        $bookingId = (int)($_POST['booking_id'] ?? 0);

        if ($action === 'update_status' && $bookingId > 0) {
            $newStatus = $_POST['status'] ?? '';
            $validStatuses = ['new', 'confirmed', 'contacted', 'completed', 'cancelled', 'no_show'];
            if (in_array($newStatus, $validStatuses, true)) {
                $bookingRepo->updateStatus($bookingId, $newStatus);
                $alert = ['type' => 'success', 'msg' => "Đã cập nhật trạng thái lịch #{$bookingId} thành '{$newStatus}'."];
            }
        } elseif ($action === 'retry_sync' && $bookingId > 0) {
            $booking = $bookingRepo->findById($bookingId);
            if ($booking) {
                $sheets = new GoogleSheetsService();
                $res = $sheets->appendBooking($booking);
                if (!empty($res['success'])) {
                    $bookingRepo->updateSheetSync($bookingId, 'synced', null);
                    $alert = ['type' => 'success', 'msg' => "Đã đồng bộ thành công lịch {$booking['booking_code']} lên Google Sheets."];
                } else {
                    $bookingRepo->updateSheetSync($bookingId, 'failed', $res['error'] ?? 'Sync failed');
                    $alert = ['type' => 'error', 'msg' => "Không thể đồng bộ lên Google Sheets. Lỗi: " . ($res['error'] ?? 'Timeout hoặc sai cấu hình credentials')];
                }
            }
        } elseif ($action === 'reschedule' && $bookingId > 0) {
            $newDate = trim($_POST['new_date'] ?? '');
            $newTime = trim($_POST['new_time'] ?? '');
            $booking = $bookingRepo->findById($bookingId);

            if ($booking && $newDate && $newTime) {
                $duration = (int)($booking['duration_minutes'] ?: 30);
                $startTimeStr = strlen($newTime) === 5 ? $newTime . ':00' : $newTime;
                $endTimeStr = date('H:i:s', strtotime("{$newDate} {$startTimeStr} + {$duration} minutes"));

                // Conflict check
                if ($bookingRepo->hasConflict($newDate, $startTimeStr, $endTimeStr, $bookingId)) {
                    $alert = ['type' => 'error', 'msg' => "Khung giờ {$startTimeStr} ngày {$newDate} đã bị trùng lịch với khách khác. Vui lòng chọn giờ khác."];
                } else {
                    $bookingRepo->updateDateTime($bookingId, $newDate, $startTimeStr, $endTimeStr);
                    $alert = ['type' => 'success', 'msg' => "Đã dời lịch #{$bookingId} sang {$startTimeStr} ngày {$newDate}."];
                }
            }
        }
    }
}

// Filters & Pagination
$filters = [
    'search'      => trim($_GET['search'] ?? ''),
    'date'        => trim($_GET['date'] ?? ''),
    'service_id'  => trim($_GET['service_id'] ?? ''),
    'status'      => trim($_GET['status'] ?? ''),
    'sync_status' => trim($_GET['sync_status'] ?? ''),
];
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$data = $bookingRepo->search($filters, $page, $perPage);
$items = $data['items'];
$totalPages = $data['total_pages'];
$total = $data['total'];

$csrfToken = Csrf::generateToken();

renderAdminHeader('Quản lý Đặt Lịch Khám', 'bookings');
?>

<div class="page-header">
    <h1 class="page-title">📅 Quản Lý Đặt Lịch Khám (<?= $total ?>)</h1>
    <a href="/admin/bookings.php" class="btn btn-secondary btn-sm">Làm mới bộ lọc</a>
</div>

<?php if ($alert['msg']): ?>
    <div class="alert alert-<?= $alert['type'] ?>">
        <?= htmlspecialchars($alert['msg']) ?>
    </div>
<?php endif; ?>

<!-- Filter Box -->
<div class="card" style="margin-bottom: 20px; padding: 16px;">
    <form method="GET" action="/admin/bookings.php" class="form-inline">
        <input type="text" name="search" value="<?= htmlspecialchars($filters['search']) ?>" placeholder="Tìm tên, SĐT, mã đặt lịch..." style="min-width: 200px;">
        <input type="date" name="date" value="<?= htmlspecialchars($filters['date']) ?>">

        <select name="service_id">
            <option value="">-- Tất cả dịch vụ --</option>
            <?php foreach ($services as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $filters['service_id'] == $s['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="status">
            <option value="">-- Mọi trạng thái --</option>
            <option value="new" <?= $filters['status'] === 'new' ? 'selected' : '' ?>>Mới (New)</option>
            <option value="confirmed" <?= $filters['status'] === 'confirmed' ? 'selected' : '' ?>>Đã xác nhận</option>
            <option value="contacted" <?= $filters['status'] === 'contacted' ? 'selected' : '' ?>>Đã liên hệ</option>
            <option value="completed" <?= $filters['status'] === 'completed' ? 'selected' : '' ?>>Hoàn thành</option>
            <option value="cancelled" <?= $filters['status'] === 'cancelled' ? 'selected' : '' ?>>Đã hủy</option>
            <option value="no_show" <?= $filters['status'] === 'no_show' ? 'selected' : '' ?>>Không đến</option>
        </select>

        <select name="sync_status">
            <option value="">-- Google Sheets --</option>
            <option value="synced" <?= $filters['sync_status'] === 'synced' ? 'selected' : '' ?>>Đã đồng bộ</option>
            <option value="failed" <?= $filters['sync_status'] === 'failed' ? 'selected' : '' ?>>Đồng bộ lỗi</option>
            <option value="pending" <?= $filters['sync_status'] === 'pending' ? 'selected' : '' ?>>Đang chờ</option>
        </select>

        <button type="submit" class="btn btn-primary">Lọc</button>
    </form>
</div>

<!-- Bookings Table -->
<div class="table-responsive">
    <table>
        <thead>
            <tr>
                <th>Mã</th>
                <th>Khách hàng</th>
                <th>Dịch vụ</th>
                <th>Ngày & Giờ</th>
                <th>Ghi chú</th>
                <th>Nguồn / UTM</th>
                <th>Trạng thái</th>
                <th>Google Sheets</th>
                <th>Thao tác</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($items)): ?>
            <tr>
                <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 36px;">
                    Không tìm thấy lịch khám nào khớp với bộ lọc.
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($items as $b): ?>
                <tr>
                    <td>
                        <strong style="font-family: monospace; font-size: 13px;"><?= htmlspecialchars($b['booking_code']) ?></strong>
                        <div style="font-size: 11px; color: var(--text-muted);"><?= date('H:i d/m/Y', strtotime($b['created_at'])) ?></div>
                    </td>
                    <td>
                        <div><strong><?= htmlspecialchars($b['customer_name']) ?></strong></div>
                        <div style="color: var(--primary); font-weight: 500; font-size: 13px;">
                            <a href="tel:<?= htmlspecialchars($b['phone']) ?>" style="text-decoration: none; color: inherit;"><?= htmlspecialchars($b['phone']) ?></a>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($b['service_name'] ?? 'Khám tổng quát') ?></td>
                    <td>
                        <strong><?= date('d/m/Y', strtotime($b['booking_date'])) ?></strong>
                        <div style="color: var(--text-muted); font-size: 12px;"><?= substr($b['start_time'], 0, 5) ?> - <?= substr($b['end_time'], 0, 5) ?></div>
                    </td>
                    <td style="max-width: 220px; font-size: 12.5px; color: #475569;">
                        <?= htmlspecialchars($b['notes'] ?: '-') ?>
                    </td>
                    <td style="font-size: 11.5px; color: var(--text-muted);">
                        <?php if ($b['utm_source']): ?>
                            <div>src: <strong><?= htmlspecialchars($b['utm_source']) ?></strong></div>
                        <?php endif; ?>
                        <?php if ($b['utm_campaign']): ?>
                            <div>camp: <?= htmlspecialchars($b['utm_campaign']) ?></div>
                        <?php endif; ?>
                        <?php if ($b['referrer'] && !$b['utm_source']): ?>
                            <div title="<?= htmlspecialchars($b['referrer']) ?>">ref: <?= htmlspecialchars(substr($b['referrer'], 0, 20)) ?>...</div>
                        <?php endif; ?>
                        <?php if (!$b['utm_source'] && !$b['referrer']): ?>
                            <span>Trực tiếp</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="POST" action="/admin/bookings.php" style="display: inline-block;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                            <select name="status" onchange="this.form.submit()" style="padding: 3px 6px; font-size: 11.5px; font-weight: 600;">
                                <option value="new" <?= $b['status'] === 'new' ? 'selected' : '' ?>>Mới</option>
                                <option value="confirmed" <?= $b['status'] === 'confirmed' ? 'selected' : '' ?>>Đã xác nhận</option>
                                <option value="contacted" <?= $b['status'] === 'contacted' ? 'selected' : '' ?>>Đã gọi điện</option>
                                <option value="completed" <?= $b['status'] === 'completed' ? 'selected' : '' ?>>Hoàn thành</option>
                                <option value="cancelled" <?= $b['status'] === 'cancelled' ? 'selected' : '' ?>>Hủy hẹn</option>
                                <option value="no_show" <?= $b['status'] === 'no_show' ? 'selected' : '' ?>>Không đến</option>
                            </select>
                        </form>
                    </td>
                    <td>
                        <span class="badge badge-<?= $b['sheet_sync_status'] ?>">
                            <?= htmlspecialchars($b['sheet_sync_status']) ?>
                        </span>
                        <?php if ($b['sheet_sync_status'] === 'failed'): ?>
                            <form method="POST" action="/admin/bookings.php" style="margin-top: 4px;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="action" value="retry_sync">
                                <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                <button type="submit" class="btn btn-secondary btn-sm" style="font-size: 10px; padding: 2px 6px;">Thử lại</button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="openRescheduleModal(<?= $b['id'] ?>, '<?= $b['booking_code'] ?>', '<?= $b['booking_date'] ?>', '<?= substr($b['start_time'], 0, 5) ?>')">
                            Dời giờ
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
    <div style="display: flex; justify-content: center; gap: 8px; margin-top: 24px;">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php
                $q = $_GET;
                $q['page'] = $i;
                $pageUrl = '/admin/bookings.php?' . http_build_query($q);
            ?>
            <a href="<?= htmlspecialchars($pageUrl) ?>" class="btn <?= $i === $page ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
                <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<!-- Reschedule Dialog -->
<div id="rescheduleModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="card" style="width: 100%; max-width: 420px; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
        <h3 style="margin-bottom: 12px;">⏰ Dời ngày giờ khám: <span id="modalCode" style="font-family: monospace;"></span></h3>
        <form method="POST" action="/admin/bookings.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="reschedule">
            <input type="hidden" name="booking_id" id="modalBookingId" value="">

            <div style="margin-bottom: 12px;">
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Ngày mới</label>
                <input type="date" name="new_date" id="modalDate" required style="width: 100%;">
            </div>
            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Giờ hẹn (HH:MM)</label>
                <input type="time" name="new_time" id="modalTime" required step="1800" style="width: 100%;">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" class="btn btn-secondary" onclick="closeRescheduleModal()">Hủy</button>
                <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRescheduleModal(id, code, date, time) {
    document.getElementById('modalBookingId').value = id;
    document.getElementById('modalCode').innerText = code;
    document.getElementById('modalDate').value = date;
    document.getElementById('modalTime').value = time;
    const m = document.getElementById('rescheduleModal');
    m.style.display = 'flex';
}
function closeRescheduleModal() {
    document.getElementById('rescheduleModal').style.display = 'none';
}
</script>

<?php
renderAdminFooter();
