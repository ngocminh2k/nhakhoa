<?php
// backend/repositories/BookingRepository.php

require_once __DIR__ . '/../core/Database.php';

class BookingRepository {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function getActiveBookingsForDate(string $date): array {
        $stmt = $this->db->prepare("
            SELECT id, booking_code, service_id, booking_date, start_time, end_time, status
            FROM bookings
            WHERE booking_date = :date
              AND status NOT IN ('cancelled', 'no_show')
            ORDER BY start_time ASC
        ");
        $stmt->execute([':date' => $date]);
        return $stmt->fetchAll();
    }

    /**
     * Recheck if a specific slot conflicts with any existing active booking.
     * Called inside a transaction after acquiring a MySQL advisory GET_LOCK.
     * No FOR UPDATE needed — advisory lock already serializes concurrent writers for this slot.
     */
    public function hasConflict(string $date, string $startTime, string $endTime, ?int $excludeBookingId = null): bool {
        $sql = "
            SELECT id FROM bookings
            WHERE booking_date = :date
              AND status NOT IN ('cancelled', 'no_show')
              AND (start_time < :end_time AND end_time > :start_time)
        ";
        $params = [
            ':date'       => $date,
            ':start_time' => $startTime,
            ':end_time'   => $endTime,
        ];

        if ($excludeBookingId !== null) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeBookingId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetch();
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare('
            INSERT INTO bookings (
                booking_code, customer_name, phone, notes,
                service_id, booking_date, start_time, end_time,
                status, source_page, referrer,
                utm_source, utm_medium, utm_campaign, utm_content, utm_term,
                session_id, sheet_sync_status
            ) VALUES (
                :booking_code, :customer_name, :phone, :notes,
                :service_id, :booking_date, :start_time, :end_time,
                :status, :source_page, :referrer,
                :utm_source, :utm_medium, :utm_campaign, :utm_content, :utm_term,
                :session_id, :sheet_sync_status
            )
        ');
        $stmt->execute([
            ':booking_code'       => $data['booking_code'],
            ':customer_name'      => $data['customer_name'],
            ':phone'              => $data['phone'],
            ':notes'              => $data['notes'] ?? null,
            ':service_id'         => $data['service_id'],
            ':booking_date'       => $data['booking_date'],
            ':start_time'         => $data['start_time'],
            ':end_time'           => $data['end_time'],
            ':status'             => $data['status'] ?? 'new',
            ':source_page'        => $data['source_page'] ?? null,
            ':referrer'           => $data['referrer'] ?? null,
            ':utm_source'         => $data['utm_source'] ?? null,
            ':utm_medium'         => $data['utm_medium'] ?? null,
            ':utm_campaign'       => $data['utm_campaign'] ?? null,
            ':utm_content'        => $data['utm_content'] ?? null,
            ':utm_term'           => $data['utm_term'] ?? null,
            ':session_id'         => $data['session_id'] ?? null,
            ':sheet_sync_status'  => $data['sheet_sync_status'] ?? 'pending',
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare('
            SELECT b.*, s.name AS service_name, s.duration_minutes
            FROM bookings b
            LEFT JOIN services s ON b.service_id = s.id
            WHERE b.id = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $id]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public function findByCode(string $code): ?array {
        $stmt = $this->db->prepare('
            SELECT b.*, s.name AS service_name
            FROM bookings b
            LEFT JOIN services s ON b.service_id = s.id
            WHERE b.booking_code = :code
            LIMIT 1
        ');
        $stmt->execute([':code' => $code]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public function updateStatus(int $id, string $status): bool {
        $stmt = $this->db->prepare('
            UPDATE bookings
            SET status = :status,
                cancelled_at = IF(:status_chk = "cancelled", NOW(), cancelled_at)
            WHERE id = :id
        ');
        return $stmt->execute([
            ':id'         => $id,
            ':status'     => $status,
            ':status_chk' => $status,
        ]);
    }

    public function updateDateTime(int $id, string $date, string $startTime, string $endTime, ?int $serviceId = null): bool {
        $sql = 'UPDATE bookings SET booking_date = :date, start_time = :start, end_time = :end';
        $params = [
            ':id'    => $id,
            ':date'  => $date,
            ':start' => $startTime,
            ':end'   => $endTime,
        ];
        if ($serviceId !== null) {
            $sql .= ', service_id = :service_id';
            $params[':service_id'] = $serviceId;
        }
        $sql .= ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function updateSheetSync(int $id, string $status, ?string $error = null): bool {
        $stmt = $this->db->prepare('
            UPDATE bookings
            SET sheet_sync_status = :status,
                sheet_sync_error  = :error,
                sheet_synced_at   = IF(:status_chk = "synced", NOW(), sheet_synced_at)
            WHERE id = :id
        ');
        return $stmt->execute([
            ':id'         => $id,
            ':status'     => $status,
            ':status_chk' => $status,
            ':error'      => $error,
        ]);
    }

    public function getPendingOrFailedSync(int $limit = 50): array {
        $stmt = $this->db->prepare("
            SELECT b.*, s.name AS service_name
            FROM bookings b
            LEFT JOIN services s ON b.service_id = s.id
            WHERE b.sheet_sync_status IN ('pending', 'failed')
            ORDER BY b.id ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function search(array $filters, int $page = 1, int $perPage = 20): array {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['date'])) {
            $where[] = 'b.booking_date = :date';
            $params[':date'] = $filters['date'];
        }
        if (!empty($filters['service_id'])) {
            $where[] = 'b.service_id = :service_id';
            $params[':service_id'] = (int)$filters['service_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'b.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(b.customer_name LIKE :kw1 OR b.phone LIKE :kw2 OR b.booking_code LIKE :kw3)';
            $kw = '%' . $filters['search'] . '%';
            $params[':kw1'] = $kw;
            $params[':kw2'] = $kw;
            $params[':kw3'] = $kw;
        }
        if (!empty($filters['utm_source'])) {
            $where[] = 'b.utm_source = :utm_source';
            $params[':utm_source'] = $filters['utm_source'];
        }
        if (!empty($filters['sync_status'])) {
            $where[] = 'b.sheet_sync_status = :sync_status';
            $params[':sync_status'] = $filters['sync_status'];
        }

        $whereClause = implode(' AND ', $where);

        // Count total
        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM bookings b WHERE $whereClause");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Fetch page
        $offset = ($page - 1) * $perPage;
        $sql = "
            SELECT b.*, s.name AS service_name
            FROM bookings b
            LEFT JOIN services s ON b.service_id = s.id
            WHERE $whereClause
            ORDER BY b.booking_date DESC, b.start_time DESC
            LIMIT :offset, :per_page
        ";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':per_page', $perPage, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();

        return [
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int)ceil($total / $perPage),
        ];
    }
}
