<?php
// tests/TestRunner.php
// Standalone, lightweight assert-based test runner (Zero dependencies)

class TestRunner {
    private static int $passed = 0;
    private static int $failed = 0;
    private static array $failures = [];

    public static function test(string $name, callable $fn): void {
        echo "Running: {$name}... ";
        try {
            $fn();
            self::$passed++;
            echo "\033[32m[PASS]\033[0m\n";
        } catch (Throwable $e) {
            self::$failed++;
            echo "\033[31m[FAIL]\033[0m\n";
            self::$failures[] = [
                'name'    => $name,
                'message' => $e->getMessage(),
                'file'    => $e->getFile() . ':' . $e->getLine(),
            ];
        }
    }

    public static function assert(bool $condition, string $msg = 'Assertion failed'): void {
        if (!$condition) {
            throw new AssertionError($msg);
        }
    }

    public static function assertEquals(mixed $expected, mixed $actual, string $msg = ''): void {
        if ($expected !== $actual) {
            $expStr = var_export($expected, true);
            $actStr = var_export($actual, true);
            $detail = $msg ? " ({$msg})" : '';
            throw new AssertionError("Expected {$expStr}, got {$actStr}{$detail}");
        }
    }

    public static function assertNotEquals(mixed $expected, mixed $actual, string $msg = ''): void {
        if ($expected === $actual) {
            $valStr = var_export($expected, true);
            $detail = $msg ? " ({$msg})" : '';
            throw new AssertionError("Expected value to differ from {$valStr}{$detail}");
        }
    }

    public static function assertTrue(mixed $val, string $msg = ''): void {
        self::assertEquals(true, $val, $msg);
    }

    public static function assertFalse(mixed $val, string $msg = ''): void {
        self::assertEquals(false, $val, $msg);
    }

    public static function assertNull(mixed $val, string $msg = ''): void {
        if ($val !== null) {
            $actStr = var_export($val, true);
            $detail = $msg ? " ({$msg})" : '';
            throw new AssertionError("Expected null, got {$actStr}{$detail}");
        }
    }

    public static function assertNotNull(mixed $val, string $msg = ''): void {
        if ($val === null) {
            $detail = $msg ? " ({$msg})" : '';
            throw new AssertionError("Expected non-null value, got null{$detail}");
        }
    }

    public static function assertEmpty(mixed $val, string $msg = ''): void {
        if (!empty($val)) {
            $actStr = var_export($val, true);
            $detail = $msg ? " ({$msg})" : '';
            throw new AssertionError("Expected empty value, got {$actStr}{$detail}");
        }
    }

    public static function assertNotEmpty(mixed $val, string $msg = ''): void {
        if (empty($val)) {
            $detail = $msg ? " ({$msg})" : '';
            throw new AssertionError("Expected non-empty value, got empty{$detail}");
        }
    }

    public static function assertCount(int $expectedCount, Countable|array $val, string $msg = ''): void {
        $actualCount = count($val);
        if ($expectedCount !== $actualCount) {
            $detail = $msg ? " ({$msg})" : '';
            throw new AssertionError("Expected count {$expectedCount}, got {$actualCount}{$detail}");
        }
    }

    public static function assertContains(string $needle, string $haystack, string $msg = ''): void {
        if (!str_contains($haystack, $needle)) {
            $detail = $msg ? " ({$msg})" : '';
            throw new AssertionError("Expected '{$haystack}' to contain '{$needle}'{$detail}");
        }
    }

    public static function assertNotContains(string $needle, string $haystack, string $msg = ''): void {
        if (str_contains($haystack, $needle)) {
            $detail = $msg ? " ({$msg})" : '';
            throw new AssertionError("Expected '{$haystack}' NOT to contain '{$needle}'{$detail}");
        }
    }

    public static function assertMatches(string $pattern, string $subject, string $msg = ''): void {
        if (!preg_match($pattern, $subject)) {
            $detail = $msg ? " ({$msg})" : '';
            throw new AssertionError("Expected '{$subject}' to match pattern {$pattern}{$detail}");
        }
    }

    public static function assertThrows(callable $fn, string $expectedClass = Throwable::class, string $msg = ''): void {
        try {
            $fn();
        } catch (Throwable $e) {
            if ($e instanceof $expectedClass) {
                return;
            }
            throw new AssertionError("Expected exception {$expectedClass}, got " . get_class($e) . ": " . $e->getMessage());
        }
        $detail = $msg ? " ({$msg})" : '';
        throw new AssertionError("Expected exception {$expectedClass} was not thrown{$detail}");
    }

    public static function getPassed(): int {
        return self::$passed;
    }

    public static function getFailed(): int {
        return self::$failed;
    }

    public static function reset(): void {
        self::$passed = 0;
        self::$failed = 0;
        self::$failures = [];
    }

    public static function report(): int {
        echo "\n=========================================\n";
        echo "Test Results: \033[32m" . self::$passed . " Passed\033[0m, ";
        if (self::$failed > 0) {
            echo "\033[31m" . self::$failed . " Failed\033[0m\n";
            echo "-----------------------------------------\n";
            foreach (self::$failures as $idx => $f) {
                echo ($idx + 1) . ") " . $f['name'] . "\n";
                echo "   " . $f['message'] . "\n";
                echo "   at " . $f['file'] . "\n\n";
            }
            return 1;
        } else {
            echo "\033[32m0 Failed\033[0m\n";
            echo "\033[32mAll tests passed successfully!\033[0m\n";
            return 0;
        }
    }
}
