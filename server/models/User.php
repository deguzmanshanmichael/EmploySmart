<?php
require_once __DIR__ . '/../config/database.php';

class User {

    private $db;

    public function __construct() {
        $this->db = getDB();
    }

    // ─── Find by ID (with employer join) ─────────────────────────────────────
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT u.*,
                   e.id               AS employer_id,
                   e.company_name,
                   e.industry,
                   e.verification_status AS employer_status
            FROM users u
            LEFT JOIN employers e ON e.user_id = u.id
            WHERE u.id = ?
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) unset($row['password']);
        return $row ?: null;
    }

    // ─── Find by email (includes password for login) ──────────────────────────
    public function findByEmail(string $email): ?array {
        $stmt = $this->db->prepare("
            SELECT u.*,
                   e.id               AS employer_record_id,
                   e.verification_status AS employer_status
            FROM users u
            LEFT JOIN employers e ON e.user_id = u.id
            WHERE u.email = ?
        ");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    // ─── Check email exists ───────────────────────────────────────────────────
    public function emailExists(string $email): bool {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }

    // ─── Create new user ──────────────────────────────────────────────────────
    public function create(array $data): int {
        $hashed = password_hash($data['password'], PASSWORD_BCRYPT);
        $stmt = $this->db->prepare("
            INSERT INTO users
                (first_name, middle_name, last_name, suffix, sex, birth_date,
                 email, password, role, phone, alternate_phone,
                 address, city, province, zip_code,
                 education_level, employment_status, bio, is_verified)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->bind_param(
            'ssssssssssssssssssi',
            $data['first_name'],
            $data['middle_name']       ?? null,
            $data['last_name'],
            $data['suffix']            ?? null,
            $data['sex'],
            $data['birth_date']        ?? null,
            $data['email'],
            $hashed,
            $data['role'],
            $data['phone']             ?? null,
            $data['alternate_phone']   ?? null,
            $data['address']           ?? null,
            $data['city']              ?? null,
            $data['province']          ?? null,
            $data['zip_code']          ?? null,
            $data['education_level']   ?? null,
            $data['employment_status'] ?? 'unemployed',
            $data['bio']               ?? null,
            $data['is_verified']       ?? 0
        );
        $stmt->execute();
        return $this->db->insert_id;
    }

    // ─── Update profile fields ────────────────────────────────────────────────
    public function update(int $id, array $data): bool {
        $allowed = [
            'first_name','middle_name','last_name','suffix','sex','birth_date',
            'phone','alternate_phone','address','city','province','zip_code',
            'civil_status','nationality','education_level','employment_status','bio'
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
        $stmt = $this->db->prepare("UPDATE users SET " . implode(', ', $sets) . " WHERE id = ?");
        $stmt->bind_param($types, ...$params);
        return $stmt->execute();
    }

    // ─── Update password ──────────────────────────────────────────────────────
    public function updatePassword(int $id, string $newPassword): bool {
        $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt   = $this->db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param('si', $hashed, $id);
        return $stmt->execute();
    }

    // ─── Verify user ──────────────────────────────────────────────────────────
    public function verify(int $id): bool {
        $stmt = $this->db->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }

    // ─── Update profile picture path ─────────────────────────────────────────
    public function updateProfilePicture(int $id, string $path): bool {
        $stmt = $this->db->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
        $stmt->bind_param('si', $path, $id);
        return $stmt->execute();
    }

    // ─── Update resume path ───────────────────────────────────────────────────
    public function updateResume(int $id, string $path): bool {
        $stmt = $this->db->prepare("UPDATE users SET resume_path = ? WHERE id = ?");
        $stmt->bind_param('si', $path, $id);
        return $stmt->execute();
    }

    // ─── Delete user ──────────────────────────────────────────────────────────
    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }

    // ─── Paginated list with optional search + role filter ───────────────────
    public function getAll(string $search = '', string $role = '', int $limit = 10, int $offset = 0): array {
        $where  = "WHERE 1=1";
        $params = []; $types = '';

        if ($search) {
            $where   .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
            $s        = "%$search%";
            $params[] = $s; $params[] = $s; $params[] = $s;
            $types   .= 'sss';
        }
        if ($role) {
            $where   .= " AND u.role = ?";
            $params[] = $role;
            $types   .= 's';
        }

        // Count
        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM users u $where");
        if ($types) $countStmt->bind_param($types, ...$params);
        $countStmt->execute();
        $total = $countStmt->get_result()->fetch_row()[0];

        // Data
        $params[] = $limit; $params[] = $offset;
        $types   .= 'ii';
        $stmt = $this->db->prepare("
            SELECT u.id, u.first_name, u.last_name, u.email, u.role,
                   u.phone, u.city, u.is_verified, u.employment_status, u.created_at
            FROM users u
            $where
            ORDER BY u.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        return ['data' => $rows, 'total' => (int)$total];
    }

    // ─── Dashboard statistics ─────────────────────────────────────────────────
    public function getStats(): array {
        $db = $this->db;
        
        // Use prepared statements for security
        $stmt = $db->prepare("SELECT COUNT(*) FROM users");
        $stmt->execute();
        $total_users = (int)$stmt->get_result()->fetch_row()[0];
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role=?");
        $stmt->bind_param("s", $role);
        $role = 'jobseeker';
        $stmt->execute();
        $total_jobseekers = (int)$stmt->get_result()->fetch_row()[0];
        
        $role = 'employer';
        $stmt->execute();
        $total_employers = (int)$stmt->get_result()->fetch_row()[0];
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE is_verified=?");
        $stmt->bind_param("i", $verified);
        $verified = 1;
        $stmt->execute();
        $verified_users = (int)$stmt->get_result()->fetch_row()[0];
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM jobs WHERE approval_status=?");
        $stmt->bind_param("s", $status);
        $status = 'approved';
        $stmt->execute();
        $total_jobs = (int)$stmt->get_result()->fetch_row()[0];
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM applications");
        $stmt->execute();
        $total_applications = (int)$stmt->get_result()->fetch_row()[0];
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM employers WHERE verification_status=?");
        $stmt->bind_param("s", $ver_status);
        $ver_status = 'pending';
        $stmt->execute();
        $pending_employers = (int)$stmt->get_result()->fetch_row()[0];
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM training_programs WHERE status=?");
        $stmt->bind_param("s", $train_status);
        $train_status = 'ongoing';
        $stmt->execute();
        $active_trainings = (int)$stmt->get_result()->fetch_row()[0];
        
        return [
            'total_users'        => $total_users,
            'total_jobseekers'   => $total_jobseekers,
            'total_employers'    => $total_employers,
            'verified_users'     => $verified_users,
            'total_jobs'         => $total_jobs,
            'total_applications' => $total_applications,
            'pending_employers'  => $pending_employers,
            'active_trainings'   => $active_trainings,
        ];
    }

    // ─── Get system logs (admin only) ─────────────────────────────────────────
    public function getLogs(string $search = '', int $limit = 20, int $offset = 0): array {
        $where  = "WHERE 1=1";
        $params = []; $types = '';

        if ($search) {
            $where   .= " AND sl.action LIKE ?";
            $params[] = "%$search%";
            $types   .= 's';
        }

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM system_logs sl $where");
        if ($types) $countStmt->bind_param($types, ...$params);
        $countStmt->execute();
        $total = $countStmt->get_result()->fetch_row()[0];

        $params[] = $limit; $params[] = $offset;
        $types   .= 'ii';
        $stmt = $this->db->prepare("
            SELECT sl.*, u.first_name, u.last_name
            FROM system_logs sl
            LEFT JOIN users u ON u.id = sl.user_id
            $where
            ORDER BY sl.log_time DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        return ['data' => $rows, 'total' => (int)$total];
    }

    // ─── Log an action ────────────────────────────────────────────────────────
    public function log(int $userId, string $action): void {
        $ip   = $_SERVER['REMOTE_ADDR']     ?? '';
        $ua   = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $stmt = $this->db->prepare("INSERT INTO system_logs (user_id, action, ip_address, user_agent) VALUES (?,?,?,?)");
        $stmt->bind_param('isss', $userId, $action, $ip, $ua);
        $stmt->execute();
    }

    // ─── Store refresh token ─────────────────────────────────────────────────
    public function storeRefreshToken(int $userId, string $token, string $expiresAt): void {
        $stmt = $this->db->prepare("INSERT INTO refresh_tokens (user_id, token, expires_at) VALUES (?,?,?)");
        $stmt->bind_param('iss', $userId, $token, $expiresAt);
        $stmt->execute();
        // Clean expired tokens
        $this->db->query("DELETE FROM refresh_tokens WHERE expires_at < NOW()");
    }

    // ─── Find refresh token record ───────────────────────────────────────────
    public function findRefreshToken(string $token): ?array {
        $stmt = $this->db->prepare("
            SELECT rt.*, u.id AS uid, u.email, u.role,
                   u.first_name, u.last_name, u.profile_picture
            FROM refresh_tokens rt
            JOIN users u ON u.id = rt.user_id
            WHERE rt.token = ? AND rt.expires_at > NOW()
        ");
        $stmt->bind_param('s', $token);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    // ─── Delete refresh token (logout / rotation) ────────────────────────────
    public function deleteRefreshToken(string $token): void {
        $stmt = $this->db->prepare("DELETE FROM refresh_tokens WHERE token = ?");
        $stmt->bind_param('s', $token);
        $stmt->execute();
    }
}