<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/validator.php';

class SkillController {

    public function getAll() {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM skills WHERE archived = ? ORDER BY skill_name");
        $archived = FALSE;
        $stmt->bind_param("i", $archived);
        $stmt->execute();
        $skills = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        sendSuccess('Skills', $skills);
    }

    public function create() {
        requireRole(['admin', 'peso', 'clcdo']);
        $data = getJsonBody();
        $errors = validateRequired($data, ['skill_name']);
        if (!empty($errors)) sendError('Validation failed', 422, $errors);
        
        // Validate skill name format
        if (!validateStringLength($data['skill_name'], 2, 100)) {
            sendError('Skill name must be 2-100 characters', 422);
        }
        
        $db = getDB();
        
        // Check if skill already exists
        $stmt = $db->prepare("SELECT id FROM skills WHERE LOWER(skill_name) = LOWER(?) AND archived = FALSE");
        $stmt->bind_param('s', $data['skill_name']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            sendError('Skill already exists', 409);
        }
        
        $stmt = $db->prepare("INSERT INTO skills (skill_name) VALUES (?)");
        $stmt->bind_param('s', $data['skill_name']);
        if (!$stmt->execute()) sendError('Failed to create skill', 500);
        
        sendSuccess('Skill created', ['id' => $db->insert_id], 201);
    }

    public function delete($id) {
        requireRole(['admin']);
        $db = getDB();
        $stmt = $db->prepare("UPDATE skills SET archived = TRUE WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        sendSuccess('Skill archived');
    }

    public function getUserSkills($userId) {
        requireAuth();
        $db = getDB();
        $stmt = $db->prepare("SELECT s.id, s.skill_name FROM user_skills us JOIN skills s ON s.id = us.skill_id WHERE us.user_id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $skills = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        sendSuccess('User skills', $skills);
    }

    public function getUserSkillProgression($userId) {
        $payload = requireAuth();
        verifyOwnership($userId, $payload, ['admin']);

        $db = getDB();
        $skillsStmt = $db->prepare("SELECT s.id, s.skill_name FROM user_skills us JOIN skills s ON s.id = us.skill_id WHERE us.user_id = ? ORDER BY s.skill_name");
        $skillsStmt->bind_param('i', $userId);
        $skillsStmt->execute();
        $skills = $skillsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $trainingsStmt = $db->prepare("SELECT ut.id, ut.status, ut.completion_date, tp.program_name, tp.location, tp.start_date, tp.end_date FROM user_training ut JOIN training_programs tp ON tp.id = ut.training_id WHERE ut.user_id = ? ORDER BY ut.completion_date DESC, ut.id DESC");
        $trainingsStmt->bind_param('i', $userId);
        $trainingsStmt->execute();
        $allTrainings = $trainingsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $completedTrainings = array_values(array_filter($allTrainings, function ($training) {
            return strtolower((string)($training['status'] ?? '')) === 'completed';
        }));

        $progression = [];
        foreach ($allTrainings as $training) {
            $status = strtolower((string)($training['status'] ?? ''));
            $progression[] = [
                'date' => $training['completion_date'] ?? ($training['start_date'] ?? null),
                'program_name' => $training['program_name'],
                'location' => $training['location'] ?? null,
                'status' => $status ?: 'enrolled',
                'skill_count' => count($skills),
            ];
        }

        sendSuccess('Skill progression', [
            'skill_count' => count($skills),
            'completed_training_count' => count($completedTrainings),
            'total_training_count' => count($allTrainings),
            'progression' => $progression,
            'latest_skills' => array_slice($skills, 0, 6),
        ]);
    }

    public function updateUserSkills($userId) {
        $payload = requireAuth();
        verifyOwnership($userId, $payload, ['admin']);
        
        $data = getJsonBody();
        if (!isset($data['skills']) || !is_array($data['skills'])) sendError('Skills array required', 422);
        
        // Validate skills array length
        if (!validateArrayLength($data['skills'], 100)) {
            sendError('Too many skills (max 100)', 422);
        }

        $db = getDB();
        
        // Validate all skills exist before updating
        foreach ($data['skills'] as $skillId) {
            if (!validateInteger($skillId)) {
                sendError('Invalid skill ID format', 422);
            }
            
            $stmt = $db->prepare("SELECT id FROM skills WHERE id = ? AND archived = FALSE");
            $stmt->bind_param('i', $skillId);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                sendError('One or more skills not found', 404);
            }
        }
        
        // Delete and re-add skills
        $stmt = $db->prepare("DELETE FROM user_skills WHERE user_id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();

        foreach ($data['skills'] as $skillId) {
            $sStmt = $db->prepare("INSERT INTO user_skills (user_id, skill_id) VALUES (?,?)");
            $sStmt->bind_param('ii', $userId, $skillId);
            if (!$sStmt->execute()) {
                sendError('Failed to update skills', 500);
            }
        }
        sendSuccess('Skills updated');
    }
}