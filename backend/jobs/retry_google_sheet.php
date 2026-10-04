<?php
// backend/jobs/retry_google_sheet.php
// Can be executed via CLI cron (e.g. */10 * * * * php /path/to/backend/jobs/retry_google_sheet.php)
// or included directly by the admin controller

require_once __DIR__ . '/../services/BookingService.php';
require_once __DIR__ . '/../repositories/BookingRepository.php';

$bookingRepo = new BookingRepository();
$bookingService = new BookingService();

$failedList = $bookingRepo->getPendingOrFailedSync(50);
$count = count($failedList);
$successCount = 0;

$isCli = (php_sapi_name() === 'cli');

if ($isCli) {
    echo "[" . date('Y-m-d H:i:s') . "] Starting Google Sheet retry job for {$count} pending/failed bookings...\n";
}

foreach ($failedList as $b) {
    $res = $bookingService->trySyncToGoogleSheet((int)$b['id']);
    if ($res['success']) {
        $successCount++;
        if ($isCli) echo "  - Booking {$b['booking_code']} (ID: {$b['id']}): Synced successfully.\n";
    } else {
        if ($isCli) echo "  - Booking {$b['booking_code']} (ID: {$b['id']}): Failed - {$res['error']}\n";
    }
}

if ($isCli) {
    echo "[" . date('Y-m-d H:i:s') . "] Finished: {$successCount}/{$count} synced.\n";
}

return [
    'total'   => $count,
    'synced'  => $successCount,
    'failed'  => $count - $successCount,
];
