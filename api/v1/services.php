<?php
// api/v1/services.php

require_once __DIR__ . '/../../backend/core/Response.php';
require_once __DIR__ . '/../../backend/services/ServiceService.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    Response::json([], 204);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('METHOD_NOT_ALLOWED', 'Chỉ chấp nhận phương thức GET.', 405);
}

try {
    $serviceService = new ServiceService();
    $services = $serviceService->getActiveServices();
    Response::success(['services' => $services]);
} catch (Throwable $e) {
    $msg = (getenv('APP_ENV') === 'development') ? ('Không thể tải danh sách dịch vụ: ' . $e->getMessage()) : 'Không thể tải danh sách dịch vụ. Vui lòng thử lại sau.';
    Response::error('INTERNAL_ERROR', $msg, 500);
}
