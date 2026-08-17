<?php
require_once __DIR__ . '/../config/database.php';

class Training {

    private $db;

    public function __construct() {
        $this->db = getDB();
    }

    // ─── Find training by ID (with creator, skills, participants) ────────────
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT tp.*, u.first_name, u.last_name
            FROM training_programs tp
            JOIN users u ON u.id = tp.created_by
            WHERE tp.id = ?
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $prog = $stmt->get_result()->fetch_assoc();
        if (!$prog) return null;

        $prog['skills']       = $this->getSkills($id);
        $prog['participants'] = $this->getParticipants($id);
        return $prog;
    }

    // ─── Get skills for a training program ───────────────────────────────────
    public function getSkills(int $trainingId): array {
        $stmt = $this->db->prepare("
            SELECT s.id, s.skill_name
            FROM training_skills ts
            JOIN skills s ON s.id = ts.skill_id
            WHERE ts.training_id = ?
        ");
        $stmt->bind_param('i', $trainingId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // ─── Get skill IDs for a training program ────────────────────────────────
    public function getSkillIds(int $trainingId): array {
        $stmt = $this->db->prepare("SELECT skill_id FROM training_skills WHERE training_id = ?");
        $stmt->bind_param('i', $trainingId);
        $stmt->execute();
        return array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'skill_id');
    }

    // ─── Get participants for a training program ──────────────────────────────
    public function getParticipants(int $trainingId): array {
        $stmt = $this->db->prepare("
            SELECT ut.*, u.first_name, u.last_name, u.email
            FROM user_training ut
            JOIN users u ON u.id = ut.user_id
            WHERE ut.training_id = ?
        ");
        $stmt->bind_param('i', $trainingId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // ─── Get enrolled count ───────────────────────────────────────────────────
    public function getEnrolledCount(int $trainingId): int {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM user_training WHERE training_id = ? AND status != 'dropped'"
        );
        $stmt->bind_param('i', $trainingId);
        $stmt->execute();
        return (int)$stmt->get_result()->fetch_row()[0];
    }

    // ─── Create training program ──────────────────────────────────────────────
    public function create(array $data, int $createdBy): int {
        $stmt = $this->db->prepare("
            INSERT INTO training_programs
                (program_name, description, created_by, start_date, end_date,
                 max_participants, location, status)
            VALUES (?,?,?,?,?,?,?,?)
        ");
        $stmt->bind_param(
            'ssississ',
            $data['program_name'],
            $data['description']    ?? null,
            $createdBy,
            $data['start_date']     ?? null,
            $data['end_date']       ?? null,
            $data['max_participants'] ?? null,
            $data['location'],
            $data['status']         ?? 'upcoming'
        );
        $stmt->execute();
        $id = $this->db->insert_id;

        if (!empty($data['skills'])) {
            $this->syncSkills($id, $data['skills']);
        }
        return $id;
    }

    // ─── Update training program ──────────────────────────────────────────────
    public function update(int $id, array $data): bool {
        $allowed = ['program_name','description','start_date','end_date','max_participants','location','status'];
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
            $stmt = $this->db->prepare("UPDATE training_programs SET " . implode(', ', $sets) . " WHERE id = ?");
            $stmt->bind_param($types, ...$params);
            $updated = $stmt->execute();
        }
        if (isset($data['skills'])) {
            $this->syncSkills($id, $data['skills']);
        }
        return $updated;
    }

    // ─── Sync skills for training ─────────────────────────────────────────────
    public function syncSkills(int $trainingId, array $skillIds): void {
        $del = $this->db->prepare("DELETE FROM training_skills WHERE training_id = ?");
        $del->bind_param('i', $trainingId);
        $del->execute();
        foreach ($skillIds as $skillId) {
            $ins = $this->db->prepare("INSERT INTO training_skills (training_id, skill_id) VALUES (?,?)");
            $ins->bind_param('ii', $trainingId, $skillId);
            $ins->execute();
        }
    }

    // ─── Enroll a user ────────────────────────────────────────────────────────
    public function enroll(int $trainingId, int $userId): bool {
        $check = $this->db->prepare("SELECT id FROM user_training WHERE user_id = ? AND training_id = ?");
        $check->bind_param('ii', $userId, $trainingId);
        $check->execute();
        if ($check->get_result()->num_rows > 0) return false;

        $stmt = $this->db->prepare("INSERT INTO user_training (user_id, training_id) VALUES (?,?)");
        $stmt->bind_param('ii', $userId, $trainingId);
        return $stmt->execute();
    }

    // ─── Mark a participant as completed ─────────────────────────────────────
    public function markCompleted(int $trainingId, int $userId, string $completionDate, ?string $certPath): bool {
        $stmt = $this->db->prepare("
            UPDATE user_training
            SET status = 'completed', completion_date = ?, certificate_path = ?
            WHERE training_id = ? AND user_id = ?
        ");
        $stmt->bind_param('ssii', $completionDate, $certPath, $trainingId, $userId);
        return $stmt->execute();
    }

    // ─── Get training history for a user ─────────────────────────────────────
    public function getByUser(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT ut.*, tp.program_name, tp.location, tp.start_date, tp.end_date
            FROM user_training ut
            JOIN training_programs tp ON tp.id = ut.training_id
            WHERE ut.user_id = ?
            ORDER BY ut.id DESC
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // ─── Paginated list with status filter ───────────────────────────────────
    public function getAll(string $status = '', int $limit = 10, int $offset = 0): array {
        $where  = "WHERE 1=1";
        $params = []; $types = '';

        if ($status) {
            $where   .= " AND tp.status = ?";
            $params[] = $status;
            $types   .= 's';
        }

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM training_programs tp $where");
        if ($types) $countStmt->bind_param($types, ...$params);
        $countStmt->execute();
        $total = $countStmt->get_result()->fetch_row()[0];

        $params[] = $limit; $params[] = $offset;
        $types   .= 'ii';
        $stmt = $this->db->prepare("
            SELECT tp.*,
                   u.first_name, u.last_name,
                   (SELECT COUNT(*) FROM user_training ut
                    WHERE ut.training_id = tp.id AND ut.status != 'dropped') AS enrolled_count
            FROM training_programs tp
            JOIN users u ON u.id = tp.created_by
            $where
            ORDER BY tp.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $programs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($programs as &$prog) {
            $prog['skills'] = $this->getSkills($prog['id']);
        }

        return ['data' => $programs, 'total' => (int)$total];
    }
}