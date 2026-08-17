<?php
require_once __DIR__ . '/../config/database.php';

class Application {

    private $db;

    public function __construct() {
        $this->db = getDB();
    }

    // ─── Check if user already applied to a job ───────────────────────────────
    public function alreadyApplied(int $userId, int $jobId): bool {
        $stmt = $this->db->prepare("SELECT id FROM applications WHERE user_id = ? AND job_id = ?");
        $stmt->bind_param('ii', $userId, $jobId);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }

    // ─── Submit a new application ─────────────────────────────────────────────
    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO applications (job_id, user_id, resume_used, cover_letter)
            VALUES (?,?,?,?)
        ");
        $stmt->bind_param(
            'iiss',
            $data['job_id'],
            $data['user_id'],
            $data['resume_used']  ?? null,
            $data['cover_letter'] ?? null
        );
        $stmt->execute();
        return $this->db->insert_id;
    }

    // ─── Find application by ID ───────────────────────────────────────────────
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT a.*,
                   j.title, j.location, j.job_type, j.salary_range, j.deadline,
                   e.company_name
            FROM applications a
            JOIN jobs j        ON j.id = a.job_id
            JOIN employers e   ON e.id = j.employer_id
            WHERE a.id = ?
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    // ─── Get all applications by a jobseeker ──────────────────────────────────
    public function getByUser(int $userId, int $limit = 10, int $offset = 0): array {
        $stmt = $this->db->prepare("
            SELECT a.*,
                   j.title, j.location, j.job_type, j.salary_range, j.deadline,
                   e.company_name
            FROM applications a
            JOIN jobs j        ON j.id = a.job_id
            JOIN employers e   ON e.id = j.employer_id
            WHERE a.user_id = ?
            ORDER BY a.applied_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param('iii', $userId, $limit, $offset);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $count = $this->db->prepare("SELECT COUNT(*) FROM applications WHERE user_id = ?");
        $count->bind_param('i', $userId);
        $count->execute();
        $total = $count->get_result()->fetch_row()[0];

        return ['data' => $rows, 'total' => (int)$total];
    }

    // ─── Get all applicants for a job ─────────────────────────────────────────
    public function getByJob(int $jobId, int $limit = 10, int $offset = 0): array {
        $stmt = $this->db->prepare("
            SELECT a.*,
                   u.first_name, u.last_name, u.email, u.phone,
                   u.education_level, u.resume_path
            FROM applications a
            JOIN users u ON u.id = a.user_id
            WHERE a.job_id = ?
            ORDER BY a.applied_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param('iii', $jobId, $limit, $offset);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $count = $this->db->prepare("SELECT COUNT(*) FROM applications WHERE job_id = ?");
        $count->bind_param('i', $jobId);
        $count->execute();
        $total = $count->get_result()->fetch_row()[0];

        return ['data' => $rows, 'total' => (int)$total];
    }

    // ─── Update application status ────────────────────────────────────────────
    public function updateStatus(int $id, string $status, ?string $remarks = null, ?string $interviewDate = null): bool {
        $stmt = $this->db->prepare("
            UPDATE applications
            SET application_status = ?, remarks = ?, interview_date = ?
            WHERE id = ?
        ");
        $stmt->bind_param('sssi', $status, $remarks, $interviewDate, $id);
        return $stmt->execute();
    }

    // ─── Withdraw (delete) an application ────────────────────────────────────
    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM applications WHERE id = ?");
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }

    // ─── Verify ownership by user ─────────────────────────────────────────────
    public function isOwnedByUser(int $applicationId, int $userId): bool {
        $stmt = $this->db->prepare("SELECT id FROM applications WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $applicationId, $userId);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }
}