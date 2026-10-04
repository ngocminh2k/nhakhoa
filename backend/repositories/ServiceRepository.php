<?php
// backend/repositories/ServiceRepository.php

require_once __DIR__ . '/../core/Database.php';

class ServiceRepository {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function getActiveServices(): array {
        $stmt = $this->db->query('
            SELECT id, name, slug, description, duration_minutes, price, active, sort_order
            FROM services
            WHERE active = 1
            ORDER BY sort_order ASC, id ASC
        ');
        return $stmt->fetchAll();
    }

    public function getAllServices(): array {
        $stmt = $this->db->query('
            SELECT id, name, slug, description, duration_minutes, price, active, sort_order, created_at, updated_at
            FROM services
            ORDER BY sort_order ASC, id ASC
        ');
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare('SELECT * FROM services WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public function findBySlug(string $slug): ?array {
        $stmt = $this->db->prepare('SELECT * FROM services WHERE slug = :slug LIMIT 1');
        $stmt->execute([':slug' => $slug]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare('
            INSERT INTO services (name, slug, description, duration_minutes, price, active, sort_order)
            VALUES (:name, :slug, :description, :duration_minutes, :price, :active, :sort_order)
        ');
        $stmt->execute([
            ':name'             => $data['name'],
            ':slug'             => $data['slug'],
            ':description'      => $data['description'] ?? null,
            ':duration_minutes' => (int)($data['duration_minutes'] ?? 30),
            ':price'            => $data['price'] ?? 0,
            ':active'           => (int)($data['active'] ?? 1),
            ':sort_order'       => (int)($data['sort_order'] ?? 0),
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $fields = [];
        $params = [':id' => $id];

        foreach (['name', 'slug', 'description', 'duration_minutes', 'price', 'active', 'sort_order'] as $col) {
            if (array_key_exists($col, $data)) {
                $fields[] = "`$col` = :$col";
                $params[":$col"] = $data[$col];
            }
        }

        if (empty($fields)) return false;

        $sql = 'UPDATE services SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
}
