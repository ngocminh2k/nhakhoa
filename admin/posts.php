<?php
// admin/posts.php
// Quản lý tin tức & bài viết chuyên môn

require_once __DIR__ . '/../backend/auth/AdminAuth.php';
require_once __DIR__ . '/../backend/repositories/PostRepository.php';
require_once __DIR__ . '/../backend/security/Sanitizer.php';
require_once __DIR__ . '/../backend/security/Csrf.php';
require_once __DIR__ . '/layout.php';

AdminAuth::requireAuth();

$postRepo = new PostRepository();
$alert = ['type' => '', 'msg' => ''];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!Csrf::verifyToken($token)) {
        $alert = ['type' => 'error', 'msg' => 'Phiên bảo mật CSRF không hợp lệ.'];
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create' || $action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $title = Sanitizer::text($_POST['title'] ?? '');
            $slug = Sanitizer::slug($_POST['slug'] ?: $title);
            $excerpt = Sanitizer::text($_POST['excerpt'] ?? '');
            $content = Sanitizer::cleanHtml($_POST['content'] ?? '');
            $image = Sanitizer::text($_POST['featured_image'] ?? '');
            $status = in_array($_POST['status'] ?? '', ['published', 'draft', 'archived']) ? $_POST['status'] : 'draft';

            if (!$title || !$content) {
                $alert = ['type' => 'error', 'msg' => 'Tiêu đề và nội dung bài viết không được để trống.'];
            } else {
                if ($action === 'create') {
                    $postRepo->upsert([
                        'title'          => $title,
                        'slug'           => $slug,
                        'excerpt'        => $excerpt,
                        'content'        => $content,
                        'featured_image' => $image,
                        'status'         => $status,
                    ]);
                    $alert = ['type' => 'success', 'msg' => "Đã tạo bài viết '{$title}' thành công."];
                } else {
                    $postRepo->update($id, [
                        'title'          => $title,
                        'slug'           => $slug,
                        'excerpt'        => $excerpt,
                        'content'        => $content,
                        'featured_image' => $image,
                        'status'         => $status,
                    ]);
                    $alert = ['type' => 'success', 'msg' => "Đã cập nhật bài viết #{$id}."];
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $postRepo->delete($id);
                $alert = ['type' => 'success', 'msg' => "Đã xóa bài viết #{$id}."];
            }
        } elseif ($action === 'toggle_status') {
            $id = (int)($_POST['id'] ?? 0);
            $newStatus = $_POST['status'] ?? 'draft';
            if ($id > 0 && in_array($newStatus, ['published', 'draft', 'archived'], true)) {
                $postRepo->update($id, ['status' => $newStatus]);
                $alert = ['type' => 'success', 'msg' => "Đã chuyển trạng thái bài viết #{$id} sang '{$newStatus}'."];
            }
        }
    }
}

$editPost = null;
if (isset($_GET['edit'])) {
    $editPost = $postRepo->findById((int)$_GET['edit']);
}

$isCreating = isset($_GET['new']);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$totalPosts = $postRepo->countAll();
$totalPages = max(1, (int)ceil($totalPosts / $perPage));
if ($page > $totalPages && $totalPosts > 0) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$posts = $postRepo->getAllPosts($perPage, $offset);
$csrfToken = Csrf::generateToken();

renderAdminHeader('Quản lý Tin Tức & Bài Viết', 'posts');
?>

<div class="page-header">
    <h1 class="page-title">📝 Quản Lý Tin Tức & Kiến Thức (<?= $totalPosts ?> bài viết)</h1>
    <?php if (!$isCreating && !$editPost): ?>
        <a href="/admin/posts.php?new=1" class="btn btn-primary">➕ Viết bài mới</a>
    <?php else: ?>
        <a href="/admin/posts.php" class="btn btn-secondary">← Danh sách bài viết</a>
    <?php endif; ?>
</div>

<?php if ($alert['msg']): ?>
    <div class="alert alert-<?= $alert['type'] ?>">
        <?= htmlspecialchars($alert['msg']) ?>
    </div>
<?php endif; ?>

