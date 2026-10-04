<?php
// admin/api_keys.php
// Quản lý khóa API Key & Tích hợp đăng bài tự động

require_once __DIR__ . '/../backend/auth/AdminAuth.php';
require_once __DIR__ . '/../backend/repositories/ApiKeyRepository.php';
require_once __DIR__ . '/../backend/security/Csrf.php';
require_once __DIR__ . '/../backend/security/Sanitizer.php';
require_once __DIR__ . '/layout.php';

AdminAuth::requireAuth();

$apiKeyRepo = new ApiKeyRepository();
$alert = ['type' => '', 'msg' => ''];
$newKeyRevealed = null;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!Csrf::verifyToken($token)) {
        $alert = ['type' => 'error', 'msg' => 'Phiên bảo mật CSRF đã hết hạn. Vui lòng thử lại.'];
    } else {
        $action = $_POST['action'] ?? '';

        // 1. Tạo khóa mới
        if ($action === 'create') {
            $name = Sanitizer::text($_POST['name'] ?? '');
            if (!$name) {
                $alert = ['type' => 'error', 'msg' => 'Vui lòng nhập tên ứng dụng hoặc dịch vụ sử dụng khóa API.'];
            } else {
                $plainKey = ApiKeyRepository::generateSecureKey();
                $id = $apiKeyRepo->create($name, $plainKey);
                $newKeyRevealed = [
                    'id'   => $id,
                    'name' => $name,
                    'key'  => $plainKey,
                ];
                $alert = ['type' => 'success', 'msg' => "Khóa API '{$name}' đã được khởi tạo thành công! Hãy sao chép ngay vì khóa sẽ không hiển thị lại sau khi rời trang."];
            }
        }

        // 2. Bật / Tắt khóa
        elseif ($action === 'toggle') {
            $id = (int)($_POST['id'] ?? 0);
            $active = (int)($_POST['active'] ?? 0) === 1;
            if ($id > 0) {
                $apiKeyRepo->toggleActive($id, $active);
                $statusText = $active ? 'kích hoạt' : 'vô hiệu hóa';
                $alert = ['type' => 'success', 'msg' => "Đã {$statusText} khóa API #{$id}."];
            }
        }

        // 3. Xóa khóa
        elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $apiKeyRepo->delete($id);
                $alert = ['type' => 'success', 'msg' => "Đã xóa vĩnh viễn khóa API #{$id}."];
            }
        }
    }
}

$keys = $apiKeyRepo->getAll();
$csrfToken = Csrf::generateToken();

renderAdminHeader('Quản Lý API Key & Tích Hợp Đăng Bài', 'api_keys');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">🔑 Quản Lý API Key &amp; Tự Động Hóa</h1>
        <div style="font-size: 13.5px; color: var(--text-muted); margin-top: 4px;">
            Quản lý cấp quyền, giám sát lần cuối sử dụng và tài liệu gọi API đăng bài viết chuyên khoa từ bên ngoài
        </div>
    </div>
    <a href="#createSection" class="btn btn-primary" onclick="document.getElementById('keyNameInput').focus();">
        ➕ Cấp Khóa API Mới
    </a>
</div>

<?php if ($alert['msg']): ?>
    <div class="alert alert-<?= $alert['type'] ?>">
        <?= $alert['type'] === 'success' ? '✅' : '⚠️' ?> <?= htmlspecialchars($alert['msg']) ?>
    </div>
<?php endif; ?>

