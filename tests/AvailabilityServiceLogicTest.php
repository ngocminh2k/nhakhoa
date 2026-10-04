<?php
// tests/AvailabilityServiceLogicTest.php
// Unit & logic tests for availability calculations, slot stepping, weekday hours and overlap detection

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../backend/services/AvailabilityService.php';

// In-memory mock repositories for pure unit testing without MySQL
class InMemoryServiceRepository extends ServiceRepository {
    private array $services = [];

    public function __construct(array $services = []) {
        $this->services = $services;
    }

    public function findById(int $id): ?array {
        return $this->services[$id] ?? null;
    }
}

class InMemoryScheduleRepository extends ScheduleRepository {
    private array $businessHours = [];
    private array $blocks = [];

    public function __construct(array $businessHours = [], array $blocks = []) {
        $this->businessHours = $businessHours;
        $this->blocks = $blocks;
    }

    public function getBusinessHoursByWeekday(int $weekday): ?array {
        return $this->businessHours[$weekday] ?? null;
    }

    public function getBlocksForDate(string $date): array {
        return $this->blocks[$date] ?? [];
    }
}

class InMemoryBookingRepository extends BookingRepository {
    private array $bookings = [];

    public function __construct(array $bookings = []) {
        $this->bookings = $bookings;
    }

    public function getActiveBookingsForDate(string $date): array {
        return $this->bookings[$date] ?? [];
    }

    public function hasConflict(string $date, string $startTime, string $endTime, ?int $excludeBookingId = null): bool {
        $list = $this->bookings[$date] ?? [];
        foreach ($list as $b) {
            if ($excludeBookingId !== null && ($b['id'] ?? 0) === $excludeBookingId) {
                continue;
            }
            if (!($endTime <= $b['start_time'] || $startTime >= $b['end_time'])) {
                return true;
            }
        }
        return false;
    }
}

