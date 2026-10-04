<?php
// router.php — PHP built-in server router for local dev
// Routes API and admin requests; serves static files directly.

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/') ?: '/';

// Dynamic news listing: /website/tin-tuc.html, /tin-tuc.html, /tin-tuc
if ($uri === '/website/tin-tuc.html' || $uri === '/tin-tuc.html' || $uri === '/tin-tuc') {
    require __DIR__ . '/backend/public/news_list.php';
    return true;
}

// Serve static files (CSS, JS, images, HTML) directly
$staticFile = __DIR__ . $uri;
if ($uri !== '/' && file_exists($staticFile) && !is_dir($staticFile) && !str_ends_with($uri, '.php')) {
    return false; // Let built-in server handle it
}

// API routes
if (str_starts_with($uri, '/api/v1/')) {
    $route = preg_replace('#^/api/v1#', '', $uri);
    $map = [
        '/bookings'    => '/api/v1/bookings.php',
        '/availability'=> '/api/v1/availability.php',
        '/services'    => '/api/v1/services.php',
        '/posts'       => '/api/v1/posts.php',
        '/track'       => '/api/v1/track.php',
    ];
    foreach ($map as $pattern => $script) {
        if ($route === $pattern || str_starts_with($route, $pattern . '?')) {
            require __DIR__ . $script;
            return true;
        }
    }
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'API route not found']);
    return true;
}

// Blog post dynamic route: /tin-tuc/<slug>
if (preg_match('#^/tin-tuc/([a-z0-9\-]+)$#', $uri, $m)) {
    $_GET['slug'] = $m[1];
    require __DIR__ . '/backend/public/post.php';
    return true;
}

// Admin panel
if (str_starts_with($uri, '/admin')) {
    $adminFile = __DIR__ . $uri . (str_ends_with($uri, '/admin') ? '/index.php' : '');
    if (!str_ends_with($adminFile, '.php')) $adminFile .= '.php';
    if (file_exists($adminFile)) {
        require $adminFile;
        return true;
    }
}

// Installer
if ($uri === '/install') {
    require __DIR__ . '/backend/install.php';
    return true;
}

// Root → serve index.html
if ($uri === '/') {
    readfile(__DIR__ . '/index.html');
    return true;
}

// 404
http_response_code(404);
echo '404 Not Found';
