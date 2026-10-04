<?php
// backend/services/GoogleSheetsService.php

class GoogleSheetsService {
    private array $config;
    private ?string $lastError = null;

    public function __construct() {
        $appConfig = require __DIR__ . '/../config/app.php';
        $this->config = $appConfig['google'] ?? [];
    }

    public function getLastError(): ?string {
        return $this->lastError;
    }

    public function isEnabled(): bool {
        return !empty($this->config['enabled'])
            && !empty($this->config['spreadsheet_id'])
            && file_exists($this->config['key_file_path'] ?? '');
    }

    /**
     * Append booking row to Google Sheets
     * Returns ['success' => bool, 'error' => ?string]
     */
    public function appendBooking(array $booking): array {
        $this->lastError = null;
        if (!$this->isEnabled()) {
            $this->lastError = 'Google Sheets integration is disabled or credentials file missing.';
            return [
                'success' => false,
                'error'   => $this->lastError,
            ];
        }

        try {
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                $this->lastError = 'Failed to obtain Google OAuth access token.';
                return ['success' => false, 'error' => $this->lastError];
            }

            $spreadsheetId = $this->config['spreadsheet_id'];
            $range = urlencode($this->config['sheet_range'] ?: 'Bookings!A:N');
            $url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/{$range}:append?valueInputOption=USER_ENTERED";

            // Format row as defined in Section 27 of plan:
            // Booking Code, Created At, Customer, Phone, Notes, Service, Date, Start Time, End Time, Status, Source Page, Source, Campaign
            $rowValues = [
                $booking['booking_code'] ?? '',
                $booking['created_at'] ?? date('Y-m-d H:i:s'),
                $booking['customer_name'] ?? '',
                $booking['phone'] ?? '',
                $booking['notes'] ?? '',
                $booking['service_name'] ?? ('Service #' . ($booking['service_id'] ?? '')),
                $booking['booking_date'] ?? '',
                $booking['start_time'] ?? '',
                $booking['end_time'] ?? '',
                $booking['status'] ?? 'new',
                $booking['source_page'] ?? '',
                $booking['utm_source'] ?? ($booking['referrer'] ?? 'direct'),
                $booking['utm_campaign'] ?? '',
                $booking['session_id'] ?? '',
            ];

            $payload = json_encode(['values' => [$rowValues]]);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/json',
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                $this->lastError = 'cURL error: ' . $curlError;
                return ['success' => false, 'error' => $this->lastError];
            }

            if ($httpCode >= 200 && $httpCode < 300) {
                return ['success' => true, 'error' => null];
            }

            $this->lastError = "HTTP $httpCode: $response";
            return ['success' => false, 'error' => $this->lastError];
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            return ['success' => false, 'error' => $this->lastError];
        }
    }

    /**
     * Generate OAuth2 Access Token from Google Service Account JSON using native OpenSSL
     */
    private function getAccessToken(): ?string {
        $keyPath = $this->config['key_file_path'];
        if (!file_exists($keyPath)) return null;

        $keyData = json_decode(file_get_contents($keyPath), true);
        if (!$keyData || empty($keyData['private_key']) || empty($keyData['client_email'])) {
            return null;
        }

        $now = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claim = [
            'iss'   => $keyData['client_email'],
            'scope' => 'https://www.googleapis.com/auth/spreadsheets',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'exp'   => $now + 3600,
            'iat'   => $now,
        ];

        $b64Header = $this->base64UrlEncode(json_encode($header));
        $b64Claim  = $this->base64UrlEncode(json_encode($claim));
        $dataToSign = "$b64Header.$b64Claim";

        $signature = '';
        $success = openssl_sign($dataToSign, $signature, $keyData['private_key'], OPENSSL_ALGO_SHA256);
        if (!$success) return null;

        $jwt = "$dataToSign." . $this->base64UrlEncode($signature);

        // Exchange JWT for token
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $res = curl_exec($ch);
        curl_close($ch);

        $json = json_decode($res, true);
        return $json['access_token'] ?? null;
    }

    private function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