<?php if ($newKeyRevealed): ?>
    <!-- Secret Key Reveal Banner (Shown only once) -->
    <div class="card" style="background: #FFFDF6; border: 2px solid #EABF0E; margin-bottom: 28px; box-shadow: 0 6px 20px rgba(234, 191, 14, 0.2);">
        <div style="display: flex; gap: 14px; align-items: flex-start;">
            <div style="font-size: 28px;">🔐</div>
            <div style="flex: 1;">
                <h3 style="color: #854d0e; font-size: 16px; font-weight: 800; margin-bottom: 6px;">
                    LƯU Ý QUAN TRỌNG: Hãy sao chép khóa bí mật của bạn ngay bây giờ
                </h3>
                <p style="font-size: 13.5px; color: #713f12; margin-bottom: 12px; line-height: 1.5;">
                    Để đảm bảo an toàn tuyệt đối, hệ thống chỉ lưu bản băm SHA-256 trong cơ sở dữ liệu. Khóa dạng thô dưới đây <strong>sẽ không bao giờ hiển thị lại</strong> sau khi bạn tải lại trang.
                </p>
                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <input type="text" id="revealedSecretKey" readonly value="<?= htmlspecialchars($newKeyRevealed['key']) ?>" style="font-family: monospace; font-size: 14px; font-weight: 700; color: #005A36; background: #fff; padding: 10px 14px; border: 1px solid #EABF0E; border-radius: 8px; flex: 1; min-width: 320px;">
                    <button type="button" class="btn btn-primary" onclick="navigator.clipboard.writeText(document.getElementById('revealedSecretKey').value); alert('Đã sao chép khóa API vào bộ nhớ tạm!');">
                        📋 Sao chép Khóa
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- LIST OF API KEYS -->
<div class="card" style="padding: 0; overflow: hidden; margin-bottom: 32px;">
    <div style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: #FFFDF6;">
        <div>
            <strong style="font-size: 15.5px; color: var(--primary);">📋 Danh Sách Khóa Đang Cấp Phép (<?= count($keys) ?>)</strong>
            <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">Trạng thái hoạt động, thời điểm gọi API gần nhất</div>
        </div>
    </div>

    <div class="table-responsive" style="border: none; border-radius: 0; box-shadow: none;">
        <table>
            <thead>
                <tr>
                    <th>Tên Định Danh Khóa</th>
                    <th>Mã Băm (SHA-256)</th>
                    <th>Trạng Thái</th>
                    <th>Lần Cuối Sử Dụng</th>
                    <th>Ngày Cấp</th>
                    <th style="text-align: right;">Thao Tác</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($keys)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 36px;">
                        Chưa có khóa API nào được tạo. Nhấn "Cấp Khóa API Mới" bên dưới để bắt đầu.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($keys as $k): ?>
                    <tr>
                        <td>
                            <strong style="font-size: 14px; color: #18181b;"><?= htmlspecialchars($k['name']) ?></strong>
                            <div style="font-size: 11.5px; color: var(--text-muted);">ID: #<?= (int)$k['id'] ?></div>
                        </td>
                        <td>
                            <span style="font-family: monospace; font-size: 12px; color: #64748b; background: #f4f4f5; padding: 3px 8px; border-radius: 4px;">
                                <?= substr($k['key_hash'], 0, 16) ?>...<?= substr($k['key_hash'], -8) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($k['active']): ?>
                                <span class="badge badge-published">● Đang hoạt động</span>
                            <?php else: ?>
                                <span class="badge badge-archived">✕ Đã vô hiệu hóa</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($k['last_used_at']): ?>
                                <div style="font-weight: 600; color: #18181b; font-size: 13px;">
                                    <?= date('H:i d/m/Y', strtotime($k['last_used_at'])) ?>
                                </div>
                                <div style="font-size: 11px; color: var(--primary);">Đang hoạt động tốt</div>
                            <?php else: ?>
                                <span style="color: var(--text-muted); font-size: 12.5px; font-style: italic;">Chưa sử dụng</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 12.5px; color: var(--text-muted);">
                            <?= date('d/m/Y', strtotime($k['created_at'])) ?>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <form method="POST" action="/admin/api_keys.php" style="display: inline-flex; gap: 6px; align-items: center;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="id" value="<?= $k['id'] ?>">

                                <?php if ($k['active']): ?>
                                    <button type="submit" name="action" value="toggle" class="btn btn-secondary btn-sm" style="color: #b45309;" title="Tạm dừng quyền truy cập của khóa này">
                                        ⏸ Vô hiệu hóa
                                    </button>
                                <?php else: ?>
                                    <input type="hidden" name="active" value="1">
                                    <button type="submit" name="action" value="toggle" class="btn btn-primary btn-sm" title="Kích hoạt lại khóa">
                                        ▶ Kích hoạt
                                    </button>
                                <?php endif; ?>

                                <button type="submit" name="action" value="delete" class="btn btn-danger btn-sm" onclick="return confirm('Bạn có chắc chắn muốn xóa vĩnh viễn khóa API này? Mọi ứng dụng đang dùng khóa sẽ bị từ chối truy cập.');" title="Xóa khóa">
                                    🗑️ Xóa
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

