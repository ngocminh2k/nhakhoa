<?php
// api/v1/availability.php

require_once __DIR__ . '/../../backend/core/Response.php';
require_once __DIR__ . '/../../backend/core/Validator.php';
require_once __DIR__ . '/../../backend/services/AvailabilityService.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    Response::json([], 204);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('METHOD_NOT_ALLOWED', 'Chỉ chấp nhận phương thức GET.', 405);
}

$serviceId = (int)($_GET['service_id'] ?? 0);
$date = trim($_GET['date'] ?? '');

$validator = new Validator([
    'service_id' => $serviceId,
    'date'       => $date,
]);

$validator->required('service_id', 'Dịch vụ')
          ->integer('service_id', 'Dịch vụ')
          ->required('date', 'Ngày hẹn')
          ->date('date', 'Ngày hẹn');

if (!$validator->passes()) {
    Response::error('VALIDATION_ERROR', $validator->getFirstError(), 422, ['errors' => $validator->getErrors()]);
}

try {
    $service = new AvailabilityService();
    $slots = $service->getAvailableSlots($serviceId, $date);

    Response::success([
        'date'  => $date,
        'slots' => $slots,
    ]);
} catch (Throwable $e) {
    Response::error('INTERNAL_ERROR', 'Lỗi kiểm tra lịch trống: ' . $e->getMessage(), 500);
}
