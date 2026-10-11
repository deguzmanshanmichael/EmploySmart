<?php
require_once __DIR__ . '/../config/database.php';

class Employer {

    private $db;

    public function __construct() {
        $this->db = getDB();
    }

    // ─── Find employer by its own ID ──────────────────────────────────────────
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT e.*, u.first_name, u.last_name, u.email
            FROM employers e
            JOIN users u ON u.id = e.user_id
            WHERE e.id = ?
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    // ─── Find employer by user_id ─────────────────────────────────────────────
    public function findByUserId(int $userId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM employers WHERE user_id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    // ─── Create employer profile ──────────────────────────────────────────────
    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO employers
                (user_id, company_name, industry, company_size, website,
                 business_permit, company_address,
                 contact_person, contact_email, contact_phone, verification_status)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->bind_param(
            'issssssssss',
            $data['user_id'],
            $data['company_name']        ?? '',
            $data['industry']            ?? null,
            $data['company_size']        ?? null,
            $data['website']             ?? null,
            $data['business_permit']     ?? null,
            $data['company_address']     ?? null,
            $data['contact_person']      ?? null,
            $data['contact_email']       ?? null,
            $data['contact_phone']       ?? null,
            $data['verification_status'] ?? 'pending'
        );
        $stmt->execute();
        return $this->db->insert_id;
    }

    // ─── Update employer profile ──────────────────────────────────────────────
    public function update(int $id, array $data): bool {
        $allowed = [
            'company_name','industry','company_size','website',
            'company_address','contact_person','contact_email','contact_phone'
        ];
        $sets = []; $params = []; $types = '';
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $sets[]   = "$field = ?";
                $params[] = $data[$field];
                $types   .= 's';
            }
        }
        if (empty($sets)) return false;
        $params[] = $id;
        $types   .= 'i';
        $stmt = $this->db->prepare("UPDATE employers SET " . implode(', ', $sets) . " WHERE id = ?");
        $stmt->bind_param($types, ...$params);
        return $stmt->execute();
    }

    // ─── Update verification status ───────────────────────────────────────────
    public function updateVerificationStatus(int $id, string $status): bool {
        $stmt = $this->db->prepare("UPDATE employers SET verification_status = ? WHERE id = ?");
        $stmt->bind_param('si', $status, $id);
        return $stmt->execute();
    }

    // ─── Update business permit document path ────────────────────────────────
    public function updateDocument(int $id, string $path): bool {
        $stmt = $this->db->prepare("UPDATE employers SET business_permit = ? WHERE id = ?");
        $stmt->bind_param('si', $path, $id);
        return $stmt->execute();
    }

    // ─── Paginated list with optional verification status filter ─────────────
    public function getAll(string $status = '', int $limit = 10, int $offset = 0): array {
        $where  = "WHERE 1=1";
        $params = []; $types = '';

        if ($status) {
            $where   .= " AND e.verification_status = ?";
            $params[] = $status;
            $types   .= 's';
        }

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM employers e $where");
        if ($types) $countStmt->bind_param($types, ...$params);
        $countStmt->execute();
        $total = $countStmt->get_result()->fetch_row()[0];

        $params[] = $limit; $params[] = $offset;
        $types   .= 'ii';
        $stmt = $this->db->prepare("
            SELECT e.*, u.first_name, u.last_name, u.email
            FROM employers e
            JOIN users u ON u.id = e.user_id
            $where
            ORDER BY e.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        return ['data' => $rows, 'total' => (int)$total];
    }

    // ─── Analytics for a specific employer ───────────────────────────────────
    public function getAnalytics(int $employerId): array {
        $db = $this->db;
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM jobs WHERE employer_id = ?");
        $stmt->bind_param("i", $employerId);
        $stmt->execute();
        $total_jobs = (int)$stmt->get_result()->fetch_row()[0];
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM jobs WHERE employer_id = ? AND approval_status=?");
        $stmt->bind_param("is", $employerId, $status);
        $status = 'approved';
        $stmt->execute();
        $approved_jobs = (int)$stmt->get_result()->fetch_row()[0];
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM applications a
                 JOIN jobs j ON j.id = a.job_id
                 WHERE j.employer_id = ?");
        $stmt->bind_param("i", $employerId);
        $stmt->execute();
        $total_applications = (int)$stmt->get_result()->fetch_row()[0];
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM applications a
                 JOIN jobs j ON j.id = a.job_id
                 WHERE j.employer_id = ? AND a.application_status = ?");
        $stmt->bind_param("is", $employerId, $app_status);
        $app_status = 'pending';
        $stmt->execute();
        $pending_applications = (int)$stmt->get_result()->fetch_row()[0];
        
        $app_status = 'accepted';
        $stmt->execute();
        $accepted_applications = (int)$stmt->get_result()->fetch_row()[0];
        
        return [
            'total_jobs' => $total_jobs,
            'approved_jobs' => $approved_jobs,
            'total_applications' => $total_applications,
            'pending_applications' => $pending_applications,
            'accepted_applications' => $accepted_applications,
        ];
    }
}