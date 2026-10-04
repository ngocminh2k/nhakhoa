<?php
// tests/CsrfTest.php

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../backend/security/Csrf.php';

function runCsrfTests(): void {
    echo "\n--- [Suite: CsrfTest] ---\n";

    TestRunner::test('Csrf generates 64-character hex token and maintains session stability', function() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        unset($_SESSION['kd_csrf_token']);

        $token1 = Csrf::generateToken();
        TestRunner::assertEquals(64, strlen($token1), 'Token should be 64 characters (32 bytes hex)');
        TestRunner::assertMatches('/^[a-f0-9]{64}$/', $token1, 'Token must be valid hexadecimal');

        // Calling generateToken again in same session should return identical token
        $token2 = Csrf::generateToken();
        TestRunner::assertEquals($token1, $token2, 'Token should remain stable within session');
    });

    TestRunner::test('Csrf::verifyToken verifies valid token successfully', function() {
        $token = Csrf::generateToken();
        TestRunner::assertTrue(Csrf::verifyToken($token), 'Valid session token must verify to true');
    });

    TestRunner::test('Csrf::verifyToken rejects invalid, empty, null, and tampered tokens', function() {
        $validToken = Csrf::generateToken();

        // 1. Null token
        TestRunner::assertFalse(Csrf::verifyToken(null), 'Null token must be rejected');

        // 2. Empty token
        TestRunner::assertFalse(Csrf::verifyToken(''), 'Empty token must be rejected');

        // 3. Completely bogus token
        TestRunner::assertFalse(Csrf::verifyToken('invalid_token_12345'), 'Bogus token must be rejected');

        // 4. Same length (64 hex) but wrong value
        $wrongToken = bin2hex(random_bytes(32));
        TestRunner::assertFalse(Csrf::verifyToken($wrongToken), 'Different 64-char hex token must be rejected');

        // 5. Truncated token (missing last char)
        $truncated = substr($validToken, 0, -1);
        TestRunner::assertFalse(Csrf::verifyToken($truncated), 'Truncated token must be rejected');

        // 6. Token with appended char
        TestRunner::assertFalse(Csrf::verifyToken($validToken . 'a'), 'Extended token must be rejected');
    });

    TestRunner::test('Csrf::verifyToken fails when no session token is stored', function() {
        unset($_SESSION['kd_csrf_token']);
        TestRunner::assertFalse(Csrf::verifyToken('some_valid_looking_token_12345'), 'Must fail when session token is unset');
    });

    TestRunner::test('Csrf token rotation invalidates old tokens', function() {
        unset($_SESSION['kd_csrf_token']);
        $oldToken = Csrf::generateToken();
        TestRunner::assertTrue(Csrf::verifyToken($oldToken));

        // Simulate session rotation / regeneration
        unset($_SESSION['kd_csrf_token']);
        $newToken = Csrf::generateToken();

        TestRunner::assertNotEquals($oldToken, $newToken, 'Regenerated token must differ from old token');
        TestRunner::assertFalse(Csrf::verifyToken($oldToken), 'Old token must be rejected after rotation');
        TestRunner::assertTrue(Csrf::verifyToken($newToken), 'New token must be accepted');
    });
}
