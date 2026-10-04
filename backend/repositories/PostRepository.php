<?php
// backend/repositories/PostRepository.php

require_once __DIR__ . '/../core/Database.php';

class PostRepository {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function findByExternalId(string $externalId): ?array {
        $stmt = $this->db->prepare('SELECT * FROM posts WHERE external_id = :ext_id LIMIT 1');
        $stmt->execute([':ext_id' => $externalId]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public function findBySlug(string $slug): ?array {
        $stmt = $this->db->prepare('SELECT * FROM posts WHERE slug = :slug LIMIT 1');
        $stmt->execute([':slug' => $slug]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare('SELECT * FROM posts WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public function upsert(array $data): array {
        // If external_id exists, check if already present
        if (!empty($data['external_id'])) {
            $existing = $this->findByExternalId($data['external_id']);
            if ($existing) {
                $this->update($existing['id'], $data);
                return ['post_id' => $existing['id'], 'action' => 'updated', 'slug' => $existing['slug']];
            }
        }

        // Check if slug exists
        $existingSlug = $this->findBySlug($data['slug']);
        if ($existingSlug) {
            // If same external_id OR if no external_id was specified, update existing post in place
            if (empty($data['external_id']) || $existingSlug['external_id'] === $data['external_id']) {
                $this->update($existingSlug['id'], $data);
                return ['post_id' => $existingSlug['id'], 'action' => 'updated', 'slug' => $existingSlug['slug']];
            } else {
                // Different external_id with colliding slug -> disambiguate slug
                $data['slug'] = $data['slug'] . '-' . substr(bin2hex(random_bytes(3)), 0, 4);
            }
        }

        // Insert new post
        $stmt = $this->db->prepare('
            INSERT INTO posts (external_id, title, slug, excerpt, content, featured_image, status, published_at)
            VALUES (:external_id, :title, :slug, :excerpt, :content, :featured_image, :status, :published_at)
        ');
        $publishedAt = ($data['status'] === 'published') ? date('Y-m-d H:i:s') : null;
        $stmt->execute([
            ':external_id'    => $data['external_id'] ?? null,
            ':title'          => $data['title'],
            ':slug'           => $data['slug'],
            ':excerpt'        => $data['excerpt'] ?? null,
            ':content'        => $data['content'],
            ':featured_image' => $data['featured_image'] ?? null,
            ':status'         => $data['status'] ?? 'published',
            ':published_at'   => $publishedAt,
        ]);
        $newId = (int)$this->db->lastInsertId();
        return ['post_id' => $newId, 'action' => 'created', 'slug' => $data['slug']];
    }

    public function update(int $id, array $data): bool {
        $fields = [];
        $params = [':id' => $id];

        foreach (['title', 'slug', 'excerpt', 'content', 'featured_image', 'status'] as $col) {
            if (array_key_exists($col, $data)) {
                $fields[] = "`$col` = :$col";
                $params[":$col"] = $data[$col];
            }
        }

        if (empty($fields)) return false;

        $sql = 'UPDATE posts SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare('DELETE FROM posts WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    public function countPublished(): int {
        $stmt = $this->db->query("SELECT COUNT(*) FROM posts WHERE status = 'published'");
        return (int)$stmt->fetchColumn();
    }

    public function countAll(): int {
        $stmt = $this->db->query("SELECT COUNT(*) FROM posts");
        return (int)$stmt->fetchColumn();
    }

    public function getPublishedPosts(int $limit = 20, int $offset = 0): array {
        $stmt = $this->db->prepare("
            SELECT id, title, slug, excerpt, featured_image, published_at, created_at
            FROM posts
            WHERE status = 'published'
            ORDER BY published_at DESC, id DESC
            LIMIT :offset, :limit
        ");
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getAllPosts(int $limit = 50, int $offset = 0): array {
        $stmt = $this->db->prepare('
            SELECT id, external_id, title, slug, excerpt, featured_image, status, published_at, created_at
            FROM posts
            ORDER BY id DESC
            LIMIT :offset, :limit
        ');
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