<!-- FORM: CREATE NEW KEY -->
<div class="card" id="createSection" style="margin-bottom: 28px; max-width: 760px; box-sizing: border-box;">
    <h3 style="font-size: 16px; font-weight: 700; color: var(--primary); margin-bottom: 12px; border-bottom: 1px solid var(--border); padding-bottom: 10px;">
        ➕ Tạo Khóa API Mới
    </h3>
    <form method="POST" action="/admin/api_keys.php" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="action" value="create">

        <div style="flex: 1; min-width: 260px;">
            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">
                Tên ứng dụng / Mục đích sử dụng *
            </label>
            <input type="text" id="keyNameInput" name="name" required placeholder="Ví dụ: N8N Tự Động Đăng Bài, Make.com, AI Writer" style="width: 100%; box-sizing: border-box;">
        </div>

        <button type="submit" class="btn btn-primary" style="padding: 10px 20px; font-weight: 700; white-space: nowrap;">
            Tạo &amp; Cấp Khóa Ngay
        </button>
    </form>
    <div style="font-size: 12px; color: var(--text-muted); margin-top: 8px;">
        Đặt tên gợi nhớ để phân biệt giữa các bên tích hợp hoặc công cụ tự động hóa.
    </div>
</div>

<!-- DOCUMENTATION & INTEGRATION GUIDE -->
<div class="card" style="width: 100%; min-width: 0; max-width: 100%; box-sizing: border-box; overflow: hidden; margin-bottom: 32px;">
    <h3 style="font-size: 16px; font-weight: 700; color: var(--primary); margin-bottom: 14px; border-bottom: 1px solid var(--border); padding-bottom: 10px;">
        📖 Tài Liệu Tích Hợp API Đăng Bài Chuyên Khoa (`/api/v1/posts`)
    </h3>

    <div style="font-size: 13.5px; line-height: 1.6; color: #27272a; width: 100%; min-width: 0; box-sizing: border-box;">
        <p style="margin-bottom: 12px;">
            <strong>Điểm cuối nhận bài (Endpoint):</strong>
            <code style="background: #f4f4f5; padding: 3px 8px; border-radius: 4px; color: #005A36; font-weight: 700;">POST https://nhakhoakimdung.vn/api/v1/posts</code>
        </p>
        <p style="margin-bottom: 14px;">
            <strong>Header bắt buộc:</strong>
            <br>• <code>Content-Type: application/json</code>
            <br>• <code>Authorization: Bearer &lt;YOUR_API_KEY&gt;</code>
        </p>

        <!-- SECTION 1: PAYLOAD JSON -->
        <h4 style="font-size: 14px; font-weight: 700; margin: 18px 0 8px; color: #18181b; display: flex; align-items: center; gap: 6px;">
            <span>1.</span> Cấu trúc Payload JSON Đầy Đủ (Có Bôi Đậm &amp; Hyperlink)
        </h4>
        <div style="font-size: 12.5px; color: var(--text-muted); margin-bottom: 6px;">
            Trường <code>content</code> nhận chuỗi HTML chuẩn đã được bọc trong nháy kép JSON (lưu ý escape dấu nháy kép bằng <code>\"</code>):
        </div>
        <pre style="background: #18181b; color: #f4f4f5; padding: 14px; border-radius: 8px; font-size: 12px; line-height: 1.55; white-space: pre-wrap; word-break: break-all; overflow-wrap: anywhere; max-width: 100%; box-sizing: border-box; overflow-x: auto;">{
  "title": "Bọc Răng Sứ Cercon HT Có Bền Không? Giá Bao Nhiêu?",
  "slug": "boc-rang-su-cercon-ht-co-ben-khong",
  "excerpt": "Đánh giá độ bền trên 15 năm và bảng giá phục hình răng sứ Cercon HT chính hãng tại Nha Khoa Kim Dung.",
  "content": "&lt;h2&gt;1. Răng sứ Cercon HT là gì?&lt;/h2&gt;\n&lt;p&gt;Cercon HT là dòng răng toàn sứ cao cấp. Tại Kim Dung, dịch vụ &lt;strong&gt;&lt;a href=\"/website/boc-rang-su.html\"&gt;bọc răng sứ thẩm mỹ&lt;/a&gt;&lt;/strong&gt; được thực hiện theo tiêu chuẩn không đau.&lt;/p&gt;\n\n&lt;h2&gt;2. So sánh độ chịu lực&lt;/h2&gt;\n&lt;p&gt;Khung sườn Zirconia chịu lực đến &lt;strong&gt;1200 MPa&lt;/strong&gt; (gấp 4 lần răng thật). Nếu mất răng toàn bộ, quý khách nên xem thêm &lt;a href=\"/website/trong-rang-implant.html\"&gt;&lt;strong&gt;cấy ghép Implant&lt;/strong&gt;&lt;/a&gt;.&lt;/p&gt;\n\n&lt;div style=\"background:#FFFDF6; border-left:4px solid #EABF0E; padding:14px 18px; margin:20px 0; border-radius:4px;\"&gt;\n  &lt;strong style=\"color:#005A36;\"&gt;💡 Lời khuyên từ Bác sĩ Kim Dung:&lt;/strong&gt;\n  &lt;p style=\"margin:6px 0 0 0;\"&gt;Nên kiêng thức ăn quá cứng trong 48h đầu. Quý khách có thể xem &lt;a href=\"/website/bang-gia.html\"&gt;bảng giá niêm yết tại đây&lt;/a&gt;.&lt;/p&gt;\n&lt;/div&gt;",
  "featured_image": "https://nhakhoakimdung.vn/upload/photo/banner-rang-su.jpg",
  "status": "published",
  "external_id": "post_ai_cercon_2026"
}</pre>

        <!-- SECTION 2: HYPERLINKS & BOLDING GUIDE -->
        <h4 style="font-size: 14px; font-weight: 700; margin: 20px 0 8px; color: #18181b; display: flex; align-items: center; gap: 6px;">
            <span>2.</span> Quy Chuẩn Chèn Hyperlink &amp; Bôi Đậm Từ Khóa
        </h4>
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px; margin-bottom: 14px; overflow-x: auto; max-width: 100%; box-sizing: border-box;">
            <table style="width: 100%; min-width: 500px; font-size: 13px; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #cbd5e1; text-align: left;">
                        <th style="padding: 8px;">Mục đích định dạng</th>
                        <th style="padding: 8px;">Cú pháp thẻ HTML gửi trong <code>content</code></th>
                        <th style="padding: 8px;">Hiển thị trên website</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <td style="padding: 8px;"><strong>Bôi đậm từ khóa</strong></td>
                        <td style="padding: 8px; font-family: monospace; color: #005A36;">&lt;strong&gt;trụ Implant Straumann&lt;/strong&gt;</td>
                        <td style="padding: 8px;"><strong>trụ Implant Straumann</strong></td>
                    </tr>
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <td style="padding: 8px;"><strong>Chèn Hyperlink nội bộ</strong><br><span style="font-size: 11.5px; color: var(--text-muted);">(Dẫn tới dịch vụ phòng khám)</span></td>
                        <td style="padding: 8px; font-family: monospace; color: #005A36;">&lt;a href="/website/boc-rang-su.html"&gt;bọc răng sứ thẩm mỹ&lt;/a&gt;</td>
                        <td style="padding: 8px;"><a href="/website/boc-rang-su.html" style="color: #005A36; text-decoration: underline;">bọc răng sứ thẩm mỹ</a></td>
                    </tr>
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <td style="padding: 8px;"><strong>Vừa bôi đậm vừa gắn link</strong></td>
                        <td style="padding: 8px; font-family: monospace; color: #005A36;">&lt;a href="/website/trong-rang-implant.html"&gt;&lt;strong&gt;cấy ghép Implant&lt;/strong&gt;&lt;/a&gt;</td>
                        <td style="padding: 8px;"><a href="/website/trong-rang-implant.html" style="color: #005A36; text-decoration: underline; font-weight: 700;">cấy ghép Implant</a></td>
                    </tr>
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <td style="padding: 8px;"><strong>Chèn Hyperlink ngoài</strong><br><span style="font-size: 11.5px; color: var(--text-muted);">(Mở tab mới an toàn)</span></td>
                        <td style="padding: 8px; font-family: monospace; color: #005A36;">&lt;a href="https://..." target="_blank" rel="noopener noreferrer"&gt;Nghiên cứu ADA&lt;/a&gt;</td>
                        <td style="padding: 8px;"><a href="#" style="color: #005A36; text-decoration: underline;">Nghiên cứu ADA</a></td>
                    </tr>
                    <tr>
                        <td style="padding: 8px;"><strong>Hộp lưu ý nổi bật</strong></td>
                        <td style="padding: 8px; font-family: monospace; color: #005A36;">&lt;div style="background:#FFFDF6; border-left:4px solid #EABF0E; padding:12px;"&gt;...&lt;/div&gt;</td>
                        <td style="padding: 8px;">Khối viền vàng thương hiệu Kim Dung</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- SECTION 3: DIRECTORY OF INTERNAL SERVICE URLS -->
        <h4 style="font-size: 14px; font-weight: 700; margin: 20px 0 8px; color: #18181b; display: flex; align-items: center; gap: 6px;">
            <span>3.</span> Danh Sách URL Dịch Vụ Chuẩn SEO (Dùng Để Gắn Link Nội Bộ)
        </h4>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 10px; font-size: 12.5px; background: #fff; border: 1px solid var(--border); padding: 12px; border-radius: 8px; margin-bottom: 16px; box-sizing: border-box;">
            <div>• Cấy ghép răng Implant: <code style="color: #005A36; font-weight: 600;">/website/trong-rang-implant.html</code></div>
            <div>• Bọc răng sứ, dán Veneer: <code style="color: #005A36; font-weight: 600;">/website/boc-rang-su.html</code></div>
            <div>• Niềng răng trong suốt: <code style="color: #005A36; font-weight: 600;">/website/nieng-rang-tham-my.html</code></div>
            <div>• Niềng răng mắc cài: <code style="color: #005A36; font-weight: 600;">/website/nieng-rang-mac-cai.html</code></div>
            <div>• Tẩy trắng răng Laser: <code style="color: #005A36; font-weight: 600;">/website/tay-trang-rang.html</code></div>
            <div>• Nha khoa tổng quát / trám: <code style="color: #005A36; font-weight: 600;">/website/nha-khoa-tong-quat.html</code></div>
            <div>• Bảng giá niêm yết: <code style="color: #005A36; font-weight: 600;">/website/bang-gia.html</code></div>
            <div>• Đội ngũ Bác sĩ: <code style="color: #005A36; font-weight: 600;">/website/bac-si.html</code></div>
            <div>• Trang Đặt lịch hẹn: <code style="color: #005A36; font-weight: 600;">/website/dat-lich.html</code></div>
            <div>• Trang chủ: <code style="color: #005A36; font-weight: 600;">/website/index.html</code></div>
        </div>

        <!-- SECTION 4: HANDLE & TEMPLATE AUTO-FILL BEHAVIOR -->
        <h4 style="font-size: 14px; font-weight: 700; margin: 20px 0 8px; color: #18181b; display: flex; align-items: center; gap: 6px;">
            <span>4.</span> Cơ Chế Xử Lý Handle (Slug) &amp; Điền Mẫu Tự Động
        </h4>
        <ul style="margin-left: 20px; font-size: 13px; color: #52525b; line-height: 1.6;">
            <li style="margin-bottom: 6px;">
                <strong>Handle / Slug đường dẫn:</strong> Nếu gửi trường <code>slug</code>, hệ thống gán chính xác slug đó (ví dụ <code>boc-rang-su-cercon</code> $\rightarrow$ <code>/tin-tuc/boc-rang-su-cercon</code>). Nếu <strong>bỏ trống slug</strong>, hệ thống tự động loại bỏ dấu tiếng Việt từ <code>title</code> để tạo slug chuẩn SEO.
            </li>
            <li style="margin-bottom: 6px;">
                <strong>Tự động điền khung mẫu Designer:</strong> Mọi bài viết gửi qua API sẽ được server tự động ghép vào layout chuẩn (Header nhận diện Kim Dung, thanh chuyên mục, nút chia sẻ Facebook/Zalo, và **Widget Đặt Lịch Khám Tư Vấn** cố định ở cột phải).
            </li>
            <li style="margin-bottom: 6px;">
                <strong>Bảo mật HTML (Sanitizer):</strong> Hệ thống cho phép 100% các thẻ định dạng nội dung an toàn (<code>&lt;a&gt;</code>, <code>&lt;strong&gt;</code>, <code>&lt;em&gt;</code>, <code>&lt;h2&gt;</code>, <code>&lt;h3&gt;</code>, <code>&lt;ul&gt;</code>, <code>&lt;li&gt;</code>, <code>&lt;table&gt;</code>, <code>&lt;img&gt;</code>, <code>&lt;blockquote&gt;</code>) và tự động lọc bỏ các mã độc hại (<code>&lt;script&gt;</code>, <code>&lt;iframe&gt;</code>, inline JS event handlers).
            </li>
            <li style="margin-bottom: 6px;">
                <strong>Tự động Cập nhật (Upsert):</strong> Gửi kèm <code>external_id</code> giúp tool tự động cập nhật lại bài viết đã đăng khi có nội dung mới thay vì tạo bài rác trùng lặp.
            </li>
        </ul>

        <!-- SECTION 5: CURL EXAMPLE -->
        <h4 style="font-size: 14px; font-weight: 700; margin: 20px 0 8px; color: #18181b; display: flex; align-items: center; gap: 6px;">
            <span>5.</span> Lệnh Mẫu cURL Gọi Thử
        </h4>
        <pre style="background: #18181b; color: #f4f4f5; padding: 14px; border-radius: 8px; font-size: 12px; line-height: 1.55; white-space: pre-wrap; word-break: break-all; overflow-wrap: anywhere; max-width: 100%; box-sizing: border-box; overflow-x: auto;">curl -X POST https://nhakhoakimdung.vn/api/v1/posts \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_API_KEY_HERE" \
  -d '{
    "title": "Bọc Răng Sứ Thẩm Mỹ Có Đau Không?",
    "content": "&lt;p&gt;Nha Khoa Kim Dung cung cấp dịch vụ &lt;strong&gt;&lt;a href=\"/website/boc-rang-su.html\"&gt;bọc răng sứ cao cấp&lt;/a&gt;&lt;/strong&gt; bảo hành chính hãng &lt;strong&gt;10 năm&lt;/strong&gt;. Quý khách xem thêm &lt;a href=\"/website/bang-gia.html\"&gt;bảng giá&lt;/a&gt; tại đây.&lt;/p&gt;",
    "status": "published"
  }'</pre>
    </div>
</div>

<?php
renderAdminFooter();
?>