<?php if ($isCreating || $editPost): ?>
    <!-- Editor Form -->
    <div class="card" style="max-width: 900px; margin: 0 auto;">
        <h3 style="margin-bottom: 20px;">
            <?= $editPost ? '✏️ Chỉnh sửa bài viết #' . $editPost['id'] : '➕ Thêm bài viết mới' ?>
        </h3>
        <form method="POST" action="/admin/posts.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="<?= $editPost ? 'update' : 'create' ?>">
            <?php if ($editPost): ?>
                <input type="hidden" name="id" value="<?= $editPost['id'] ?>">
            <?php endif; ?>

            <div style="margin-bottom: 16px;">
                <label style="display: block; font-weight: 600; margin-bottom: 6px;">Tiêu đề bài viết *</label>
                <input type="text" name="title" required value="<?= htmlspecialchars($editPost['title'] ?? '') ?>" style="width: 100%; font-size: 15px; font-weight: 600;">
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Slug đường dẫn</label>
                    <input type="text" name="slug" value="<?= htmlspecialchars($editPost['slug'] ?? '') ?>" placeholder="Tự sinh từ tiêu đề nếu trống" style="width: 100%; font-family: monospace;">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Trạng thái</label>
                    <select name="status" style="width: 100%;">
                        <option value="published" <?= ($editPost['status'] ?? 'published') === 'published' ? 'selected' : '' ?>>Đã xuất bản (Published)</option>
                        <option value="draft" <?= ($editPost['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Bản nháp (Draft)</option>
                        <option value="archived" <?= ($editPost['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Lưu trữ (Archived)</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Ảnh đại diện URL (Featured Image)</label>
                <input type="url" name="featured_image" value="<?= htmlspecialchars($editPost['featured_image'] ?? '') ?>" placeholder="https://..." style="width: 100%;">
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Tóm tắt ngắn (Excerpt)</label>
                <textarea name="excerpt" rows="2" style="width: 100%;"><?= htmlspecialchars($editPost['excerpt'] ?? '') ?></textarea>
            </div>

            <div style="margin-bottom: 24px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <label style="font-size: 13px; font-weight: 600;">Nội dung bài viết (HTML / Text) *</label>
                    <span style="font-size: 12px; color: var(--text-muted);">Bôi đen từ khóa rồi bấm nút bên dưới để định dạng nhanh</span>
                </div>

                <!-- Quick Format Toolbar -->
                <div style="display: flex; gap: 6px; margin-bottom: 8px; flex-wrap: wrap; background: #f8fafc; padding: 6px; border: 1px solid #e2e8f0; border-radius: 6px;">
                    <button type="button" class="btn btn-secondary btn-sm" style="font-weight: 700;" onclick="wrapFormat('<strong>', '</strong>')" title="Bôi đậm từ khóa">
                        <b>B</b> Bôi đậm
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" style="font-style: italic;" onclick="wrapFormat('<em>', '</em>')" title="In nghiêng">
                        <i>I</i> Nghiêng
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="insertHyperlink()" title="Chèn liên kết vào từ khóa">
                        🔗 Chèn Hyperlink
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="wrapFormat('<h2>', '</h2>')" title="Tiêu đề mục lớn">
                        H2
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="wrapFormat('<h3>', '</h3>')" title="Tiêu đề mục nhỏ">
                        H3
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="wrapFormat('<ul>\n  <li>', '</li>\n</ul>')" title="Danh sách gạch đầu dòng">
                        • Danh sách
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="insertCalloutBox()" title="Hộp lưu ý nổi bật chuẩn Kim Dung">
                        💡 Hộp lưu ý bác sĩ
                    </button>
                </div>

                <textarea name="content" id="postContent" rows="16" required style="width: 100%; font-family: monospace; font-size: 13px; line-height: 1.6; border: 1px solid #d4d4d8; border-radius: 8px; padding: 12px;"><?= htmlspecialchars($editPost['content'] ?? '') ?></textarea>
            </div>

            <script>
            function wrapFormat(before, after) {
                const el = document.getElementById('postContent');
                const start = el.selectionStart;
                const end = el.selectionEnd;
                const text = el.value;
                const selected = text.substring(start, end) || 'từ khóa';
                el.value = text.substring(0, start) + before + selected + after + text.substring(end);
                el.focus();
                el.setSelectionRange(start + before.length, start + before.length + selected.length);
            }

            function insertHyperlink() {
                const el = document.getElementById('postContent');
                const start = el.selectionStart;
                const end = el.selectionEnd;
                const text = el.value;
                const selected = text.substring(start, end) || 'xem chi tiết';
                const url = prompt('Nhập địa chỉ URL cần gắn vào từ khóa:\n(VD link nội bộ: /website/boc-rang-su.html hoặc https://...)', '/website/');
                if (url && url.trim() !== '') {
                    const cleanUrl = url.trim();
                    const linkHtml = '<a href="' + cleanUrl + '">' + selected + '</a>';
                    el.value = text.substring(0, start) + linkHtml + text.substring(end);
                    el.focus();
                    el.setSelectionRange(start, start + linkHtml.length);
                }
            }

            function insertCalloutBox() {
                const note = prompt('Nội dung lưu ý quan trọng:', 'Khách hàng nên tái khám định kỳ 6 tháng/lần để bảo vệ răng sứ tối ưu.');
                if (note) {
                    const html = '\n<div style="background:#FFFDF6; border-left:4px solid #EABF0E; padding:14px 18px; margin:20px 0; border-radius:4px; color:#18181b;">\n  <strong style="color:#005A36;">💡 Lời khuyên từ Bác sĩ Kim Dung:</strong>\n  <p style="margin:6px 0 0 0;">' + note + '</p>\n</div>\n';
                    const el = document.getElementById('postContent');
                    const start = el.selectionStart;
                    const end = el.selectionEnd;
                    el.value = el.value.substring(0, start) + html + el.value.substring(end);
                    el.focus();
                }
            }
            </script>

            <div style="display: flex; gap: 12px;">
                <button type="submit" class="btn btn-primary" style="padding: 10px 24px;">
                    <?= $editPost ? 'Lưu cập nhật' : 'Xuất bản bài viết' ?>
                </button>
                <a href="/admin/posts.php" class="btn btn-secondary">Hủy bỏ</a>
            </div>
        </form>
    </div>
<?php else: ?>
    <!-- Post List Table -->
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tiêu đề / Slug</th>
                    <th>Nguồn</th>
                    <th>Trạng thái</th>
                    <th>Ngày đăng</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($posts)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 32px;">
                        Chưa có bài viết nào. Bạn có thể thêm bài mới hoặc đồng bộ tự động qua API.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($posts as $p): ?>
                    <tr>
                        <td>#<?= $p['id'] ?></td>
                        <td>
                            <div><strong><?= htmlspecialchars($p['title']) ?></strong></div>
                            <div style="font-size: 11.5px; color: var(--text-muted); font-family: monospace;">
                                <a href="/tin-tuc/<?= htmlspecialchars($p['slug']) ?>" target="_blank" style="color: inherit; text-decoration: none;">
                                    /tin-tuc/<?= htmlspecialchars($p['slug']) ?> ↗
                                </a>
                            </div>
                        </td>
                        <td style="font-size: 12px; color: var(--text-muted);">
                            <?= $p['external_id'] ? 'API / Auto' : 'Thủ công' ?>
                        </td>
                        <td>
                            <form method="POST" action="/admin/posts.php" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <select name="status" onchange="this.form.submit()" style="padding: 2px 6px; font-size: 11px; font-weight: 600;">
                                    <option value="published" <?= $p['status'] === 'published' ? 'selected' : '' ?>>Xuất bản</option>
                                    <option value="draft" <?= $p['status'] === 'draft' ? 'selected' : '' ?>>Nháp</option>
                                    <option value="archived" <?= $p['status'] === 'archived' ? 'selected' : '' ?>>Lưu trữ</option>
                                </select>
                            </form>
                        </td>
                        <td style="font-size: 12px; color: var(--text-muted);">
                            <?= $p['published_at'] ? date('d/m/Y', strtotime($p['published_at'])) : date('d/m/Y', strtotime($p['created_at'])) ?>
                        </td>
                        <td>
                            <div style="display: flex; gap: 6px;">
                                <a href="/admin/posts.php?edit=<?= $p['id'] ?>" class="btn btn-secondary btn-sm">Sửa</a>
                                <form method="POST" action="/admin/posts.php" onsubmit="return confirm('Bạn có chắc muốn xóa vĩnh viễn bài viết này?');" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Xóa</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; flex-wrap: wrap; gap: 12px;">
            <div style="font-size: 13px; color: var(--text-muted);">
                Hiển thị bài viết <strong><?= $offset + 1 ?> - <?= min($offset + $perPage, $totalPosts) ?></strong> trên tổng số <strong><?= $totalPosts ?></strong> bài (Trang <strong><?= $page ?></strong> / <strong><?= $totalPages ?></strong>)
            </div>
            <div style="display: flex; gap: 6px; align-items: center;">
                <?php if ($page > 1): ?>
                    <a href="/admin/posts.php?page=<?= $page - 1 ?>" class="btn btn-secondary btn-sm">‹ Trước</a>
                <?php else: ?>
                    <span class="btn btn-secondary btn-sm" style="opacity: 0.5; cursor: not-allowed;">‹ Trước</span>
                <?php endif; ?>

                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                if ($startPage > 1): ?>
                    <a href="/admin/posts.php?page=1" class="btn btn-secondary btn-sm">1</a>
                    <?php if ($startPage > 2): ?><span style="padding: 0 4px; color: var(--text-muted);">...</span><?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <a href="/admin/posts.php?page=<?= $i ?>" class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-secondary' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?><span style="padding: 0 4px; color: var(--text-muted);">...</span><?php endif; ?>
                    <a href="/admin/posts.php?page=<?= $totalPages ?>" class="btn btn-secondary btn-sm"><?= $totalPages ?></a>
                <?php endif; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="/admin/posts.php?page=<?= $page + 1 ?>" class="btn btn-secondary btn-sm">Sau ›</a>
                <?php else: ?>
                    <span class="btn btn-secondary btn-sm" style="opacity: 0.5; cursor: not-allowed;">Sau ›</span>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php
renderAdminFooter();
