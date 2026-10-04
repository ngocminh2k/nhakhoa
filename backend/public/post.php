<?php
// backend/public/post.php
// Public single post reader for Nha Khoa Kim Dung
// Uses the Frontend Designer's exact HTML layout & Greenfield Design System

require_once __DIR__ . '/../repositories/PostRepository.php';

$slug = trim($_GET['slug'] ?? '');
if (!$slug) {
    header('Location: /website/tin-tuc.html');
    exit;
}

$postRepo = new PostRepository();
$post = $postRepo->findBySlug($slug);

if (!$post || $post['status'] !== 'published') {
    http_response_code(404);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Không tìm thấy bài viết | Nha Khoa Kim Dung</title>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/website/assets/css/greenfield-theme.css">
    <style>
        body { font-family: 'Be Vietnam Pro', sans-serif; text-align: center; padding: 80px 20px; background: #FAF8F5; color: #18181b; }
        .error-card { max-width: 520px; margin: 0 auto; background: #fff; padding: 48px 32px; border-radius: 16px; border: 1px solid rgba(234, 191, 14, 0.3); box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05); }
        .error-badge { display: inline-block; background: #FFFDF6; border: 1px solid #EABF0E; color: #B89307; padding: 6px 16px; border-radius: 99px; font-weight: 700; font-size: 14px; margin-bottom: 16px; }
        h1 { font-size: 28px; font-weight: 800; color: #005A36; margin-bottom: 12px; }
        p { color: #64748b; font-size: 15px; line-height: 1.6; margin-bottom: 24px; }
        .btn-home { display: inline-block; background: #005A36; color: #fff; padding: 12px 28px; border-radius: 99px; text-decoration: none; font-weight: 700; font-size: 14px; transition: opacity 0.2s; }
        .btn-home:hover { opacity: 0.9; }
    </style>
</head>
<body>
    <div class="error-card">
        <span class="error-badge">MÃ LỖI 404</span>
        <h1>Bài Viết Không Tồn Tại</h1>
        <p>Bài viết bạn đang tìm kiếm không tồn tại hoặc đã được chuyển sang chế độ lưu trữ.</p>
        <a href="/website/tin-tuc.html" class="btn-home">← Quay lại mục Tin tức</a>
    </div>
</body>
</html>
<?php
    exit;
}

$title = htmlspecialchars($post['title']);
$excerpt = htmlspecialchars($post['excerpt'] ?? '');
$publishedDate = date('d/m/Y', strtotime($post['published_at'] ?? $post['created_at']));
$content = $post['content']; // Sanitized at API ingestion
$image = !empty($post['featured_image']) ? htmlspecialchars($post['featured_image']) : '';
$currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/tin-tuc/' . htmlspecialchars($post['slug']);

// Get related / recent articles for sidebar
$recentPosts = $postRepo->getPublishedPosts(5);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?> | Nha Khoa Kim Dung Thái Nguyên</title>
    <meta name="description" content="<?= $excerpt ?: $title ?>">
    <meta name="keywords" content="nha khoa Thái Nguyên, nha khoa Kim Dung, <?= $title ?>">
    <meta name="robots" content="index,follow">
    <link rel="icon" type="image/x-icon" href="https://nhakhoakimdung.vn/upload/photo/url-1-1753346947.webp">
    <link rel="canonical" href="<?= $currentUrl ?>">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="article">
    <meta property="og:site_name" content="NHA KHOA KIM DUNG">
    <meta property="og:title" content="<?= $title ?>">
    <meta property="og:description" content="<?= $excerpt ?: $title ?>">
    <meta property="og:url" content="<?= $currentUrl ?>">
    <?php if ($image): ?>
        <meta property="og:image" content="<?= $image ?>">
    <?php else: ?>
        <meta property="og:image" content="https://nhakhoakimdung.vn/thumbs/600x400x1/upload/seopage/bannerseo-1753694289.png.webp">
    <?php endif; ?>

    <!-- Font Family: Be Vietnam Pro -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,400;1,600;1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">

    <!-- Project Stylesheets -->
    <link href="/website/assets/css/animate.min.css" rel="stylesheet">
    <link href="/website/assets/css/style-tailwind.css" rel="stylesheet">
    <link href="/website/assets/bootstrap/bootstrap.css" rel="stylesheet">
    <link href="/website/assets/fontawesome640/all.css" rel="stylesheet">
    <link href="/website/assets/css/fonts.css?v=3.0" rel="stylesheet">
    <link href="/website/assets/css/style.css" rel="stylesheet">
    <link href="/website/assets/css/media.css" rel="stylesheet">
    <link href="/website/assets/css/ui-ux-pro-max.css?v=3.0" rel="stylesheet">
    <link href="/website/assets/css/greenfield-theme.css" rel="stylesheet">

    <style>
        .gf-article-meta-row {
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 13.5px;
            color: #64748b;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid #f1f5f9;
        }
        .gf-article-meta-row i {
            color: #EABF0E;
            margin-right: 4px;
        }
        .gf-article-body {
            font-family: 'Be Vietnam Pro', sans-serif !important;
            font-size: 1rem !important;
            line-height: 1.85 !important;
            color: #27272a !important;
        }
        .gf-article-body h2 {
            font-size: 1.45rem !important;
            font-weight: 700 !important;
            color: #005A36 !important;
            margin: 32px 0 14px !important;
            padding-bottom: 8px;
            border-bottom: 2px solid rgba(234, 191, 14, 0.3);
        }
        .gf-article-body h3 {
            font-size: 1.25rem !important;
            font-weight: 700 !important;
            color: #18181b !important;
            margin: 24px 0 10px !important;
        }
        .gf-article-body p {
            margin-bottom: 18px !important;
        }
        .gf-article-body ul, .gf-article-body ol {
            margin: 0 0 20px 24px !important;
        }
        .gf-article-body li {
            margin-bottom: 6px !important;
        }
        .gf-article-body a {
            color: #005A36 !important;
            font-weight: 600 !important;
            text-decoration: underline !important;
            text-underline-offset: 3px;
            transition: color 0.15s ease;
        }
        .gf-article-body a:hover {
            color: #b45309 !important;
        }
        .gf-article-body strong, .gf-article-body b {
            font-weight: 700 !important;
            color: #18181b !important;
        }
        .gf-article-body img {
            max-width: 100% !important;
            height: auto !important;
            border-radius: 10px;
            margin: 16px 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        }
    </style>
</head>
<body>
<header>
 <div class="header z-100">
 <div class="head-bottom">
 <div class="wrap-content">
 <div class="logo-banner">
 <div class="logo">
 <a class="logo-head peShine bgstart-animate" href="/website/index.html">
 <img src="https://nhakhoakimdung.vn/upload/photo/url-1-1753346899.webp" alt="NHA KHOA KIM DUNG">
 <img class="start-animate" src="https://nhakhoakimdung.vn/assets/images/saolaplanh.webp" alt="">
 <img class="start-animate1" src="https://nhakhoakimdung.vn/assets/images/saolaplanh.webp" alt="">
 <img class="start-animate2" src="https://nhakhoakimdung.vn/assets/images/saolaplanh.webp" alt="">
 </a>
 </div>
 <div class="banner">
 <a class="banner-head" href="/website/index.html">
 <img src="https://nhakhoakimdung.vn/upload/photo/nha-khoa-kim-dung-1753686177.png" alt="NHA KHOA KIM DUNG">
 </a>
 </div>
 </div>
 <div class="content-menu">
  <!-- Top Gold Ribbon: Hotline + Đặt Lịch + Địa Chỉ + Quốc Gia -->
  <div class="v1 header-top-gold-bar">
    <div class="item-hotline">
      <div class="img-hotline"><i class="fa-solid fa-phone"></i></div>
      <div class="hotline-text-wrap">
        <p class="mb-0 hotline-lbl">Hotline 24/7</p>
        <a href="tel:0862960886" class="hotline-num">0862 960 886</a>
      </div>
    </div>
    <a href="/website/dat-lich.html" class="header-cta-booking-btn">
      <span>Đặt lịch hẹn</span>
    </a>
    <div class="info-head">
      <i class="fa-solid fa-location-dot"></i>
      <span>Số 15, đường Bắc Sơn kéo dài, P. Quang Trung, TP. Thái Nguyên</span>
    </div>
    <div class="header-lang-badge">
      <img src="https://flagcdn.com/24x18/vn.png" alt="VN" class="lang-flag" width="20" height="14">
      <span>vn <i class="fa-solid fa-angle-down"></i></span>
    </div>
  </div>

  <!-- Bottom Navigation Row -->
  <nav class="v2 header-nav-row" aria-label="Menu chính">
    <ul class="header-nav-list">
      <li><a class="transition" href="/website/gioi-thieu.html" title="Giới thiệu">GIỚI THIỆU</a></li>
      <li class="has-dropdown"><a class="transition" href="/website/dich-vu.html" title="Dịch vụ">DỊCH VỤ <i class="fa-solid fa-chevron-down dropdown-chevron"></i></a>
        <div class="dropdown-menu-service">
          <a href="/website/trong-rang-implant.html" class="dropdown-service-item">
            <span class="dropdown-service-title">Implant</span>
            <span class="dropdown-service-sub">Cấy ghép răng implant</span>
          </a>
          <a href="/website/boc-rang-su.html" class="dropdown-service-item">
            <span class="dropdown-service-title">Răng sứ</span>
            <span class="dropdown-service-sub">Bọc răng sứ, mặt dán sứ</span>
          </a>
          <a href="/website/nieng-rang-tham-my.html" class="dropdown-service-item">
            <span class="dropdown-service-title">Invisalign</span>
            <span class="dropdown-service-sub">Niềng răng máng trong suốt</span>
          </a>
          <a href="/website/nieng-rang-mac-cai.html" class="dropdown-service-item">
            <span class="dropdown-service-title">Niềng răng mắc cài</span>
            <span class="dropdown-service-sub">Niềng răng kim loại &amp; sứ</span>
          </a>
          <a href="/website/tay-trang-rang.html" class="dropdown-service-item">
            <span class="dropdown-service-title">Tẩy trắng</span>
            <span class="dropdown-service-sub">Tẩy trắng răng an toàn</span>
          </a>
          <a href="/website/nha-khoa-tong-quat.html" class="dropdown-service-item">
            <span class="dropdown-service-title">Nha khoa tổng quát</span>
            <span class="dropdown-service-sub">Khám, vệ sinh, trám răng</span>
          </a>
        </div>
      </li>
      <li><a class="transition" href="/website/bang-gia.html" title="Bảng giá">BẢNG GIÁ</a></li>
      <li><a class="transition" href="/website/bac-si.html" title="Bác sĩ">BÁC SĨ</a></li>
      <li><a class="transition active" href="/website/tin-tuc.html" title="Tin tức & Ưu đãi">TIN TỨC &amp; ƯU ĐÃI</a></li>
      <li><a class="transition" href="/website/lien-he.html" title="Liên hệ">LIÊN HỆ</a></li>
    </ul>
  </nav>
 </div>
 </div>
 </div>
 </div>
</header>

<main id="main-content" style="background: #FAF8F5; min-height: 80vh; padding-top: 24px; padding-bottom: 60px;">
  <div class="gf-article-detail-wrap">

    <!-- Top Category Tabs (Synced with tin-tuc.html) -->
    <nav class="gf-news-cat-bar" aria-label="Danh mục tin tức" style="margin-bottom: 24px;">
      <a href="/website/tin-tuc.html" class="gf-news-cat-item active">Tất cả</a>
      <a href="/website/tin-tuc.html?cat=nieng-rang" class="gf-news-cat-item">Niềng răng</a>
      <a href="/website/tin-tuc.html?cat=rang-su" class="gf-news-cat-item">Răng sứ</a>
      <a href="/website/tin-tuc.html?cat=implant" class="gf-news-cat-item">Implant</a>
      <a href="/website/tin-tuc.html?cat=cham-soc-rang" class="gf-news-cat-item">Chăm sóc răng</a>
      <a href="/website/tin-tuc.html?cat=uu-dai" class="gf-news-cat-item">Ưu đãi</a>
    </nav>

    <!-- 2-Column Grid -->
    <div class="gf-news-grid-2col">

      <!-- Left Column: Article Body -->
      <article class="gf-article-content">
        <a href="/website/tin-tuc.html" class="gf-back-to-news" title="Quay lại Tin tức & Ưu đãi">
          <i class="fa-solid fa-arrow-left"></i>
          <span>Quay lại Tin tức &amp; Ưu đãi</span>
        </a>

        <h1 class="gf-article-title"><?= $title ?></h1>

        <div class="gf-article-meta-row">
            <span><i class="fa-regular fa-calendar"></i> <?= $publishedDate ?></span>
            <span><i class="fa-solid fa-user-doctor"></i> Bác sĩ Nha Khoa Kim Dung</span>
            <span><i class="fa-regular fa-clock"></i> 5 phút đọc</span>
        </div>

        <?php if ($excerpt): ?>
            <div class="gf-article-sapo">
                <?= $excerpt ?>
            </div>
        <?php endif; ?>

        <?php if ($image): ?>
            <div class="gf-article-figure">
                <img src="<?= $image ?>" alt="<?= $title ?>">
                <div class="gf-article-figcaption">Hình ảnh minh họa chuyên môn tại Nha Khoa Kim Dung</div>
            </div>
        <?php endif; ?>

        <div class="gf-article-body">
            <?= $content ?>
        </div>

        <!-- Article Actions Footer Bar (Matching Designer Mockup) -->
        <div class="gf-article-actions-bar">
          <a href="/website/dat-lich.html" class="gf-btn-consult-now">
            <i class="fa-solid fa-calendar-check"></i>
            <span>Tư vấn ngay</span>
          </a>

          <div class="gf-social-share-group">
            <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($currentUrl) ?>" target="_blank" rel="noopener noreferrer" class="gf-social-btn" title="Chia sẻ Facebook">
              <i class="fa-brands fa-facebook-f"></i>
            </a>
            <a href="mailto:nhakhoakimdung@gmail.com?subject=<?= urlencode($title) ?>&body=<?= urlencode($currentUrl) ?>" class="gf-social-btn" title="Gửi Email">
              <i class="fa-regular fa-envelope"></i>
            </a>
            <button type="button" class="gf-social-btn" title="Sao chép liên kết" onclick="navigator.clipboard.writeText(window.location.href); alert('Đã sao chép liên kết bài viết!');">
              <i class="fa-solid fa-link"></i>
            </button>
          </div>
        </div>

      </article>

      <!-- Right Column: Sidebar -->
      <aside class="gf-news-sidebar">

        <!-- Widget 1: Đặt lịch tư vấn (Yellow Widget) -->
        <div class="gf-news-booking-widget">
          <h3 class="gf-news-booking-title">Đặt lịch tư vấn</h3>
          <p class="gf-news-booking-sub">Bạn vui lòng để lại thông tin, chúng tôi sẽ liên hệ ngay!</p>

          <form class="gf-news-booking-form" id="sidebarBookingForm" onsubmit="handleSidebarBooking(event)">
            <input type="text" id="sb_name" name="fullname" placeholder="Họ và tên" required>
            <input type="tel" id="sb_phone" name="phone" placeholder="Số điện thoại" required>
            <textarea id="sb_notes" name="content" rows="3" placeholder="Nội dung cần tư vấn (ví dụ: bọc răng sứ, niềng răng, implant...)"></textarea>
            <button type="submit" class="gf-news-booking-submit" id="sb_btn">ĐĂNG KÝ NGAY</button>
          </form>
          <div id="sb_msg" style="display:none; margin-top:12px; font-size:13px; text-align:center; font-weight:600;"></div>
        </div>

        <!-- Widget 2: Xem nhiều / Bài viết chuyên đề khác -->
        <div class="gf-popular-widget">
          <h4 class="gf-popular-heading">Xem nhiều</h4>
          <ul class="gf-popular-list">
            <?php if (!empty($recentPosts)): ?>
              <?php foreach ($recentPosts as $p): ?>
                <?php if ($p['slug'] === $post['slug']) continue; ?>
                <li class="gf-popular-item">
                  <a href="/tin-tuc/<?= htmlspecialchars($p['slug']) ?>" class="gf-popular-link">
                    <div class="gf-popular-thumb">
                      <img src="<?= !empty($p['featured_image']) ? htmlspecialchars($p['featured_image']) : '/website/assets/images/hero-clinic.jpg' ?>" alt="<?= htmlspecialchars($p['title']) ?>" loading="lazy">
                    </div>
                    <h5 class="gf-popular-title"><?= htmlspecialchars($p['title']) ?></h5>
                  </a>
                </li>
              <?php endforeach; ?>
            <?php endif; ?>
            <li class="gf-popular-item">
              <a href="/website/quy-trinh-lam-rang-su-dien-ra-nhu-the-nao.html" class="gf-popular-link">
                <div class="gf-popular-thumb">
                  <img src="https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=300&auto=format&fit=crop&q=80" alt="Quy trình bọc răng sứ chuẩn" loading="lazy">
                </div>
                <h5 class="gf-popular-title">Quy trình làm răng sứ diễn ra như thế nào? Bác sĩ Kim Dung giải đáp</h5>
              </a>
            </li>
          </ul>
        </div>

      </aside>

    </div>
  </div>
</main>

<footer class="wrap-footer">
 <div class="info-footer">
 <div class="wrap-content">
 <div class="flex-footer">
 <div class="box-footer">
  <div class="logo-footer">
 <a class="logoft peShine bgstart-animate" href="/website/index.html">
 <img src="https://nhakhoakimdung.vn/upload/news/group-358-1753429953.png" alt="NHA KHOA KIM DUNG">
 <img class="start-animate" src="https://nhakhoakimdung.vn/assets/images/saolaplanh.webp" alt="">
 <img class="start-animate1" src="https://nhakhoakimdung.vn/assets/images/saolaplanh.webp" alt="">
 <img class="start-animate2" src="https://nhakhoakimdung.vn/assets/images/saolaplanh.webp" alt="">
 </a>
 </div>
 <div class="name-company">Nha Khoa Kim Dung Thái Nguyên | Chăm Sóc &amp; Điều Trị Răng Miệng Uy Tín</div>
 <div class="content-footer">
 <p>📍 Địa chỉ: Số 15, đường Bắc Sơn kéo dài, P. Quang Trung, TP. Thái Nguyên</p>
 <p>📞 Hotline: 0862 960 886</p>
 <p>✉️ Email: nhakhoakimdung@gmail.com</p>
 <p>🌐 Website: www.nhakhoakimdung.vn</p>
 </div>
 </div>
 </div>
 </div>
 </div>
</footer>

<script>
async function handleSidebarBooking(e) {
    e.preventDefault();
    const btn = document.getElementById('sb_btn');
    const msg = document.getElementById('sb_msg');
    const name = document.getElementById('sb_name').value.trim();
    const phone = document.getElementById('sb_phone').value.trim();
    const notes = document.getElementById('sb_notes').value.trim();

    if (!name || !phone) return;

    btn.disabled = true;
    btn.innerText = 'ĐANG GỬI...';
    msg.style.display = 'none';

    // Calculate tomorrow date and 09:00 default slot
    const tomorrow = new Date(Date.now() + 86400000).toISOString().split('T')[0];

    try {
        const res = await fetch('/api/v1/bookings', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name: name,
                phone: phone,
                notes: notes,
                service_id: 1,
                date: tomorrow,
                time: '09:00',
                source_page: window.location.pathname
            })
        });
        const data = await res.json();
        if (data.success) {
            msg.style.display = 'block';
            msg.style.color = '#005A36';
            msg.innerHTML = '✅ Đăng ký thành công! Mã hẹn: <strong>' + (data.booking_code || '') + '</strong>. Bác sĩ sẽ gọi lại ngay.';
            document.getElementById('sidebarBookingForm').reset();
        } else {
            msg.style.display = 'block';
            msg.style.color = '#dc2626';
            msg.innerText = data.error?.message || 'Có lỗi xảy ra, vui lòng liên hệ hotline!';
        }
    } catch (err) {
        msg.style.display = 'block';
        msg.style.color = '#005A36';
        msg.innerText = '✅ Cảm ơn quý khách! Nha Khoa Kim Dung sẽ liên hệ tư vấn trong ít phút.';
    } finally {
        btn.disabled = false;
        btn.innerText = 'ĐĂNG KÝ NGAY';
    }
}
</script>
</body>
</html>
