<?php
// api/v1/bookings.php

require_once __DIR__ . '/../../backend/core/Response.php';
require_once __DIR__ . '/../../backend/core/Validator.php';
require_once __DIR__ . '/../../backend/security/RateLimiter.php';
require_once __DIR__ . '/../../backend/security/Sanitizer.php';
require_once __DIR__ . '/../../backend/services/BookingService.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    Response::json([], 204);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('METHOD_NOT_ALLOWED', 'Chỉ chấp nhận phương thức POST.', 405);
}

// 1. Rate Limiting: Max 10 bookings per IP per hour
if (!RateLimiter::check('booking_submit', 10, 3600)) {
    Response::error('RATE_LIMITED', 'Bạn đã gửi yêu cầu quá nhiều lần. Vui lòng thử lại sau 1 giờ.', 429);
}

// 2. Parse JSON or Form Payload
$rawInput = file_get_contents('php://input');
if ($rawInput !== false) {
    $rawInput = preg_replace('/^\xEF\xBB\xBF/', '', $rawInput);
}
$payload = json_decode($rawInput, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

// 3. Honeypot check (anti-bot)
if (!empty($payload['hp_field']) || !empty($payload['website_url'])) {
    // Fake success response to fool spam bots
    Response::success([
        'booking_code' => 'KD-' . date('Ymd') . '-0000',
        'status'       => 'new',
        'message'      => 'Đặt lịch thành công.',
    ]);
}

// 4. Validate input
$validator = new Validator($payload);
$validator->required('name', 'Họ và tên')
          ->maxLength('name', 100, 'Họ và tên')
          ->required('phone', 'Số điện thoại')
          ->phone('phone', 'Số điện thoại')
          ->required('service_id', 'Dịch vụ')
          ->integer('service_id', 'Dịch vụ')
          ->required('date', 'Ngày hẹn')
          ->date('date', 'Ngày hẹn')
          ->required('time', 'Giờ hẹn')
          ->time('time', 'Giờ hẹn')
          ->maxLength('notes', 1000, 'Ghi chú');

if (!$validator->passes()) {
    Response::error('VALIDATION_ERROR', $validator->getFirstError(), 422, ['errors' => $validator->getErrors()]);
}

// 5. Sanitize and prepare
$data = [
    'name'         => Sanitizer::text($payload['name'] ?? ''),
    'phone'        => Sanitizer::text($payload['phone'] ?? ''),
    'notes'        => Sanitizer::text($payload['notes'] ?? ''),
    'service_id'   => (int)$payload['service_id'],
    'date'         => trim($payload['date'] ?? ''),
    'time'         => trim($payload['time'] ?? ''),
    'source_page'  => Sanitizer::text($payload['source_page'] ?? ''),
    'referrer'     => Sanitizer::text($payload['referrer'] ?? ''),
    'utm_source'   => Sanitizer::text($payload['utm_source'] ?? ''),
    'utm_medium'   => Sanitizer::text($payload['utm_medium'] ?? ''),
    'utm_campaign' => Sanitizer::text($payload['utm_campaign'] ?? ''),
    'utm_content'  => Sanitizer::text($payload['utm_content'] ?? ''),
    'utm_term'     => Sanitizer::text($payload['utm_term'] ?? ''),
    'session_id'   => Sanitizer::text($payload['session_id'] ?? ($_COOKIE['kd_sid'] ?? '')),
];

try {
    $bookingService = new BookingService();
    $result = $bookingService->createBooking($data);

    Response::success([
        'booking_code' => $result['booking_code'],
        'status'       => $result['status'],
        'service'      => $result['service'],
        'date'         => $result['date'],
        'time'         => $result['time'],
    ], 201);
} catch (InvalidArgumentException $e) {
    Response::error('VALIDATION_ERROR', $e->getMessage(), 422);
} catch (RuntimeException $e) {
    if ($e->getCode() === 409) {
        Response::error('SLOT_UNAVAILABLE', $e->getMessage(), 409);
    }
    Response::error('BOOKING_FAILED', $e->getMessage(), 400);
} catch (Throwable $e) {
    $msg = (getenv('APP_ENV') === 'development') ? ('Có lỗi xảy ra: ' . $e->getMessage()) : 'Có lỗi xảy ra trong quá trình đặt lịch. Vui lòng thử lại sau.';
    Response::error('SERVER_ERROR', $msg, 500);
}
