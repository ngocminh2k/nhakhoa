<?php
// backend/services/BookingService.php

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../repositories/BookingRepository.php';
require_once __DIR__ . '/../repositories/ServiceRepository.php';
require_once __DIR__ . '/../services/AvailabilityService.php';
require_once __DIR__ . '/../services/GoogleSheetsService.php';

class BookingService {
    private BookingRepository $bookingRepo;
    private ServiceRepository $serviceRepo;
    private AvailabilityService $availabilityService;
    private GoogleSheetsService $sheetsService;
    private PDO $db;

    public function __construct() {
        $this->bookingRepo = new BookingRepository();
        $this->serviceRepo = new ServiceRepository();
        $this->availabilityService = new AvailabilityService();
        $this->sheetsService = new GoogleSheetsService();
        $this->db = Database::getConnection();
    }

    /**
     * Create a booking with strict concurrency protection against double booking
     * @throws Exception
     */
    public function createBooking(array $payload): array {
        $serviceId = (int)$payload['service_id'];
        $date      = trim($payload['date']);
        $time      = trim($payload['time']);

        // Format time to HH:MM:00
        if (strlen($time) === 5) {
            $time .= ':00';
        }

        $service = $this->serviceRepo->findById($serviceId);
        if (!$service || empty($service['active'])) {
            throw new InvalidArgumentException('Dịch vụ không tồn tại hoặc tạm ngưng.');
        }

        $durationMinutes = max(15, (int)$service['duration_minutes']);
        $startTimeSec = strtotime("$date $time");
        $endTimeSec   = $startTimeSec + ($durationMinutes * 60);
        $endTime      = date('H:i:s', $endTimeSec);

        // 1. Availability check against business hours & schedule blocks
        if (!$this->availabilityService->isSlotAvailable($serviceId, $date, substr($time, 0, 5))) {
            throw new RuntimeException('Khung giờ này vừa được đặt hoặc không khả dụng. Vui lòng chọn giờ khác.', 409);
        }

        // 2. BEGIN Database Transaction & Lock check for concurrent double booking
        // Use MySQL advisory GET_LOCK to prevent gap-lock deadlocks on InnoDB.
        // The lock key is "kd_<date>_<HH:MM>" — fine-grained, per-slot.
        // ponytail: upgrade to Redis SETNX if traffic exceeds single-server hosting.
        // ponytail: integration test needed — send two concurrent POST /api/v1/bookings
        //   for the same slot; assert exactly one gets 200 and the other gets 409.
        //   Cannot be unit-tested without a live MySQL connection (GET_LOCK is server-side).
        $lockKey = 'kd_' . $date . '_' . substr($time, 0, 5);
        $lockResult = $this->db->query("SELECT GET_LOCK(" . $this->db->quote($lockKey) . ", 5)")->fetchColumn();
        if (!$lockResult) {
            throw new RuntimeException('Hệ thống đang bận xử lý cùng khung giờ này. Vui lòng thử lại sau.', 503);
        }

        $this->db->beginTransaction();
        try {
            // Recheck slot conflict under advisory lock — no gap-lock, no deadlock
            if ($this->bookingRepo->hasConflict($date, $time, $endTime)) {
                $this->db->rollBack();
                $this->db->query("SELECT RELEASE_LOCK(" . $this->db->quote($lockKey) . ")");
                throw new RuntimeException('Khung giờ này vừa được đặt bởi khách hàng khác. Vui lòng chọn giờ khác.', 409);
            }

            // Generate unique public booking code: KD-YYYYMMDD-XXXX
            $dateClean = str_replace('-', '', $date);
            $randomHex = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
            $bookingCode = "KD-{$dateClean}-{$randomHex}";

            $insertData = [
                'booking_code'       => $bookingCode,
                'customer_name'      => $payload['name'],
                'phone'              => $payload['phone'],
                'notes'              => $payload['notes'] ?? '',
                'service_id'         => $serviceId,
                'booking_date'       => $date,
                'start_time'         => $time,
                'end_time'           => $endTime,
                'status'             => 'new',
                'source_page'        => $payload['source_page'] ?? null,
                'referrer'           => $payload['referrer'] ?? null,
                'utm_source'         => $payload['utm_source'] ?? null,
                'utm_medium'         => $payload['utm_medium'] ?? null,
                'utm_campaign'       => $payload['utm_campaign'] ?? null,
                'utm_content'        => $payload['utm_content'] ?? null,
                'utm_term'           => $payload['utm_term'] ?? null,
                'session_id'         => $payload['session_id'] ?? null,
                'sheet_sync_status'  => 'pending',
            ];

            $bookingId = $this->bookingRepo->create($insertData);

            // COMMIT MariaDB first — MariaDB is the authoritative Source of Truth
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->db->query("SELECT RELEASE_LOCK(" . $this->db->quote($lockKey) . ")");
            throw $e;
        }

        // Release advisory lock after commit
        $this->db->query("SELECT RELEASE_LOCK(" . $this->db->quote($lockKey) . ")");

        // 3. Post-commit: Attempt Google Sheet synchronization
        // If Google Sheet times out or fails, the booking remains safely confirmed in MariaDB
        $syncResult = $this->trySyncToGoogleSheet($bookingId);

        return [
            'booking_id'   => $bookingId,
            'booking_code' => $bookingCode,
            'status'       => 'new',
            'service'      => $service['name'],
            'date'         => $date,
            'time'         => substr($time, 0, 5),
            'sheet_synced' => $syncResult['success'],
        ];
    }

    /**
     * Synchronize a specific booking to Google Sheets
     */
    public function trySyncToGoogleSheet(int $bookingId): array {
        $booking = $this->bookingRepo->findById($bookingId);
        if (!$booking) {
            return ['success' => false, 'error' => 'Booking not found'];
        }

        $res = $this->sheetsService->appendBooking($booking);
        if ($res['success']) {
            $this->bookingRepo->updateSheetSync($bookingId, 'synced', null);
        } else {
            $this->bookingRepo->updateSheetSync($bookingId, 'failed', $res['error']);
        }

        return $res;
    }
}
