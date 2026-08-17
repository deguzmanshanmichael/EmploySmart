<?php
require_once __DIR__ . '/../config/database.php';

class Job {

    private $db;

    public function __construct() {
        $this->db = getDB();
    }

    // ─── Find job by ID (with employer info + skills) ─────────────────────────
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT j.*,
                   e.company_name, e.industry, e.website,
                   e.company_address, e.contact_email, e.contact_phone
            FROM jobs j
            JOIN employers e ON e.id = j.employer_id
            WHERE j.id = ?
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $job = $stmt->get_result()->fetch_assoc();
        if (!$job) return null;
        $job['skills'] = $this->getSkills($id);
        return $job;
    }

    // ─── Get skills for a job ─────────────────────────────────────────────────
    public function getSkills(int $jobId): array {
        $stmt = $this->db->prepare("
            SELECT s.id, s.skill_name
            FROM job_skills js
            JOIN skills s ON s.id = js.skill_id
            WHERE js.job_id = ?
        ");
        $stmt->bind_param('i', $jobId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // ─── Create a new job posting ─────────────────────────────────────────────
    public function create(array $data, int $employerId): int {
        $title = (string) ($data['title'] ?? '');
        $description = (string) ($data['description'] ?? '');
        $requirements = isset($data['requirements']) ? (string) $data['requirements'] : null;
        $location = (string) ($data['location'] ?? '');
        $salaryRange = isset($data['salary_range']) ? (string) $data['salary_range'] : null;
        $vacancies = (int) ($data['vacancies'] ?? 1);
        $deadline = isset($data['deadline']) ? (string) $data['deadline'] : null;
        $experienceLevel = isset($data['experience_level']) ? (string) $data['experience_level'] : null;
        $educationRequired = isset($data['education_required']) ? (string) $data['education_required'] : null;
        $approvalStatus = 'pending';

        $stmt = $this->db->prepare("
            INSERT INTO jobs
                (employer_id, title, description, requirements,
                 location, salary_range, vacancies, deadline,
                 job_type, experience_level, education_required, approval_status)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->bind_param(
            'isssssisssss',
            $employerId,
            $title,
            $description,
            $requirements,
            $location,
            $salaryRange,
            $vacancies,
            $deadline,
            $data['job_type'],
            $experienceLevel,
            $educationRequired,
            $approvalStatus
        );
        $stmt->execute();
        $jobId = $this->db->insert_id;

        if (!empty($data['skills'])) {
            $this->syncSkills($jobId, $data['skills']);
        }
        return $jobId;
    }

    // ─── Update a job ─────────────────────────────────────────────────────────
    public function update(int $id, array $data): bool {
        $allowed = [
            'title','description','requirements','location','salary_range',
            'vacancies','deadline','job_type','experience_level','education_required'
        ];
        $sets = []; $params = []; $types = '';
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $sets[]   = "$field = ?";
                $params[] = $data[$field];
                $types   .= 's';
            }
        }
        $updated = false;
        if (!empty($sets)) {
            $params[] = $id;
            $types   .= 'i';
            $stmt = $this->db->prepare("UPDATE jobs SET " . implode(', ', $sets) . " WHERE id = ?");
            $stmt->bind_param($types, ...$params);
            $updated = $stmt->execute();
        }
        if (isset($data['skills'])) {
            $this->syncSkills($id, $data['skills']);
        }
        return $updated;
    }

    // ─── Sync skills for a job ────────────────────────────────────────────────
    public function syncSkills(int $jobId, array $skillIds): void {
        $del = $this->db->prepare("DELETE FROM job_skills WHERE job_id = ?");
        $del->bind_param('i', $jobId);
        $del->execute();
        foreach ($skillIds as $skillId) {
            $ins = $this->db->prepare("INSERT INTO job_skills (job_id, skill_id) VALUES (?,?)");
            $ins->bind_param('ii', $jobId, $skillId);
            $ins->execute();
        }
    }

    // ─── Delete a job ─────────────────────────────────────────────────────────
    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM jobs WHERE id = ?");
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }

    // ─── Update approval status ───────────────────────────────────────────────
    public function updateApprovalStatus(int $id, string $status, int $approvedBy): bool {
        $stmt = $this->db->prepare("UPDATE jobs SET approval_status = ?, approved_by = ? WHERE id = ?");
        $stmt->bind_param('sii', $status, $approvedBy, $id);
        return $stmt->execute();
    }

    // ─── Check if employer owns this job ─────────────────────────────────────
    public function isOwnedByUser(int $jobId, int $userId): bool {
        $stmt = $this->db->prepare("
            SELECT j.id FROM jobs j
            JOIN employers e ON e.id = j.employer_id
            WHERE j.id = ? AND e.user_id = ?
        ");
        $stmt->bind_param('ii', $jobId, $userId);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }

    // ─── Paginated list with filters ──────────────────────────────────────────
    public function getAll(
        string $search         = '',
        string $location       = '',
        string $jobType        = '',
        string $experienceLevel = '',
        string $approvalStatus = 'approved',
        int    $limit          = 10,
        int    $offset         = 0
    ): array {
        $where  = "WHERE j.approval_status = ?";
        $params = [$approvalStatus]; $types = 's';

        if ($search) {
            $where   .= " AND (j.title LIKE ? OR j.description LIKE ?)";
            $s        = "%$search%";
            $params[] = $s; $params[] = $s;
            $types   .= 'ss';
        }
        if ($location) {
            $where   .= " AND j.location LIKE ?";
            $params[] = "%$location%";
            $types   .= 's';
        }
        if ($jobType) {
            $where   .= " AND j.job_type = ?";
            $params[] = $jobType;
            $types   .= 's';
        }
        if ($experienceLevel) {
            $where   .= " AND j.experience_level = ?";
            $params[] = $experienceLevel;
            $types   .= 's';
        }

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM jobs j $where");
        $countStmt->bind_param($types, ...$params);
        $countStmt->execute();
        $total = $countStmt->get_result()->fetch_row()[0];

        $params[] = $limit; $params[] = $offset;
        $types   .= 'ii';
        $stmt = $this->db->prepare("
            SELECT j.*, e.company_name, e.industry
            FROM jobs j
            JOIN employers e ON e.id = j.employer_id
            $where
            ORDER BY j.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($jobs as &$job) {
            $job['skills'] = $this->getSkills($job['id']);
        }

        return ['data' => $jobs, 'total' => (int)$total];
    }

    // ─── Get jobs by employer ID ──────────────────────────────────────────────
    public function getByEmployer(int $employerId, int $limit = 10, int $offset = 0): array {
        $stmt = $this->db->prepare("
            SELECT j.*, e.company_name
            FROM jobs j
            JOIN employers e ON e.id = j.employer_id
            WHERE j.employer_id = ?
            ORDER BY j.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param('iii', $employerId, $limit, $offset);
        $stmt->execute();
        $jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $count = $this->db->prepare("SELECT COUNT(*) FROM jobs WHERE employer_id = ?");
        $count->bind_param('i', $employerId);
        $count->execute();
        $total = $count->get_result()->fetch_row()[0];

        return ['data' => $jobs, 'total' => (int)$total];
    }

    // ─── Get pending jobs ─────────────────────────────────────────────────────
    public function getPending(int $limit = 10, int $offset = 0): array {
        $stmt = $this->db->prepare("
            SELECT j.*, e.company_name
            FROM jobs j
            JOIN employers e ON e.id = j.employer_id
            WHERE j.approval_status = 'pending'
            ORDER BY j.created_at ASC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param('ii', $limit, $offset);
        $stmt->execute();
        $jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $total = (int)$this->db->query(
            "SELECT COUNT(*) FROM jobs WHERE approval_status='pending'"
        )->fetch_row()[0];

        return ['data' => $jobs, 'total' => $total];
    }

    // ─── Get all approved non-expired jobs (for matching) ────────────────────
    public function getAllApprovedForMatching(): array {
        $stmt = $this->db->prepare("
            SELECT j.*, e.company_name, e.industry
            FROM jobs j
            JOIN employers e ON e.id = j.employer_id
            WHERE j.approval_status = 'approved'
              AND (j.deadline IS NULL OR j.deadline >= CURDATE())
        ");
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}