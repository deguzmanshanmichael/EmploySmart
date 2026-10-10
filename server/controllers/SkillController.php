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

    /**
     * POST /skills/user/{id}/custom — a jobseeker adds a skill that is not in the list.
     * Reuses an existing catalog skill when the name (or a known synonym) matches,
     * otherwise creates it, then adds it to the user's skills.
     */
    public function addCustomUserSkill($userId) {
        $payload = requireAuth();
        verifyOwnership($userId, $payload, ['admin']);
        require_once __DIR__ . '/../helpers/rate_limiter.php';
        require_once __DIR__ . '/../services/resume/SkillNormalizer.php';
        $data = json_decode((string)file_get_contents('php://input'), true) ?: [];
        $raw = (string)($data['skill_name'] ?? '');
        if (preg_match('/[<>]/', $raw)) sendError('Skill names cannot contain < or >.', 422);
        $name = trim(preg_replace('/\s+/u', ' ', $raw));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 60) sendError('Skill name must be 2 to 60 characters.', 422);
        if (!preg_match('/^[\p{L}\p{N}][\p{L}\p{N} +#.&\/\'()\-]*$/u', $name)) sendError('Skill names may contain letters, numbers, spaces and + # . & / \' ( ) -', 422);
        if (!throttleRequest('custom_skill:' . (int)$payload['sub'], 30, 3600)) sendError('Too many new skills added. Please try again later.', 429);

        $db = getDB();
        $catalog = $db->query("SELECT id, skill_name, archived FROM skills")->fetch_all(MYSQLI_ASSOC);
        $skill = null;
        foreach ($catalog as $row) {
            if (mb_strtolower($row['skill_name']) === mb_strtolower($name)) { $skill = $row; break; }
        }
        if ($skill === null) {
            $match = SkillNormalizer::mapToCatalog($name, array_values(array_filter($catalog, fn($r) => !$r['archived'])));
            // Exact/synonym matches reuse the existing skill ("MS Excel" → "Microsoft Excel" if listed);
            // a broader category is not a substitute for the specific skill the user typed.
            if ($match && $match['match'] !== 'broader') $skill = ['id' => $match['id'], 'skill_name' => $match['skill_name'], 'archived' => 0];
        }
        $created = false;
        if ($skill === null) {
            $stmt = $db->prepare("INSERT INTO skills (skill_name) VALUES (?)");
            $stmt->bind_param('s', $name);
            $stmt->execute();
            $skill = ['id' => $db->insert_id, 'skill_name' => $name, 'archived' => 0];
            $created = true;
        } elseif ($skill['archived']) {
            $stmt = $db->prepare("UPDATE skills SET archived = 0 WHERE id = ?");
            $stmt->bind_param('i', $skill['id']);
            $stmt->execute();
        }
        $uid = (int)$userId; $sid = (int)$skill['id'];
        $stmt = $db->prepare("INSERT INTO user_skills (user_id, skill_id) SELECT ?, ? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM user_skills WHERE user_id = ? AND skill_id = ?)");
        $stmt->bind_param('iiii', $uid, $sid, $uid, $sid);
        $stmt->execute();
        sendSuccess($created ? 'New skill added' : 'Skill added', ['id' => $sid, 'skill_name' => $skill['skill_name'], 'created' => $created], $created ? 201 : 200);
    }

    public function updateUserSkills($userId) {
        $payload = requireAuth();
        verifyOwnership($userId, $payload, ['admin']);
        
        $data = getJsonBody();
        if (!isset($data['skills']) || !is_array($data['skills'])) sendError('Skills array required', 422);
        $data['skills'] = array_values(array_unique(array_map('intval', array_filter($data['skills'], 'validateInteger'))));
        
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