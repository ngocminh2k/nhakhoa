<?php
// tests/RateLimiterTest.php

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../backend/security/RateLimiter.php';

function runRateLimiterTests(): void {
    echo "\n--- [Suite: RateLimiterTest] ---\n";

    TestRunner::test('RateLimiter enforces maximum limit and blocks subsequent attempts', function() {
        $action = 'test_limit_' . bin2hex(random_bytes(4));
        $ip = '192.168.1.100';
        $max = 3;
        $window = 10;

        RateLimiter::reset($action, $ip);

        // Attempts 1, 2, 3 must pass
        TestRunner::assertTrue(RateLimiter::check($action, $max, $window, $ip), 'Attempt 1 must pass');
        TestRunner::assertTrue(RateLimiter::check($action, $max, $window, $ip), 'Attempt 2 must pass');
        TestRunner::assertTrue(RateLimiter::check($action, $max, $window, $ip), 'Attempt 3 must pass');

        // Attempt 4 must fail (rate limit reached)
        TestRunner::assertFalse(RateLimiter::check($action, $max, $window, $ip), 'Attempt 4 must fail');
        TestRunner::assertFalse(RateLimiter::check($action, $max, $window, $ip), 'Attempt 5 must also fail');

        RateLimiter::reset($action, $ip);
    });

    TestRunner::test('RateLimiter isolates limits by IP address', function() {
        $action = 'test_ip_iso_' . bin2hex(random_bytes(4));
        $ipA = '10.0.0.1';
        $ipB = '10.0.0.2';
        $max = 2;
        $window = 10;

        RateLimiter::reset($action, $ipA);
        RateLimiter::reset($action, $ipB);

        // Exhaust IP A
        TestRunner::assertTrue(RateLimiter::check($action, $max, $window, $ipA));
        TestRunner::assertTrue(RateLimiter::check($action, $max, $window, $ipA));
        TestRunner::assertFalse(RateLimiter::check($action, $max, $window, $ipA), 'IP A should now be rate limited');

        // IP B must NOT be blocked
        TestRunner::assertTrue(RateLimiter::check($action, $max, $window, $ipB), 'IP B should still pass');
        TestRunner::assertTrue(RateLimiter::check($action, $max, $window, $ipB), 'IP B attempt 2 should still pass');
        TestRunner::assertFalse(RateLimiter::check($action, $max, $window, $ipB), 'IP B should now be blocked');

        RateLimiter::reset($action, $ipA);
        RateLimiter::reset($action, $ipB);
    });

    TestRunner::test('RateLimiter isolates limits by action name', function() {
        $actionLogin = 'login_' . bin2hex(random_bytes(4));
        $actionBooking = 'booking_' . bin2hex(random_bytes(4));
        $ip = '172.16.0.5';
        $max = 1;
        $window = 10;

        RateLimiter::reset($actionLogin, $ip);
        RateLimiter::reset($actionBooking, $ip);

        // Exhaust actionLogin
        TestRunner::assertTrue(RateLimiter::check($actionLogin, $max, $window, $ip));
        TestRunner::assertFalse(RateLimiter::check($actionLogin, $max, $window, $ip));

        // actionBooking for same IP must pass
        TestRunner::assertTrue(RateLimiter::check($actionBooking, $max, $window, $ip), 'Different action on same IP must pass');

        RateLimiter::reset($actionLogin, $ip);
        RateLimiter::reset($actionBooking, $ip);
    });

    TestRunner::test('RateLimiter::reset clears the attempt counter immediately', function() {
        $action = 'test_reset_' . bin2hex(random_bytes(4));
        $ip = '192.168.10.50';
        $max = 1;
        $window = 60;

        // Exhaust limit
        TestRunner::assertTrue(RateLimiter::check($action, $max, $window, $ip));
        TestRunner::assertFalse(RateLimiter::check($action, $max, $window, $ip));

        // Reset
        RateLimiter::reset($action, $ip);

        // Now should pass again
        TestRunner::assertTrue(RateLimiter::check($action, $max, $window, $ip), 'Must pass after reset');

        RateLimiter::reset($action, $ip);
    });

    TestRunner::test('RateLimiter expires old window automatically', function() {
        $action = 'test_expiry_' . bin2hex(random_bytes(4));
        $ip = '192.168.20.10';
        $max = 2;
        $window = 10; // 10 seconds

        RateLimiter::reset($action, $ip);

        // Use up attempts
        TestRunner::assertTrue(RateLimiter::check($action, $max, $window, $ip));
        TestRunner::assertTrue(RateLimiter::check($action, $max, $window, $ip));
        TestRunner::assertFalse(RateLimiter::check($action, $max, $window, $ip));

        // Manually simulate window expiration by setting reset_at in the past
        $key = md5($action . '_' . $ip);
        $file = sys_get_temp_dir() . '/kd_rate_limits/' . $key . '.json';
        TestRunner::assertTrue(file_exists($file), 'Rate limit state file must exist');

        $expiredData = ['attempts' => 2, 'reset_at' => time() - 5];
        file_put_contents($file, json_encode($expiredData));

        // Check should now pass because window expired
        TestRunner::assertTrue(RateLimiter::check($action, $max, $window, $ip), 'Must pass after window expiration');

        RateLimiter::reset($action, $ip);
    });

    TestRunner::test('RateLimiter::getClientIp uses only REMOTE_ADDR (spoof-safe for shared hosting)', function() {
        $backup = $_SERVER;

        // Only REMOTE_ADDR is trusted — HTTP_CF_CONNECTING_IP and HTTP_X_FORWARDED_FOR
        // are ignored to prevent IP spoofing via attacker-controlled headers.
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.1';  // should be ignored
        $_SERVER['HTTP_X_FORWARDED_FOR']  = '198.51.100.99, 10.0.0.1'; // should be ignored
        $_SERVER['REMOTE_ADDR']           = '192.0.2.55';
        TestRunner::assertEquals('192.0.2.55', RateLimiter::getClientIp(), 'Must use REMOTE_ADDR even when proxy headers present');

        // Fallback when REMOTE_ADDR is unset
        unset($_SERVER['REMOTE_ADDR']);
        TestRunner::assertEquals('127.0.0.1', RateLimiter::getClientIp(), 'Must fall back to 127.0.0.1 when REMOTE_ADDR is absent');

        $_SERVER = $backup;
    });
}
