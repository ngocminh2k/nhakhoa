<?php
// backend/services/ServiceService.php

require_once __DIR__ . '/../repositories/ServiceRepository.php';

class ServiceService {
    private ServiceRepository $repo;

    public function __construct() {
        $this->repo = new ServiceRepository();
    }

    public function getActiveServices(): array {
        return $this->repo->getActiveServices();
    }

    public function getAllServices(): array {
        return $this->repo->getAllServices();
    }

    public function getService(int $id): ?array {
        return $this->repo->findById($id);
    }

    public function createService(array $data): int {
        return $this->repo->create($data);
    }

    public function updateService(int $id, array $data): bool {
        return $this->repo->update($id, $data);
    }
}
