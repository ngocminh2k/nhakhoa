<?php
// backend/services/AvailabilityService.php

require_once __DIR__ . '/../repositories/ServiceRepository.php';
require_once __DIR__ . '/../repositories/ScheduleRepository.php';
require_once __DIR__ . '/../repositories/BookingRepository.php';

class AvailabilityService {
    private ServiceRepository $serviceRepo;
    private ScheduleRepository $scheduleRepo;
    private BookingRepository $bookingRepo;

    public function __construct(
        ?ServiceRepository $serviceRepo = null,
        ?ScheduleRepository $scheduleRepo = null,
        ?BookingRepository $bookingRepo = null
    ) {
        $this->serviceRepo = $serviceRepo ?? new ServiceRepository();
        $this->scheduleRepo = $scheduleRepo ?? new ScheduleRepository();
        $this->bookingRepo = $bookingRepo ?? new BookingRepository();
    }

    /**
     * Get free time slots for a given service and date
     * @return array Array of slot times, e.g. ["08:00", "08:30", "14:00"]
     */
    public function getAvailableSlots(int $serviceId, string $date): array {
        // Validate date format YYYY-MM-DD
        $dt = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dt || $dt->format('Y-m-d') !== $date) {
            return [];
        }

        // Cannot book dates in the past
        $todayStr = date('Y-m-d');
        if ($date < $todayStr) {
            return [];
        }

        // 1. Get service duration
        $service = $this->serviceRepo->findById($serviceId);
        if (!$service || empty($service['active'])) {
            return [];
        }
        $durationMinutes = max(15, (int)$service['duration_minutes']);

        // 2. Get business hours for that weekday
        // PHP 'w' returns 0 (Sunday) to 6 (Saturday)
        $weekday = (int)$dt->format('w');
        $hours = $this->scheduleRepo->getBusinessHoursByWeekday($weekday);
        if (!$hours || empty($hours['active'])) {
            return []; // Closed on this day
        }

        $openTimeStr = $hours['start_time']; // e.g. '08:00:00'
        $closeTimeStr = $hours['end_time'];  // e.g. '19:30:00'

        // 3. Get schedule blocks for this date (holidays, doctor leave, meetings)
        $blocks = $this->scheduleRepo->getBlocksForDate($date);

        // 4. Get active bookings for this date
        $bookings = $this->bookingRepo->getActiveBookingsForDate($date);

        // 5. Generate candidate slots in 30-minute intervals
        $slots = [];
        $slotStepMinutes = 30;

        $openTime = strtotime("$date $openTimeStr");
        $closeTime = strtotime("$date $closeTimeStr");
        $currentTime = time();

        for ($curr = $openTime; $curr < $closeTime; $curr += ($slotStepMinutes * 60)) {
            $slotEnd = $curr + ($durationMinutes * 60);

            // Must end before or at business close time
            if ($slotEnd > $closeTime) {
                break;
            }

            // If date is today, slot must be at least 30 minutes in the future
            if ($date === $todayStr && $curr < ($currentTime + 1800)) {
                continue;
            }

            $currStr = date('H:i:s', $curr);
            $endStr  = date('H:i:s', $slotEnd);
            $currDtStr = date('Y-m-d H:i:s', $curr);
            $endDtStr  = date('Y-m-d H:i:s', $slotEnd);

            // Check collision with schedule blocks
            $hasBlockCollision = false;
            foreach ($blocks as $block) {
                // Collision if NOT (slotEnd <= blockStart OR slotStart >= blockEnd)
                if (!($endDtStr <= $block['start_datetime'] || $currDtStr >= $block['end_datetime'])) {
                    $hasBlockCollision = true;
                    break;
                }
            }
            if ($hasBlockCollision) {
                continue;
            }

            // Check collision with existing active bookings
            $hasBookingCollision = false;
            foreach ($bookings as $b) {
                $bStart = $b['start_time'];
                $bEnd   = $b['end_time'];
                // Collision if NOT (slotEnd <= bStart OR slotStart >= bEnd)
                if (!($endStr <= $bStart || $currStr >= $bEnd)) {
                    $hasBookingCollision = true;
                    break;
                }
            }
            if ($hasBookingCollision) {
                continue;
            }

            // Slot is available!
            $slots[] = date('H:i', $curr);
        }

        return $slots;
    }

    /**
     * Check if a specific slot is available
     */
    public function isSlotAvailable(int $serviceId, string $date, string $startTime, ?int $excludeBookingId = null): bool {
        $slots = $this->getAvailableSlots($serviceId, $date);
        $startShort = substr($startTime, 0, 5); // '15:30'

        // If checking for admin rescheduling of same booking:
        if ($excludeBookingId !== null) {
            // Also check DB conflict directly
            $service = $this->serviceRepo->findById($serviceId);
            $duration = $service ? (int)$service['duration_minutes'] : 30;
            $endTime = date('H:i:s', strtotime("$date $startTime") + ($duration * 60));
            return !$this->bookingRepo->hasConflict($date, $startTime, $endTime, $excludeBookingId);
        }

        return in_array($startShort, $slots, true);
    }
}
