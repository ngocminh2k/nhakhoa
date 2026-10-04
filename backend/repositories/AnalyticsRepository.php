<?php
// backend/repositories/AnalyticsRepository.php

require_once __DIR__ . '/../core/Database.php';

class AnalyticsRepository {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function recordSession(array $data): void {
        $stmt = $this->db->prepare('
            INSERT INTO sessions (
                session_id, first_page, referrer,
                utm_source, utm_medium, utm_campaign, utm_content, utm_term
            ) VALUES (
                :session_id, :first_page, :referrer,
                :utm_source, :utm_medium, :utm_campaign, :utm_content, :utm_term
            )
            ON DUPLICATE KEY UPDATE
                last_seen_at = CURRENT_TIMESTAMP
        ');
        $stmt->execute([
            ':session_id'   => $data['session_id'],
            ':first_page'   => $data['first_page'] ?? null,
            ':referrer'     => $data['referrer'] ?? null,
            ':utm_source'   => $data['utm_source'] ?? null,
            ':utm_medium'   => $data['utm_medium'] ?? null,
            ':utm_campaign' => $data['utm_campaign'] ?? null,
            ':utm_content'  => $data['utm_content'] ?? null,
            ':utm_term'     => $data['utm_term'] ?? null,
        ]);
    }

    public function recordPageView(string $sessionId, string $path): void {
        $stmt = $this->db->prepare('
            INSERT INTO page_views (session_id, path)
            VALUES (:session_id, :path)
        ');
        $stmt->execute([
            ':session_id' => $sessionId,
            ':path'       => $path,
        ]);

        // Also update session last_seen_at
        $upd = $this->db->prepare('UPDATE sessions SET last_seen_at = CURRENT_TIMESTAMP WHERE session_id = :sid');
        $upd->execute([':sid' => $sessionId]);
    }

    public function getTodayStats(): array {
        $today = date('Y-m-d');

        // Sessions today
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM sessions WHERE DATE(created_at) = :today');
        $stmt->execute([':today' => $today]);
        $sessions = (int)$stmt->fetchColumn();

        // Page views today
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM page_views WHERE DATE(created_at) = :today');
        $stmt->execute([':today' => $today]);
        $pageViews = (int)$stmt->fetchColumn();

        // Bookings today
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM bookings WHERE DATE(created_at) = :today AND status != 'cancelled'");
        $stmt->execute([':today' => $today]);
        $bookings = (int)$stmt->fetchColumn();

        $conversion = ($sessions > 0) ? round(($bookings / $sessions) * 100, 2) : 0.0;

        return [
            'sessions'   => $sessions,
            'page_views' => $pageViews,
            'bookings'   => $bookings,
            'conversion' => $conversion,
        ];
    }

    public function getSummaryStats(int $days = 7): array {
        $since = date('Y-m-d 00:00:00', strtotime("-$days days"));

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM sessions WHERE created_at >= :since');
        $stmt->execute([':since' => $since]);
        $sessions = (int)$stmt->fetchColumn();

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM bookings WHERE created_at >= :since AND status != 'cancelled'");
        $stmt->execute([':since' => $since]);
        $bookings = (int)$stmt->fetchColumn();

        $conversion = ($sessions > 0) ? round(($bookings / $sessions) * 100, 2) : 0.0;

        return [
            'days'       => $days,
            'sessions'   => $sessions,
            'bookings'   => $bookings,
            'conversion' => $conversion,
        ];
    }

    public function getTopSources(int $days = 30, int $limit = 10): array {
        $since = date('Y-m-d 00:00:00', strtotime("-$days days"));
        $stmt = $this->db->prepare("
            SELECT
                COALESCE(NULLIF(utm_source, ''), 'direct') AS source,
                COUNT(*) AS session_count
            FROM sessions
            WHERE created_at >= :since
            GROUP BY source
            ORDER BY session_count DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':since', $since);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getTopLandingPages(int $days = 30, int $limit = 10): array {
        $since = date('Y-m-d 00:00:00', strtotime("-$days days"));
        $stmt = $this->db->prepare("
            SELECT
                COALESCE(NULLIF(first_page, ''), '/') AS landing_page,
                COUNT(*) AS count
            FROM sessions
            WHERE created_at >= :since
            GROUP BY landing_page
            ORDER BY count DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':since', $since);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function aggregateDaily(string $date): void {
        // Aggregates raw sessions, pageviews, and bookings for a specific day into daily_analytics
        $sql = "
            INSERT INTO daily_analytics (date, path, source, campaign, sessions, page_views, bookings)
            SELECT
                :date1 AS date,
                COALESCE(s.first_page, '/') AS path,
                COALESCE(NULLIF(s.utm_source, ''), 'direct') AS source,
                COALESCE(s.utm_campaign, '') AS campaign,
                COUNT(DISTINCT s.id) AS sessions,
                COUNT(pv.id) AS page_views,
                COUNT(DISTINCT b.id) AS bookings
            FROM sessions s
            LEFT JOIN page_views pv ON s.session_id = pv.session_id AND DATE(pv.created_at) = :date2
            LEFT JOIN bookings b ON s.session_id = b.session_id AND DATE(b.created_at) = :date3 AND b.status != 'cancelled'
            WHERE DATE(s.created_at) = :date4
            GROUP BY path, source, campaign
            ON DUPLICATE KEY UPDATE
                sessions   = VALUES(sessions),
                page_views = VALUES(page_views),
                bookings   = VALUES(bookings)
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':date1' => $date,
            ':date2' => $date,
            ':date3' => $date,
            ':date4' => $date,
        ]);
    }
}
