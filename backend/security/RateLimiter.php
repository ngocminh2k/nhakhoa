<?php
// backend/security/RateLimiter.php

class RateLimiter {
    private static function getStorageDir(): string {
        $dir = sys_get_temp_dir() . '/kd_rate_limits';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }

    public static function getClientIp(): string {
        // Always use REMOTE_ADDR as the canonical IP.
        // On Mắt Bão / cPanel shared hosting, REMOTE_ADDR is the actual connecting IP
        // from the load balancer (already unwrapped by LiteSpeed/Apache on the server side).
        // Trusting HTTP_X_FORWARDED_FOR or HTTP_CF_CONNECTING_IP headers from untrusted
        // downstream requests would allow an attacker to spoof their IP and bypass rate limits.
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Check if client exceeded limit. If not, record attempt.
     * Uses atomic file lock (LOCK_EX) across read-modify-write to eliminate concurrency race conditions.
     */
    public static function check(string $action, int $maxAttempts, int $decaySeconds, ?string $customIp = null): bool {
        $ip = $customIp ?? self::getClientIp();
        $key = md5($action . '_' . $ip);
        $file = self::getStorageDir() . '/' . $key . '.json';

        $fp = @fopen($file, 'c+');
        if (!$fp) {
            return true; // Fail open if storage is unwriteable
        }

        flock($fp, LOCK_EX);

        $now = time();
        $data = ['attempts' => 0, 'reset_at' => $now + $decaySeconds];

        clearstatcache(true, $file);
        $size = @filesize($file);
        if ($size && $size > 0) {
            rewind($fp);
            $content = fread($fp, $size);
            $parsed = json_decode($content, true);
            if ($parsed && isset($parsed['reset_at']) && $parsed['reset_at'] > $now) {
                $data = $parsed;
            }
        }

        if ($data['attempts'] >= $maxAttempts) {
            flock($fp, LOCK_UN);
            fclose($fp);
            return false; // Rate limit exceeded
        }

        $data['attempts']++;
        rewind($fp);
        ftruncate($fp, 0);
        fwrite($fp, json_encode($data));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    }

    /**
     * Global rate limiter (across all rotating IPs) to protect against distributed botnet DDoS
     */
    public static function checkGlobal(string $action, int $maxAttempts, int $decaySeconds): bool {
        return self::check($action, $maxAttempts, $decaySeconds, 'global_circuit_breaker');
    }

    /**
     * Reset rate limit on success
     */
    public static function reset(string $action, ?string $customIp = null): void {
        $ip = $customIp ?? self::getClientIp();
        $key = md5($action . '_' . $ip);
        $file = self::getStorageDir() . '/' . $key . '.json';
        if (file_exists($file)) {
            @unlink($file);
        }
    }
}