function runAvailabilityServiceLogicTests(): void {
    echo "\n--- [Suite: AvailabilityServiceLogicTest] ---\n";

    TestRunner::test('Time interval overlap mathematical logic (all 6 collision cases)', function() {
        // Collision if NOT (end1 <= start2 OR start1 >= end2)
        // Which is equivalent to: (start1 < end2) && (end1 > start2)
        $isOverlap = function(string $s1, string $e1, string $s2, string $e2): bool {
            return ($s1 < $e2) && ($e1 > $s2);
        };

        // Case 1: Identical intervals -> Overlap
        TestRunner::assertTrue($isOverlap('08:00', '08:30', '08:00', '08:30'));

        // Case 2: Adjacent intervals (slot ends exactly when next starts) -> NO overlap
        TestRunner::assertFalse($isOverlap('08:00', '08:30', '08:30', '09:00'));
        TestRunner::assertFalse($isOverlap('08:30', '09:00', '08:00', '08:30'));

        // Case 3: Partial overlap (slot starts before and ends during existing block)
        TestRunner::assertTrue($isOverlap('08:00', '09:00', '08:30', '09:30'));

        // Case 4: Partial overlap (slot starts during and ends after existing block)
        TestRunner::assertTrue($isOverlap('08:30', '09:30', '08:00', '09:00'));

        // Case 5: Enclosure (outer surrounds inner)
        TestRunner::assertTrue($isOverlap('08:00', '11:00', '09:00', '10:00'));
        TestRunner::assertTrue($isOverlap('09:00', '10:00', '08:00', '11:00'));

        // Case 6: Completely separated intervals -> NO overlap
        TestRunner::assertFalse($isOverlap('08:00', '08:30', '14:00', '14:30'));
    });

    TestRunner::test('30-minute interval generation between business hours', function() {
        $start = strtotime('2026-10-05 08:00:00');
        $end = strtotime('2026-10-05 10:00:00');
        $step = 30 * 60; // 30 mins
        $duration = 30 * 60;

        $slots = [];
        for ($curr = $start; $curr < $end; $curr += $step) {
            if ($curr + $duration <= $end) {
                $slots[] = date('H:i', $curr);
            }
        }

        TestRunner::assertEquals(['08:00', '08:30', '09:00', '09:30'], $slots);
    });

    TestRunner::test('Service duration exceeding closing time is excluded from slots', function() {
        $start = strtotime('2026-10-05 18:00:00');
        $close = strtotime('2026-10-05 19:30:00');
        $step = 30 * 60;
        $longDuration = 45 * 60; // 45-minute service (e.g. Implant / complex surgery)

        $slots = [];
        for ($curr = $start; $curr < $close; $curr += $step) {
            if ($curr + $longDuration <= $close) {
                $slots[] = date('H:i', $curr);
            }
        }

        // 18:00 -> 18:45 <= 19:30 (included)
        // 18:30 -> 19:15 <= 19:30 (included)
        // 19:00 -> 19:45 > 19:30 (excluded)
        TestRunner::assertEquals(['18:00', '18:30'], $slots);
    });

    TestRunner::test('Weekday numbering matches PHP w format (0 = Sunday ... 6 = Saturday)', function() {
        // Monday: 2026-10-05
        $monday = (int)date('w', strtotime('2026-10-05'));
        TestRunner::assertEquals(1, $monday, 'Monday must be 1');

        // Wednesday: 2026-10-07
        $wednesday = (int)date('w', strtotime('2026-10-07'));
        TestRunner::assertEquals(3, $wednesday, 'Wednesday must be 3');

        // Saturday: 2026-10-10
        $saturday = (int)date('w', strtotime('2026-10-10'));
        TestRunner::assertEquals(6, $saturday, 'Saturday must be 6');

        // Sunday: 2026-10-11
        $sunday = (int)date('w', strtotime('2026-10-11'));
        TestRunner::assertEquals(0, $sunday, 'Sunday must be 0');
    });

    TestRunner::test('AvailabilityService returns free slots for open business day with mock repos', function() {
        $futureDate = '2026-10-20'; // Future Tuesday (weekday 2)
        $weekday = (int)date('w', strtotime($futureDate));

        $serviceRepo = new InMemoryServiceRepository([
            1 => ['id' => 1, 'name' => 'Khám tổng quát', 'duration_minutes' => 30, 'active' => 1],
        ]);
        $scheduleRepo = new InMemoryScheduleRepository([
            $weekday => ['weekday' => $weekday, 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'active' => 1],
        ]);
        $bookingRepo = new InMemoryBookingRepository();

        $service = new AvailabilityService($serviceRepo, $scheduleRepo, $bookingRepo);
        $slots = $service->getAvailableSlots(1, $futureDate);

        TestRunner::assertEquals(['08:00', '08:30', '09:00', '09:30'], $slots);
    });

    TestRunner::test('AvailabilityService returns empty slots when clinic is closed on that weekday', function() {
        $futureDate = '2026-10-25'; // Future Sunday (weekday 0)
        $weekday = (int)date('w', strtotime($futureDate));

        $serviceRepo = new InMemoryServiceRepository([
            1 => ['id' => 1, 'name' => 'Khám tổng quát', 'duration_minutes' => 30, 'active' => 1],
        ]);
        // Clinic closed on Sunday (active = 0)
        $scheduleRepo = new InMemoryScheduleRepository([
            $weekday => ['weekday' => $weekday, 'start_time' => '08:00:00', 'end_time' => '18:00:00', 'active' => 0],
        ]);
        $bookingRepo = new InMemoryBookingRepository();

        $service = new AvailabilityService($serviceRepo, $scheduleRepo, $bookingRepo);
        $slots = $service->getAvailableSlots(1, $futureDate);

        TestRunner::assertEquals([], $slots, 'Closed weekday must yield no slots');
    });

    TestRunner::test('AvailabilityService filters out slots during schedule blocks', function() {
        $futureDate = '2026-10-20';
        $weekday = (int)date('w', strtotime($futureDate));

        $serviceRepo = new InMemoryServiceRepository([
            1 => ['id' => 1, 'name' => 'Khám tổng quát', 'duration_minutes' => 30, 'active' => 1],
        ]);
        $scheduleRepo = new InMemoryScheduleRepository(
            [$weekday => ['weekday' => $weekday, 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'active' => 1]],
            // Block from 08:30 to 09:30 (e.g. clinic staff meeting)
            [$futureDate => [
                ['start_datetime' => "$futureDate 08:30:00", 'end_datetime' => "$futureDate 09:30:00", 'reason' => 'Họp giao ban']
            ]]
        );
        $bookingRepo = new InMemoryBookingRepository();

        $service = new AvailabilityService($serviceRepo, $scheduleRepo, $bookingRepo);
        $slots = $service->getAvailableSlots(1, $futureDate);

        // 08:00-08:30 is free, 08:30-09:00 is blocked, 09:00-09:30 is blocked, 09:30-10:00 is free
        TestRunner::assertEquals(['08:00', '09:30'], $slots);
    });

    TestRunner::test('AvailabilityService filters out slots during existing active bookings', function() {
        $futureDate = '2026-10-20';
        $weekday = (int)date('w', strtotime($futureDate));

        $serviceRepo = new InMemoryServiceRepository([
            1 => ['id' => 1, 'name' => 'Khám tổng quát', 'duration_minutes' => 30, 'active' => 1],
        ]);
        $scheduleRepo = new InMemoryScheduleRepository([
            $weekday => ['weekday' => $weekday, 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'active' => 1],
        ]);
        // Booking from 08:30 to 09:00
        $bookingRepo = new InMemoryBookingRepository([
            $futureDate => [
                ['id' => 101, 'start_time' => '08:30:00', 'end_time' => '09:00:00'],
            ]
        ]);

        $service = new AvailabilityService($serviceRepo, $scheduleRepo, $bookingRepo);
        $slots = $service->getAvailableSlots(1, $futureDate);

        TestRunner::assertEquals(['08:00', '09:00', '09:30'], $slots);
    });

    TestRunner::test('AvailabilityService rejects dates in the past and malformed date strings', function() {
        $serviceRepo = new InMemoryServiceRepository([
            1 => ['id' => 1, 'name' => 'Khám', 'duration_minutes' => 30, 'active' => 1],
        ]);
        $scheduleRepo = new InMemoryScheduleRepository();
        $bookingRepo = new InMemoryBookingRepository();
        $service = new AvailabilityService($serviceRepo, $scheduleRepo, $bookingRepo);

        // Past date
        TestRunner::assertEquals([], $service->getAvailableSlots(1, '2020-01-01'), 'Past date must return empty slots');

        // Malformed dates
        TestRunner::assertEquals([], $service->getAvailableSlots(1, 'invalid-date'));
        TestRunner::assertEquals([], $service->getAvailableSlots(1, '2026/10/20'));
        TestRunner::assertEquals([], $service->getAvailableSlots(1, '20-10-2026'));
    });

    TestRunner::test('AvailabilityService::isSlotAvailable verifies availability and reschedule exclusions', function() {
        $futureDate = '2026-10-20';
        $weekday = (int)date('w', strtotime($futureDate));

        $serviceRepo = new InMemoryServiceRepository([
            1 => ['id' => 1, 'name' => 'Khám', 'duration_minutes' => 30, 'active' => 1],
        ]);
        $scheduleRepo = new InMemoryScheduleRepository([
            $weekday => ['weekday' => $weekday, 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'active' => 1],
        ]);
        // Existing booking #42 at 08:30-09:00
        $bookingRepo = new InMemoryBookingRepository([
            $futureDate => [
                ['id' => 42, 'start_time' => '08:30:00', 'end_time' => '09:00:00'],
            ]
        ]);

        $service = new AvailabilityService($serviceRepo, $scheduleRepo, $bookingRepo);

        // 08:00 is free
        TestRunner::assertTrue($service->isSlotAvailable(1, $futureDate, '08:00'));

        // 08:30 is booked -> false for regular user
        TestRunner::assertFalse($service->isSlotAvailable(1, $futureDate, '08:30'));

        // 08:30 is available when excluding booking #42 itself (admin reschedule)
        TestRunner::assertTrue($service->isSlotAvailable(1, $futureDate, '08:30', 42));

        // 08:30 is NOT available when excluding a different booking #99
        TestRunner::assertFalse($service->isSlotAvailable(1, $futureDate, '08:30', 99));
    });
}
