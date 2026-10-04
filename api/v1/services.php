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
    Response::error('INTERNAL_ERROR', 'Không thể tải danh sách dịch vụ: ' . $e->getMessage(), 500);
}
