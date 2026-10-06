<?php
// api/v1/posts.php

require_once __DIR__ . '/../../backend/core/Response.php';
require_once __DIR__ . '/../../backend/core/Validator.php';
require_once __DIR__ . '/../../backend/auth/ApiKeyAuth.php';
require_once __DIR__ . '/../../backend/services/PostService.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    Response::json([], 204);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('METHOD_NOT_ALLOWED', 'Chỉ chấp nhận phương thức POST.', 405);
}

// 1. Authenticate API Key
$key = ApiKeyAuth::validate();
if (!$key) {
    Response::error('UNAUTHORIZED', 'API Key không hợp lệ hoặc bị thiếu. Cần Authorization: Bearer <API_KEY>.', 401);
}

// 2. Parse JSON body
$raw = file_get_contents('php://input');
if ($raw !== false) {
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
}
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

// 3. Validation
$validator = new Validator($payload);
$validator->required('title', 'Tiêu đề bài viết')
          ->maxLength('title', 255, 'Tiêu đề bài viết')
          ->required('content', 'Nội dung bài viết');

if (!$validator->passes()) {
    Response::error('VALIDATION_ERROR', $validator->getFirstError(), 422, ['errors' => $validator->getErrors()]);
}

try {
    $postService = new PostService();
    $result = $postService->receiveExternalPost($payload);

    $appConfig = require __DIR__ . '/../../backend/config/app.php';
    $baseUrl = rtrim($appConfig['base_url'], '/');
    $publicUrl = $baseUrl . '/tin-tuc/' . $result['slug'];

    Response::success([
        'post_id' => $result['post_id'],
        'slug'    => $result['slug'],
        'action'  => $result['action'],
        'url'     => $publicUrl,
    ], ($result['action'] === 'created' ? 201 : 200));
} catch (Throwable $e) {
    $msg = (getenv('APP_ENV') === 'development') ? ('Lỗi lưu bài viết: ' . $e->getMessage()) : 'Lỗi hệ thống khi lưu bài viết.';
    Response::error('POST_CREATION_FAILED', $msg, 500);
}
