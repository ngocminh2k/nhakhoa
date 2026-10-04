<?php
// backend/jobs/aggregate_analytics.php
// Aggregates raw page views and sessions into daily_analytics

require_once __DIR__ . '/../repositories/AnalyticsRepository.php';

$analyticsRepo = new AnalyticsRepository();
$targetDate = $argv[1] ?? date('Y-m-d', strtotime('-1 day'));

$isCli = (php_sapi_name() === 'cli');
if ($isCli) {
    echo "[" . date('Y-m-d H:i:s') . "] Aggregating analytics for date: {$targetDate}...\n";
}

$analyticsRepo->aggregateDaily($targetDate);

if ($isCli) {
    echo "[" . date('Y-m-d H:i:s') . "] Aggregation completed.\n";
}
