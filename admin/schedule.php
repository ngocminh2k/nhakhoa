<?php
// admin/schedule.php
// Quản lý giờ làm việc và lịch nghỉ / đóng cửa phòng khám

require_once __DIR__ . '/../backend/auth/AdminAuth.php';
require_once __DIR__ . '/../backend/repositories/ScheduleRepository.php';
require_once __DIR__ . '/../backend/security/Sanitizer.php';
require_once __DIR__ . '/../backend/security/Csrf.php';
require_once __DIR__ . '/layout.php';

AdminAuth::requireAuth();

$scheduleRepo = new ScheduleRepository();
$alert = ['type' => '', 'msg' => ''];

$weekdayLabels = [
    1 => 'Thứ Hai',
    2 => 'Thứ Ba',
    3 => 'Thứ Tư',
    4 => 'Thứ Năm',
    5 => 'Thứ Sáu',
    6 => 'Thứ Bảy',
    0 => 'Chủ Nhật',
];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!Csrf::verifyToken($token)) {
        $alert = ['type' => 'error', 'msg' => 'Phiên bảo mật CSRF không hợp lệ.'];
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_hours') {
            $hoursData = $_POST['hours'] ?? [];
            foreach ($hoursData as $w => $cfg) {
                $w = (int)$w;
                $open = isset($cfg['active']) ? 1 : 0;
                $start = trim($cfg['start_time'] ?? '08:00');
                $end = trim($cfg['end_time'] ?? '19:30');
                if (strlen($start) === 5) $start .= ':00';
                if (strlen($end) === 5) $end .= ':00';
                $scheduleRepo->updateBusinessHours($w, $start, $end, $open);
            }
            $alert = ['type' => 'success', 'msg' => 'Đã cập nhật khung giờ làm việc theo tuần thành công.'];
        } elseif ($action === 'add_block') {
            $startDate = trim($_POST['start_date'] ?? '');
            $startTime = trim($_POST['start_time'] ?? '00:00');
            $endDate = trim($_POST['end_date'] ?? '');
            $endTime = trim($_POST['end_time'] ?? '23:59');
            $reason = Sanitizer::text($_POST['reason'] ?? '');

            if ($startDate && $endDate) {
                $startDt = "{$startDate} {$startTime}:00";
                $endDt = "{$endDate} {$endTime}:00";
                if ($endDt >= $startDt) {
                    $scheduleRepo->addScheduleBlock($startDt, $endDt, $reason);
                    $alert = ['type' => 'success', 'msg' => 'Đã thêm khoảng thời gian nghỉ / khóa lịch.'];
                } else {
                    $alert = ['type' => 'error', 'msg' => 'Thời gian kết thúc phải sau thời gian bắt đầu.'];
                }
            }
        } elseif ($action === 'delete_block') {
            $blockId = (int)($_POST['block_id'] ?? 0);
            if ($blockId > 0) {
                $scheduleRepo->deleteScheduleBlock($blockId);
                $alert = ['type' => 'success', 'msg' => "Đã xóa lịch nghỉ #{$blockId}."];
            }
        }
    }
}

$rawHours = $scheduleRepo->getAllBusinessHours();
$hoursByWeekday = [];
foreach ($rawHours as $h) {
    $hoursByWeekday[$h['weekday']] = $h;
}

$scheduleBlocks = $scheduleRepo->getAllScheduleBlocks(30);
$csrfToken = Csrf::generateToken();

renderAdminHeader('Quản lý Giờ Khám & Lịch Nghỉ', 'schedule');
?>

<div class="page-header">
    <h1 class="page-title">⏰ Cấu Hình Giờ Mở Cửa & Lịch Nghỉ</h1>
</div>

