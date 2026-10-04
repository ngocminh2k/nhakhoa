<?php
require_once __DIR__ . '/../backend/core/Database.php';

$db = Database::getConnection();
$email = 'admin@nhakhoakimdung.com';
$password = 'AdminKimDung@2026!';
$hash = password_hash($password, PASSWORD_BCRYPT);

$stmt = $db->prepare('UPDATE admins SET password_hash = :hash WHERE email = :email');
$stmt->execute([':hash' => $hash, ':email' => $email]);

echo "SUCCESS: Admin {$email} password set to {$password}\n";
