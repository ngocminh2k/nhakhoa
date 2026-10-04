<?php
// backend/repositories/ApiKeyRepository.php

require_once __DIR__ . '/../core/Database.php';

class ApiKeyRepository {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function getAll(): array {
        $stmt = $this->db->query('
            SELECT id, name, key_hash, active, last_used_at, created_at
            FROM api_keys
            ORDER BY created_at DESC
        ');
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare('SELECT * FROM api_keys WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(string $name, string $plainKey): int {
        $hash = hash('sha256', $plainKey);
        $stmt = $this->db->prepare('
            INSERT INTO api_keys (name, key_hash, active, created_at)
            VALUES (:name, :hash, 1, NOW())
        ');
        $stmt->execute([
            ':name' => $name,
            ':hash' => $hash,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function toggleActive(int $id, bool $active): bool {
        $stmt = $this->db->prepare('UPDATE api_keys SET active = :active WHERE id = :id');
        return $stmt->execute([
            ':active' => $active ? 1 : 0,
            ':id'     => $id,
        ]);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare('DELETE FROM api_keys WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    public static function generateSecureKey(): string {
        return 'kd_' . bin2hex(random_bytes(24));
    }
}
