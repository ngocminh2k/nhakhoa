<?php
require_once __DIR__ . '/../backend/core/Database.php';
$db = Database::getConnection();
$stmt = $db->query('SELECT count(*) as total FROM posts');
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "TOTAL_POSTS: " . $row['total'] . "\n";

$byCat = $db->query("
    SELECT
        SUM(CASE WHEN external_id LIKE 'batch100%' THEN 1 ELSE 0 END) AS batch100,
        SUM(CASE WHEN external_id NOT LIKE 'batch100%' OR external_id IS NULL THEN 1 ELSE 0 END) AS others
    FROM posts
")->fetch(PDO::FETCH_ASSOC);
echo "BATCH_100_POSTS: " . $byCat['batch100'] . "\n";
echo "PREVIOUS_POSTS: " . $byCat['others'] . "\n";
