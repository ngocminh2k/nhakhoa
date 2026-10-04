<?php
// tests/AvailabilityLogicTest.php
// Pure logic tests for availability calculations and slot stepping

require_once __DIR__ . '/TestRunner.php';

function runAvailabilityLogicTests(): void {
    echo "\n--- [Suite: AvailabilityLogicTest] ---\n";

    TestRunner::test('Time interval overlap logic', function() {
        // Overlap function: (start1 < end2) && (end1 > start2)
        $isOverlap = function(string $s1, string $e1, string $s2, string $e2): bool {
            return ($s1 < $e2) && ($e1 > $s2);
        };

        // Case 1: Exact same slot -> Overlap
        TestRunner::assertTrue($isOverlap('08:00', '08:30', '08:00', '08:30'));

        // Case 2: Adjacent slots -> No overlap (08:30 ends when 08:30 starts)
        TestRunner::assertFalse($isOverlap('08:00', '08:30', '08:30', '09:00'));
        TestRunner::assertFalse($isOverlap('08:30', '09:00', '08:00', '08:30'));

        // Case 3: Partial overlap
        TestRunner::assertTrue($isOverlap('08:00', '09:00', '08:30', '09:30'));

        // Case 4: Enclosing slot
        TestRunner::assertTrue($isOverlap('08:00', '10:00', '08:30', '09:00'));

        // Case 5: Completely separate
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

    TestRunner::test('Duration exceeding closing time is excluded', function() {
        $start = strtotime('2026-10-05 19:00:00');
        $close = strtotime('2026-10-05 19:30:00');
        $step = 30 * 60;
        $longDuration = 45 * 60; // 45 mins (Implant)

        $slots = [];
        for ($curr = $start; $curr < $close; $curr += $step) {
            if ($curr + $longDuration <= $close) {
                $slots[] = date('H:i', $curr);
            }
        }

        // At 19:00, 19:00 + 45m = 19:45 > 19:30, so no slots fit
        TestRunner::assertEquals([], $slots, 'Slots that overrun closing time must not be generated');
    });

    TestRunner::test('Weekday numbering matches PHP date format', function() {
        // Test known day: 2026-10-05 is a Monday (weekday 1)
        $monday = (int)date('w', strtotime('2026-10-05'));
        TestRunner::assertEquals(1, $monday, 'Monday must be 1');

        // 2026-10-11 is a Sunday (weekday 0)
        $sunday = (int)date('w', strtotime('2026-10-11'));
        TestRunner::assertEquals(0, $sunday, 'Sunday must be 0');
    });
}
