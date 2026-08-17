<?php
require_once __DIR__ . '/../config/database.php';

class Skill {

    private $db;

    public function __construct() {
        $this->db = getDB();
    }

    // ─── Get all skills ───────────────────────────────────────────────────────
    public function getAll(): array {
        return $this->db->query("SELECT * FROM skills ORDER BY skill_name")
                        ->fetch_all(MYSQLI_ASSOC);
    }

    // ─── Find skill by ID ─────────────────────────────────────────────────────
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM skills WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    // ─── Create a new skill ───────────────────────────────────────────────────
    public function create(string $skillName): int {
        $stmt = $this->db->prepare("INSERT INTO skills (skill_name) VALUES (?)");
        $stmt->bind_param('s', $skillName);
        if (!$stmt->execute()) return 0;
        return $this->db->insert_id;
    }

    // ─── Delete a skill ───────────────────────────────────────────────────────
    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM skills WHERE id = ?");
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }

    // ─── Get skills for a user ────────────────────────────────────────────────
    public function getByUser(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT s.id, s.skill_name
            FROM user_skills us
            JOIN skills s ON s.id = us.skill_id
            WHERE us.user_id = ?
            ORDER BY s.skill_name
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // ─── Get skill IDs for a user ─────────────────────────────────────────────
    public function getIdsByUser(int $userId): array {
        $stmt = $this->db->prepare("SELECT skill_id FROM user_skills WHERE user_id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        return array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'skill_id');
    }

    // ─── Replace all skills for a user ───────────────────────────────────────
    public function syncForUser(int $userId, array $skillIds): void {
        $del = $this->db->prepare("DELETE FROM user_skills WHERE user_id = ?");
        $del->bind_param('i', $userId);
        $del->execute();

        foreach ($skillIds as $skillId) {
            $ins = $this->db->prepare("INSERT INTO user_skills (user_id, skill_id) VALUES (?,?)");
            $ins->bind_param('ii', $userId, $skillId);
            $ins->execute();
        }
    }

    // ─── Add a single skill to a user (no duplicate) ─────────────────────────
    public function addToUser(int $userId, int $skillId): void {
        $check = $this->db->prepare("SELECT id FROM user_skills WHERE user_id = ? AND skill_id = ?");
        $check->bind_param('ii', $userId, $skillId);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            $ins = $this->db->prepare("INSERT INTO user_skills (user_id, skill_id) VALUES (?,?)");
            $ins->bind_param('ii', $userId, $skillId);
            $ins->execute();
        }
    }
}