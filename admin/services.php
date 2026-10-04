<?php
// admin/services.php
// Quản lý danh mục dịch vụ khám & giá

require_once __DIR__ . '/../backend/auth/AdminAuth.php';
require_once __DIR__ . '/../backend/repositories/ServiceRepository.php';
require_once __DIR__ . '/../backend/security/Sanitizer.php';
require_once __DIR__ . '/../backend/security/Csrf.php';
require_once __DIR__ . '/layout.php';

AdminAuth::requireAuth();

$serviceRepo = new ServiceRepository();
$alert = ['type' => '', 'msg' => ''];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!Csrf::verifyToken($token)) {
        $alert = ['type' => 'error', 'msg' => 'Phiên bảo mật CSRF không hợp lệ.'];
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $name = Sanitizer::text($_POST['name'] ?? '');
            $slug = Sanitizer::slug($_POST['slug'] ?: $name);
            $duration = max(10, (int)($_POST['duration_minutes'] ?? 30));
            $price = max(0, (float)($_POST['price'] ?? 0));
            $sortOrder = (int)($_POST['sort_order'] ?? 0);
            $active = isset($_POST['active']) ? 1 : 0;
            $description = Sanitizer::text($_POST['description'] ?? '');

            if (!$name) {
                $alert = ['type' => 'error', 'msg' => 'Tên dịch vụ không được để trống.'];
            } else {
                $serviceRepo->create([
                    'name'             => $name,
                    'slug'             => $slug,
                    'duration_minutes' => $duration,
                    'price'            => $price,
                    'sort_order'       => $sortOrder,
                    'active'           => $active,
                    'description'      => $description,
                ]);
                $alert = ['type' => 'success', 'msg' => "Đã thêm dịch vụ '{$name}' thành công."];
            }
        } elseif ($action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $name = Sanitizer::text($_POST['name'] ?? '');
            $slug = Sanitizer::slug($_POST['slug'] ?: $name);
            $duration = max(10, (int)($_POST['duration_minutes'] ?? 30));
            $price = max(0, (float)($_POST['price'] ?? 0));
            $sortOrder = (int)($_POST['sort_order'] ?? 0);
            $active = isset($_POST['active']) ? 1 : 0;
            $description = Sanitizer::text($_POST['description'] ?? '');

            if ($id > 0 && $name) {
                $serviceRepo->update($id, [
                    'name'             => $name,
                    'slug'             => $slug,
                    'duration_minutes' => $duration,
                    'price'            => $price,
                    'sort_order'       => $sortOrder,
                    'active'           => $active,
                    'description'      => $description,
                ]);
                $alert = ['type' => 'success', 'msg' => "Đã cập nhật dịch vụ #{$id} ({$name})."];
            }
        } elseif ($action === 'toggle_active') {
            $id = (int)($_POST['id'] ?? 0);
            $current = (int)($_POST['current_active'] ?? 0);
            if ($id > 0) {
                $serviceRepo->update($id, ['active' => $current ? 0 : 1]);
                $alert = ['type' => 'success', 'msg' => "Đã thay đổi trạng thái hoạt động của dịch vụ #{$id}."];
            }
        }
    }
}

$services = $serviceRepo->getAllServices();
$csrfToken = Csrf::generateToken();

// Edit Mode check
$editService = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editService = $serviceRepo->findById($editId);
}

renderAdminHeader('Quản lý Dịch vụ Khám', 'services');
?>

<div class="page-header">
    <h1 class="page-title">🩺 Danh Mục Dịch Vụ Khám (<?= count($services) ?>)</h1>
</div>

<?php if ($alert['msg']): ?>
    <div class="alert alert-<?= $alert['type'] ?>">
        <?= htmlspecialchars($alert['msg']) ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; align-items: start;">
    <!-- Service List Table -->
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>STT</th>
                    <th>Tên dịch vụ / Slug</th>
                    <th>Thời lượng</th>
                    <th>Giá tham khảo</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($services as $s): ?>
                <tr>
                    <td><?= $s['sort_order'] ?></td>
                    <td>
                        <strong><?= htmlspecialchars($s['name']) ?></strong>
                        <div style="font-size: 11.5px; color: var(--text-muted); font-family: monospace;"><?= htmlspecialchars($s['slug']) ?></div>
                    </td>
                    <td><?= $s['duration_minutes'] ?> phút</td>
                    <td><?= number_format($s['price'], 0, ',', '.') ?> đ</td>
                    <td>
                        <form method="POST" action="/admin/services.php" style="display: inline;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="action" value="toggle_active">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <input type="hidden" name="current_active" value="<?= $s['active'] ?>">
                            <button type="submit" class="badge <?= $s['active'] ? 'badge-confirmed' : 'badge-no_show' ?>" style="border: none; cursor: pointer;">
                                <?= $s['active'] ? 'Hoạt động' : 'Tạm ẩn' ?>
                            </button>
                        </form>
                    </td>
                    <td>
                        <a href="/admin/services.php?edit=<?= $s['id'] ?>" class="btn btn-secondary btn-sm">Sửa</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Create or Edit Form -->
    <div class="card">
        <h3 style="margin-bottom: 16px;">
            <?= $editService ? '✏️ Cập nhật dịch vụ #' . $editService['id'] : '➕ Thêm dịch vụ mới' ?>
        </h3>
        <form method="POST" action="/admin/services.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="<?= $editService ? 'update' : 'create' ?>">
            <?php if ($editService): ?>
                <input type="hidden" name="id" value="<?= $editService['id'] ?>">
            <?php endif; ?>

            <div style="margin-bottom: 12px;">
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Tên dịch vụ *</label>
                <input type="text" name="name" required style="width: 100%;" value="<?= htmlspecialchars($editService['name'] ?? '') ?>" placeholder="Ví dụ: Cạo vôi răng">
            </div>

            <div style="margin-bottom: 12px;">
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Đường dẫn (Slug)</label>
                <input type="text" name="slug" style="width: 100%; font-family: monospace;" value="<?= htmlspecialchars($editService['slug'] ?? '') ?>" placeholder="Tự sinh nếu để trống">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Thời lượng (phút)</label>
                    <input type="number" name="duration_minutes" step="10" min="10" required style="width: 100%;" value="<?= (int)($editService['duration_minutes'] ?? 30) ?>">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Giá (VNĐ)</label>
                    <input type="number" name="price" step="10000" min="0" style="width: 100%;" value="<?= (float)($editService['price'] ?? 0) ?>">
                </div>
            </div>

            <div style="margin-bottom: 12px;">
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Thứ tự hiển thị</label>
                <input type="number" name="sort_order" style="width: 100%;" value="<?= (int)($editService['sort_order'] ?? 0) ?>">
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px;">Mô tả ngắn</label>
                <textarea name="description" rows="3" style="width: 100%;"><?= htmlspecialchars($editService['description'] ?? '') ?></textarea>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                    <input type="checkbox" name="active" value="1" <?= (!$editService || !empty($editService['active'])) ? 'checked' : '' ?> style="width: auto;">
                    <span>Hiển thị trên form đặt lịch</span>
                </label>
            </div>

            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">
                    <?= $editService ? 'Lưu thay đổi' : 'Thêm mới' ?>
                </button>
                <?php if ($editService): ?>
                    <a href="/admin/services.php" class="btn btn-secondary">Hủy</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php
renderAdminFooter();