<?php if ($alert['msg']): ?>
    <div class="alert alert-<?= $alert['type'] ?>">
        <?= htmlspecialchars($alert['msg']) ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 24px; align-items: start;">
    <!-- Weekly Hours Form -->
    <div class="card">
        <h3 style="margin-bottom: 16px;">🗓️ Giờ mở cửa phòng khám theo tuần</h3>
        <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 18px;">
            Hệ thống sẽ chỉ tự động phân bổ khung giờ hẹn khi ngày đó đang được kích hoạt mở cửa.
        </p>
        <form method="POST" action="/admin/schedule.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="update_hours">

            <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 20px;">
                <?php foreach ([1, 2, 3, 4, 5, 6, 0] as $w): ?>
                    <?php
                        $cfg = $hoursByWeekday[$w] ?? ['active' => 1, 'start_time' => '08:00:00', 'end_time' => '19:30:00'];
                        $isOpen = !empty($cfg['active']);
                        $start = substr($cfg['start_time'], 0, 5);
                        $end = substr($cfg['end_time'], 0, 5);
                    ?>
                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: var(--bg); border-radius: 6px;">
                        <label style="display: flex; align-items: center; gap: 10px; width: 140px; font-weight: 600; cursor: pointer;">
                            <input type="checkbox" name="hours[<?= $w ?>][active]" value="1" <?= $isOpen ? 'checked' : '' ?>>
                            <span><?= $weekdayLabels[$w] ?></span>
                        </label>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <input type="time" name="hours[<?= $w ?>][start_time]" value="<?= $start ?>" style="padding: 4px 8px;">
                            <span>đến</span>
                            <input type="time" name="hours[<?= $w ?>][end_time]" value="<?= $end ?>" style="padding: 4px 8px;">
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">Lưu thay đổi giờ mở cửa</button>
        </form>
    </div>

    <!-- Schedule Blocks / Holidays -->
    <div>
        <!-- Add Block Form -->
        <div class="card" style="margin-bottom: 24px;">
            <h3 style="margin-bottom: 14px;">⛔ Khóa lịch / Ngày nghỉ lễ</h3>
            <form method="POST" action="/admin/schedule.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="add_block">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px;">Từ ngày</label>
                        <input type="date" name="start_date" required value="<?= date('Y-m-d') ?>" style="width: 100%;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px;">Giờ bắt đầu</label>
                        <input type="time" name="start_time" value="00:00" style="width: 100%;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px;">Đến ngày</label>
                        <input type="date" name="end_date" required value="<?= date('Y-m-d') ?>" style="width: 100%;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px;">Giờ kết thúc</label>
                        <input type="time" name="end_time" value="23:59" style="width: 100%;">
                    </div>
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px;">Lý do nghỉ / Khóa lịch</label>
                    <input type="text" name="reason" placeholder="Ví dụ: Nghỉ Tết Nguyên Đán, Bảo trì máy móc..." style="width: 100%;">
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">Thêm ngày nghỉ</button>
            </form>
        </div>

        <!-- Block List -->
        <div class="card" style="padding: 0; overflow: hidden;">
            <div style="padding: 14px 16px; border-bottom: 1px solid var(--border); font-weight: 600;">
                Danh sách lịch nghỉ đã tạo (<?= count($scheduleBlocks) ?>)
            </div>
            <div class="table-responsive" style="border: none; box-shadow: none;">
                <table>
                    <thead>
                        <tr>
                            <th>Thời gian nghỉ</th>
                            <th>Lý do</th>
                            <th>Xóa</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($scheduleBlocks)): ?>
                        <tr>
                            <td colspan="3" style="text-align: center; color: var(--text-muted); padding: 20px;">
                                Chưa có lịch nghỉ nào được tạo.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($scheduleBlocks as $blk): ?>
                            <tr>
                                <td style="font-size: 12.5px;">
                                    <div><strong><?= date('d/m/Y H:i', strtotime($blk['start_datetime'])) ?></strong></div>
                                    <div style="color: var(--text-muted);">đến <?= date('d/m/Y H:i', strtotime($blk['end_datetime'])) ?></div>
                                </td>
                                <td style="font-size: 12.5px;"><?= htmlspecialchars($blk['reason'] ?: 'Nghỉ phòng khám') ?></td>
                                <td>
                                    <form method="POST" action="/admin/schedule.php" onsubmit="return confirm('Bạn có chắc muốn xóa lịch nghỉ này?');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="action" value="delete_block">
                                        <input type="hidden" name="block_id" value="<?= $blk['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Xóa</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
renderAdminFooter();
