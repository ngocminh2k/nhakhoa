<?php
require_once __DIR__ . '/../backend/core/Database.php';

$db = Database::getConnection();
$plainKey = 'kd_batch_publisher_secret_key_2026';
$hash = hash('sha256', $plainKey);

$stmt = $db->prepare('SELECT id, active FROM api_keys WHERE key_hash = :h');
$stmt->execute([':h' => $hash]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    $ins = $db->prepare('INSERT INTO api_keys (name, key_hash, active, created_at) VALUES (:name, :hash, 1, NOW())');
    $ins->execute([':name' => 'Auto Batch 100 Publisher Key', ':hash' => $hash]);
    echo "CREATED: $plainKey\n";
} else {
    if (!$row['active']) {
        $db->prepare('UPDATE api_keys SET active = 1 WHERE id = :id')->execute([':id' => $row['id']]);
        echo "RE-ACTIVATED: $plainKey\n";
    } else {
        echo "EXISTS_ACTIVE: $plainKey\n";
    }
}
