<?php
// backend/services/PostService.php

require_once __DIR__ . '/../repositories/PostRepository.php';
require_once __DIR__ . '/../security/Sanitizer.php';

class PostService {
    private PostRepository $repo;

    public function __construct() {
        $this->repo = new PostRepository();
    }

    /**
     * Process post payload from external automation
     */
    public function receiveExternalPost(array $payload): array {
        $title = Sanitizer::text($payload['title'] ?? '');
        $slug = Sanitizer::slug($payload['slug'] ?? $title);
        $excerpt = Sanitizer::text($payload['excerpt'] ?? '');
        $content = Sanitizer::html($payload['content'] ?? '');
        $featuredImage = filter_var($payload['featured_image'] ?? '', FILTER_SANITIZE_URL);
        $externalId = Sanitizer::text($payload['external_id'] ?? '');
        $status = in_array($payload['status'] ?? '', ['draft', 'published', 'archived']) ? $payload['status'] : 'published';

        $data = [
            'external_id'    => $externalId ?: null,
            'title'          => $title,
            'slug'           => $slug,
            'excerpt'        => $excerpt,
            'content'        => $content,
            'featured_image' => $featuredImage ?: null,
            'status'         => $status,
        ];

        return $this->repo->upsert($data);
    }

    public function getPublishedPosts(int $limit = 20, int $offset = 0): array {
        return $this->repo->getPublishedPosts($limit, $offset);
    }

    public function getPostBySlug(string $slug): ?array {
        return $this->repo->findBySlug($slug);
    }
}
