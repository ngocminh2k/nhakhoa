<?php
// backend/public/news_list.php
// Dynamic news listing with pagination for Nha Khoa Kim Dung
// Preserves Frontend Designer's template while automatically injecting paginated live articles from MariaDB

require_once __DIR__ . '/../repositories/PostRepository.php';

$templatePath = __DIR__ . '/../../website/tin-tuc.html';
if (!file_exists($templatePath)) {
    http_response_code(404);
    echo "Template not found.";
    exit;
}

$html = file_get_contents($templatePath);

try {
    $postRepo = new PostRepository();
    $totalPosts = $postRepo->countPublished();

    if ($totalPosts > 0) {
        $perPage = 12;
        $totalPages = max(1, (int)ceil($totalPosts / $perPage));
        $page = max(1, min((int)($_GET['page'] ?? 1), $totalPages));
        $offset = ($page - 1) * $perPage;

        $posts = $postRepo->getPublishedPosts($perPage, $offset);

        // 1. Build Articles HTML
        $articlesHtml = "\n        <!-- [AUTO-PAGINATED ARTICLES - PAGE {$page}/{$totalPages}] -->\n";
        foreach ($posts as $p) {
            $slug = htmlspecialchars($p['slug'] ?? '', ENT_QUOTES, 'UTF-8');
            $title = htmlspecialchars($p['title'] ?? '', ENT_QUOTES, 'UTF-8');
            $url = "/tin-tuc/{$slug}";

            // Category detection for client-side filter compatibility
            $titleLower = mb_strtolower($p['title'] ?? '', 'UTF-8');
            $cat = 'cham-soc-rang';
            if (str_contains($titleLower, 'implant') || str_contains($titleLower, 'xoang') || str_contains($titleLower, 'ghép xương')) {
                $cat = 'implant';
            } elseif (str_contains($titleLower, 'sứ') || str_contains($titleLower, 'veneer') || str_contains($titleLower, 'cercon') || str_contains($titleLower, 'emax')) {
                $cat = 'rang-su';
            } elseif (str_contains($titleLower, 'niềng') || str_contains($titleLower, 'mắc cài') || str_contains($titleLower, 'invisalign') || str_contains($titleLower, 'chỉnh nha')) {
                $cat = 'nieng-rang';
            } elseif (str_contains($titleLower, 'ưu đãi') || str_contains($titleLower, 'tẩy trắng') || str_contains($titleLower, 'bảo hành') || str_contains($titleLower, 'bảng giá')) {
                $cat = 'uu-dai';
            }

            $img = !empty($p['featured_image'])
                ? htmlspecialchars($p['featured_image'], ENT_QUOTES, 'UTF-8')
                : 'https://nhakhoakimdung.vn/thumbs/390x300x1/upload/news/bai-dang-instagram-quang-cao-sale-nieng-rang-nha-khoa-tet-hien-dai-do-vang-1772767722.png.webp';

            $excerpt = !empty($p['excerpt'])
                ? htmlspecialchars($p['excerpt'], ENT_QUOTES, 'UTF-8')
                : htmlspecialchars(mb_substr(strip_tags($p['content'] ?? ''), 0, 165) . '...', ENT_QUOTES, 'UTF-8');

            $articlesHtml .= "        <article class=\"gf-news-item\" data-cat=\"{$cat}\">\n";
            $articlesHtml .= "          <a href=\"{$url}\" class=\"gf-news-thumb\" title=\"{$title}\">\n";
            $articlesHtml .= "            <img src=\"{$img}\" alt=\"{$title}\" loading=\"lazy\" width=\"220\" height=\"138\" style=\"object-fit: cover;\">\n";
            $articlesHtml .= "          </a>\n";
            $articlesHtml .= "          <div class=\"gf-news-content\">\n";
            $articlesHtml .= "            <h2 class=\"gf-news-title\">\n";
            $articlesHtml .= "              <a href=\"{$url}\">{$title}</a>\n";
            $articlesHtml .= "            </h2>\n";
            $articlesHtml .= "            <p class=\"gf-news-desc\">{$excerpt}</p>\n";
            $articlesHtml .= "          </div>\n";
            $articlesHtml .= "        </article>\n";
        }

        // 2. Build Luxury Greenfield Pagination HTML
        $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/tin-tuc', PHP_URL_PATH);
        $makeUrl = function(int $targetPage) use ($currentPath): string {
            return htmlspecialchars($currentPath . '?page=' . $targetPage, ENT_QUOTES, 'UTF-8');
        };

        $paginationHtml = "\n        <!-- [PAGINATION CONTROLS] -->\n";
        $paginationHtml .= "        <div class=\"gf-pagination-wrap\" style=\"margin-top: 36px; padding-top: 24px; border-top: 1px solid rgba(234, 191, 14, 0.3); text-align: center;\">\n";
        $paginationHtml .= "          <div style=\"display: inline-flex; align-items: center; justify-content: center; gap: 6px; flex-wrap: wrap;\">\n";

        // Previous button
        if ($page > 1) {
            $prevUrl = $makeUrl($page - 1);
            $paginationHtml .= "            <a href=\"{$prevUrl}\" class=\"btn-page\" style=\"display: inline-flex; align-items: center; gap: 4px; padding: 8px 14px; border-radius: 8px; border: 1px solid #d4d4d8; background: #fff; color: #18181b; font-size: 13.5px; font-weight: 600; text-decoration: none; transition: all 0.15s;\">‹ Trang trước</a>\n";
        } else {
            $paginationHtml .= "            <span style=\"padding: 8px 14px; border-radius: 8px; border: 1px solid #e4e4e7; background: #f4f4f5; color: #a1a1aa; font-size: 13.5px; font-weight: 600; cursor: not-allowed;\">‹ Trang trước</span>\n";
        }

        // Page number links with smart ellipsis
        $range = [];
        $delta = 2; // Show 2 pages before and after current
        for ($i = max(2, $page - $delta); $i <= min($totalPages - 1, $page + $delta); $i++) {
            $range[] = $i;
        }

        // First page
        $activeStyle = "background: #005A36; color: #ffffff; border: 1px solid #005A36; font-weight: 700;";
        $normalStyle = "background: #ffffff; color: #18181b; border: 1px solid #d4d4d8; font-weight: 600;";

        $p1Style = ($page === 1) ? $activeStyle : $normalStyle;
        $p1Url = $makeUrl(1);
        $paginationHtml .= "            <a href=\"{$p1Url}\" style=\"min-width: 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; {$p1Style} font-size: 13.5px; text-decoration: none;\">1</a>\n";

        if (!empty($range) && $range[0] > 2) {
            $paginationHtml .= "            <span style=\"padding: 0 4px; color: #a1a1aa;\">...</span>\n";
        }

        foreach ($range as $r) {
            $rStyle = ($page === $r) ? $activeStyle : $normalStyle;
            $rUrl = $makeUrl($r);
            $paginationHtml .= "            <a href=\"{$rUrl}\" style=\"min-width: 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; {$rStyle} font-size: 13.5px; text-decoration: none;\">{$r}</a>\n";
        }

        if (!empty($range) && end($range) < $totalPages - 1) {
            $paginationHtml .= "            <span style=\"padding: 0 4px; color: #a1a1aa;\">...</span>\n";
        }

        // Last page if totalPages > 1
        if ($totalPages > 1) {
            $pLastStyle = ($page === $totalPages) ? $activeStyle : $normalStyle;
            $pLastUrl = $makeUrl($totalPages);
            $paginationHtml .= "            <a href=\"{$pLastUrl}\" style=\"min-width: 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; {$pLastStyle} font-size: 13.5px; text-decoration: none;\">{$totalPages}</a>\n";
        }

        // Next button
        if ($page < $totalPages) {
            $nextUrl = $makeUrl($page + 1);
            $paginationHtml .= "            <a href=\"{$nextUrl}\" class=\"btn-page\" style=\"display: inline-flex; align-items: center; gap: 4px; padding: 8px 14px; border-radius: 8px; border: 1px solid #d4d4d8; background: #fff; color: #18181b; font-size: 13.5px; font-weight: 600; text-decoration: none; transition: all 0.15s;\">Trang sau ›</a>\n";
        } else {
            $paginationHtml .= "            <span style=\"padding: 8px 14px; border-radius: 8px; border: 1px solid #e4e4e7; background: #f4f4f5; color: #a1a1aa; font-size: 13.5px; font-weight: 600; cursor: not-allowed;\">Trang sau ›</span>\n";
        }

        $paginationHtml .= "          </div>\n";

        // Summary text
        $from = $offset + 1;
        $to = min($offset + $perPage, $totalPosts);
        $paginationHtml .= "          <div style=\"font-size: 12.5px; color: #64748b; margin-top: 10px;\">Hiển thị bài viết <strong>{$from} - {$to}</strong> trên tổng số <strong>{$totalPosts}</strong> bài kiến thức nha khoa (Trang <strong>{$page}</strong> / <strong>{$totalPages}</strong>)</div>\n";
        $paginationHtml .= "        </div>\n";

        // Replace entire inner list with paginated items + pagination controls
        $pattern = '#<section class="gf-news-list" id="gfNewsList">.*?</section>#s';
        $replacement = "<section class=\"gf-news-list\" id=\"gfNewsList\">{$articlesHtml}{$paginationHtml}\n      </section>";
        $html = preg_replace($pattern, $replacement, $html);
    }
} catch (Throwable $e) {
    // Fail gracefully: if database is unreachable, output template as-is
    error_log('Error loading dynamic posts into news_list: ' . $e->getMessage());
}

header('Content-Type: text/html; charset=utf-8');
echo $html;
