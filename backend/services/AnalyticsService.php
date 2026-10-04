<?php
// backend/services/AnalyticsService.php

require_once __DIR__ . '/../repositories/AnalyticsRepository.php';

class AnalyticsService {
    private AnalyticsRepository $repo;

    public function __construct() {
        $this->repo = new AnalyticsRepository();
    }

    /**
     * Process tracking beacon from frontend visitor
     */
    public function track(array $payload): void {
        $sessionId = trim($payload['session_id'] ?? '');
        if (!$sessionId) {
            // Check cookie
            $sessionId = $_COOKIE['kd_sid'] ?? bin2hex(random_bytes(16));
        }

        $path = substr(trim($payload['path'] ?? '/'), 0, 255);
        $referrer = substr(trim($payload['referrer'] ?? ($_SERVER['HTTP_REFERER'] ?? '')), 0, 500);

        // UTM classification
        $utmSource   = substr(trim($payload['utm_source'] ?? ''), 0, 100);
        $utmMedium   = substr(trim($payload['utm_medium'] ?? ''), 0, 100);
        $utmCampaign = substr(trim($payload['utm_campaign'] ?? ''), 0, 100);
        $utmContent  = substr(trim($payload['utm_content'] ?? ''), 0, 100);
        $utmTerm     = substr(trim($payload['utm_term'] ?? ''), 0, 100);

        // If no UTM source provided, infer from referrer
        if (empty($utmSource)) {
            $utmSource = $this->classifyReferrer($referrer);
        }

        // Record or update session
        $this->repo->recordSession([
            'session_id'   => $sessionId,
            'first_page'   => $path,
            'referrer'     => $referrer,
            'utm_source'   => $utmSource,
            'utm_medium'   => $utmMedium,
            'utm_campaign' => $utmCampaign,
            'utm_content'  => $utmContent,
            'utm_term'     => $utmTerm,
        ]);

        // Record page view
        $this->repo->recordPageView($sessionId, $path);
    }

    /**
     * Infer organic traffic source from referrer string
     */
    public function classifyReferrer(string $referrer): string {
        if (empty($referrer)) {
            return 'direct';
        }

        $host = strtolower(parse_url($referrer, PHP_URL_HOST) ?? '');
        if (empty($host)) {
            return 'direct';
        }

        if (str_contains($host, 'google.'))    return 'google';
        if (str_contains($host, 'facebook.') || str_contains($host, 'fb.com') || str_contains($host, 'm.me')) return 'facebook';
        if (str_contains($host, 'instagram.')) return 'instagram';
        if (str_contains($host, 'tiktok.'))    return 'tiktok';
        if (str_contains($host, 'zalo.'))      return 'zalo';
        if (str_contains($host, 'coccoc.'))    return 'coccoc';
        if (str_contains($host, 'bing.'))      return 'bing';
        if (str_contains($host, 'youtube.'))   return 'youtube';

        return 'referral';
    }
}
