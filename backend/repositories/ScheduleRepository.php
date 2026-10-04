<?php
// backend/repositories/ScheduleRepository.php

require_once __DIR__ . '/../core/Database.php';

class ScheduleRepository {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function getBusinessHoursByWeekday(int $weekday): ?array {
        $stmt = $this->db->prepare('
            SELECT id, weekday, start_time, end_time, active
            FROM business_hours
            WHERE weekday = :weekday AND active = 1
            LIMIT 1
        ');
        $stmt->execute([':weekday' => $weekday]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public function getAllBusinessHours(): array {
        $stmt = $this->db->query('
            SELECT id, weekday, start_time, end_time, active
            FROM business_hours
            ORDER BY weekday ASC
        ');
        return $stmt->fetchAll();
    }

    public function updateBusinessHours(int $weekday, string $startTime, string $endTime, int $active): bool {
        $stmt = $this->db->prepare('
            INSERT INTO business_hours (weekday, start_time, end_time, active)
            VALUES (:weekday, :start_time, :end_time, :active)
            ON DUPLICATE KEY UPDATE
                start_time = VALUES(start_time),
                end_time   = VALUES(end_time),
                active     = VALUES(active)
        ');
        return $stmt->execute([
            ':weekday'    => $weekday,
            ':start_time' => $startTime,
            ':end_time'   => $endTime,
            ':active'     => $active,
        ]);
    }

    public function getBlocksForDate(string $date): array {
        $stmt = $this->db->prepare('
            SELECT id, start_datetime, end_datetime, reason
            FROM schedule_blocks
            WHERE DATE(start_datetime) <= :date1 AND DATE(end_datetime) >= :date2
        ');
        $stmt->execute([
            ':date1' => $date,
            ':date2' => $date,
        ]);
        return $stmt->fetchAll();
    }

    public function getAllScheduleBlocks(int $limit = 50): array {
        $stmt = $this->db->prepare('
            SELECT id, start_datetime, end_datetime, reason, created_at
            FROM schedule_blocks
            ORDER BY start_datetime DESC
            LIMIT :limit
        ');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function addScheduleBlock(string $startDatetime, string $endDatetime, ?string $reason): int {
        $stmt = $this->db->prepare('
            INSERT INTO schedule_blocks (start_datetime, end_datetime, reason)
            VALUES (:start, :end, :reason)
        ');
        $stmt->execute([
            ':start'  => $startDatetime,
            ':end'    => $endDatetime,
            ':reason' => $reason,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function deleteScheduleBlock(int $id): bool {
        $stmt = $this->db->prepare('DELETE FROM schedule_blocks WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }
}
