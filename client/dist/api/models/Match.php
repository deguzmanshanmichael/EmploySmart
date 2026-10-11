<?php
require_once __DIR__ . '/../config/database.php';

class JobMatch {

    private $db;

    public function __construct() {
        $this->db = getDB();
    }

    // ─── Store a match score ──────────────────────────────────────────────────
    public function store(int $userId, int $jobId, float $score): void {
        $stmt = $this->db->prepare(
            "INSERT INTO job_matches (user_id, job_id, match_score) VALUES (?,?,?)"
        );
        $stmt->bind_param('iid', $userId, $jobId, $score);
        $stmt->execute();
    }

    // ─── Delete all stored matches for a user ────────────────────────────────
    public function deleteForUser(int $userId): void {
        $stmt = $this->db->prepare("DELETE FROM job_matches WHERE user_id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
    }

    // ─── Get stored matches for a user (sorted by score) ─────────────────────
    public function getForUser(int $userId, int $limit = 10): array {
        $stmt = $this->db->prepare("
            SELECT jm.*, j.title, j.location, j.job_type, j.salary_range, j.deadline,
                   e.company_name, e.industry
            FROM job_matches jm
            JOIN jobs j      ON j.id  = jm.job_id
            JOIN employers e ON e.id  = j.employer_id
            WHERE jm.user_id = ?
            ORDER BY jm.match_score DESC
            LIMIT ?
        ");
        $stmt->bind_param('ii', $userId, $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // ─── Get single stored score ──────────────────────────────────────────────
    public function getScore(int $userId, int $jobId): ?float {
        $stmt = $this->db->prepare(
            "SELECT match_score FROM job_matches WHERE user_id = ? AND job_id = ?"
        );
        $stmt->bind_param('ii', $userId, $jobId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ? (float)$row['match_score'] : null;
    }
}