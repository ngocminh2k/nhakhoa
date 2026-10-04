<?php
// backend/config/app.php
// Application settings

return [
    'name'        => 'Nha Khoa Kim Dung',
    'timezone'    => 'Asia/Ho_Chi_Minh',
    'base_url'    => getenv('APP_URL') ?: 'https://nhakhoakimdung.vn',

    // Google Sheets integration
    'google'      => [
        'enabled'        => filter_var(getenv('GOOGLE_SHEETS_ENABLED') ?: false, FILTER_VALIDATE_BOOLEAN),
        'spreadsheet_id' => getenv('GOOGLE_SHEET_ID') ?: '',
        'sheet_range'    => getenv('GOOGLE_SHEET_RANGE') ?: 'Bookings!A:N',
        'key_file_path'  => getenv('GOOGLE_SERVICE_ACCOUNT_JSON') ?: __DIR__ . '/../../credentials/google-service-account.json',
    ],

    // Security
    'session_name' => 'kd_admin_session',
    'csrf_token_key' => 'kd_csrf_token',
    'rate_limit' => [
        'booking_max_per_hour' => 10,
        'login_max_fails'      => 5,
        'login_lockout_min'    => 15,
    ],
];
