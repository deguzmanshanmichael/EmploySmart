<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/validator.php';

class TrainingController {

    public function getAll() {
        $payload = requireAuth();
        [$page, $limit, $offset] = getPaginationParams();
        $status = getQueryParam('status', '');
        $search = getQueryParam('search', '');
        $db = getDB();

        // Validate status if provided
        if ($status) {
            $allowedStatuses = ['upcoming', 'ongoing', 'completed', 'cancelled'];
            if (!validateEnum($status, $allowedStatuses)) {
                sendError('Invalid status filter', 422);
            }
        }

        $where = "WHERE tp.archived = FALSE";
        $params = []; $types = '';
        if ($status) { $where .= " AND tp.status = ?"; $params[] = $status; $types .= 's'; }
        if ($search) { $where .= " AND tp.program_name LIKE ?"; $params[] = '%' . $search . '%'; $types .= 's'; }

        $countStmt = $db->prepare("SELECT COUNT(*) FROM training_programs tp $where");
        if ($types) $countStmt->bind_param($types, ...$params);
        $countStmt->execute();
        $total = $countStmt->get_result()->fetch_row()[0];

        $params[] = $limit; $params[] = $offset; $types .= 'ii';
        $stmt = $db->prepare("SELECT tp.*, u.first_name, u.last_name, (SELECT COUNT(*) FROM user_training ut WHERE ut.training_id = tp.id AND ut.status != 'dropped') as enrolled_count FROM training_programs tp JOIN users u ON u.id = tp.created_by $where ORDER BY tp.created_at DESC LIMIT ? OFFSET ?");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $programs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($programs as &$prog) {
            $sStmt = $db->prepare("SELECT s.id, s.skill_name FROM training_skills ts JOIN skills s ON s.id = ts.skill_id WHERE ts.training_id = ?");
            $sStmt->bind_param('i', $prog['id']);
            $sStmt->execute();
            $prog['skills'] = $sStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }

        sendPaginated($programs, $total, $page, $limit);
    }

    public function getOne($id) {
        $db = getDB();
        $stmt = $db->prepare("SELECT tp.*, u.first_name, u.last_name FROM training_programs tp JOIN users u ON u.id = tp.created_by WHERE tp.id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $prog = $stmt->get_result()->fetch_assoc();
        if (!$prog) sendError('Training not found', 404);

        $sStmt = $db->prepare("SELECT s.id, s.skill_name FROM training_skills ts JOIN skills s ON s.id = ts.skill_id WHERE ts.training_id = ?");
        $sStmt->bind_param('i', $id);
        $sStmt->execute();
        $prog['skills'] = $sStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $eStmt = $db->prepare("SELECT ut.*, u.first_name, u.last_name, u.email FROM user_training ut JOIN users u ON u.id = ut.user_id WHERE ut.training_id = ?");
        $eStmt->bind_param('i', $id);
        $eStmt->execute();
        $prog['participants'] = $eStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        sendSuccess('Training data', $prog);
    }

    public function create() {
        $payload = requireRole(['clcdo', 'admin']);
        $data = getJsonBody();
        $errors = validateRequired($data, ['program_name', 'location']);
        if (!empty($errors)) sendError('Validation failed', 422, $errors);

        // Validate field formats
        if (!validateStringLength($data['program_name'], 3, 200)) sendError('Program name must be 3-200 characters', 422);
        if (!validateStringLength($data['location'], 2, 100)) sendError('Location must be 2-100 characters', 422);
        
        if (isset($data['description']) && $data['description'] && !validateStringLength($data['description'], 10, 5000)) {
            sendError('Description must be 10-5000 characters', 422);
        }
        
        if (isset($data['start_date']) && $data['start_date'] && !validateDate($data['start_date'], 'Y-m-d')) {
            sendError('Invalid start date format', 422);
        }
        
        if (isset($data['end_date']) && $data['end_date'] && !validateDate($data['end_date'], 'Y-m-d')) {
            sendError('Invalid end date format', 422);
        }
        
        if (isset($data['max_participants']) && $data['max_participants'] && !validateNumericRange($data['max_participants'], 1, 10000)) {
            sendError('Max participants must be between 1 and 10000', 422);
        }
        
        // Validate status
        $status = $data['status'] ?? 'upcoming';
        $allowedStatuses = ['upcoming', 'ongoing', 'completed', 'cancelled'];
        if (!validateEnum($status, $allowedStatuses)) sendError('Invalid status', 422);
        
        // Validate skills array
        if (isset($data['skills']) && !validateArrayLength($data['skills'], 20)) {
            sendError('Too many skills (max 20)', 422);
        }

        // Convert empty strings to null for optional fields
        $data['description'] = $data['description'] ?: null;
        $data['start_date'] = $data['start_date'] ?: null;
        $data['end_date'] = $data['end_date'] ?: null;
        $data['max_participants'] = $data['max_participants'] ? (int)$data['max_participants'] : null;

        $db = getDB();
        $stmt = $db->prepare("INSERT INTO training_programs (program_name,description,created_by,start_date,end_date,max_participants,location,status) VALUES (?,?,?,?,?,?,?,?)");
        $createdBy = (int)$payload['sub'];
        $status = $data['status'] ?? 'upcoming';
        $stmt->bind_param('ssisssss',
            $data['program_name'], $data['description'], $createdBy,
            $data['start_date'], $data['end_date'],
            $data['max_participants'], $data['location'], $status
        );
        if (!$stmt->execute()) {
            error_log('Training create failed: ' . $stmt->error);
            sendError('Failed to create training: ' . $stmt->error, 500);
        }
        $trainingId = $db->insert_id;

        if (!empty($data['skills'])) {
            foreach ($data['skills'] as $skillId) {
                $skillIdInt = (int)$skillId;
                $sStmt = $db->prepare("INSERT INTO training_skills (training_id, skill_id) VALUES (?,?)");
                $sStmt->bind_param('ii', $trainingId, $skillIdInt);
                if (!$sStmt->execute()) {
                    error_log('Skill insert failed: ' . $sStmt->error);
                }
            }
        }
        sendSuccess('Training program created', ['training_id' => $trainingId], 201);
    }

    public function update($id) {
        requireRole(['clcdo', 'admin']);
        $data = getJsonBody();
        $db = getDB();

        $allowed = ['program_name','description','start_date','end_date','max_participants','location','status'];
        $sets = []; $params = []; $types = '';
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "$field = ?"; $params[] = $data[$field]; $types .= 's';
            }
        }
        if (!empty($sets)) {
            $params[] = $id; $types .= 'i';
            $stmt = $db->prepare("UPDATE training_programs SET " . implode(',', $sets) . " WHERE id = ?");
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
        }

        if (isset($data['skills'])) {
            $deleteStmt = $db->prepare("DELETE FROM training_skills WHERE training_id = ?");
            $deleteStmt->bind_param('i', $id);
            $deleteStmt->execute();
            foreach ($data['skills'] as $skillId) {
                $sStmt = $db->prepare("INSERT INTO training_skills (training_id, skill_id) VALUES (?,?)");
                $sStmt->bind_param('ii', $id, $skillId);
                $sStmt->execute();
            }
        }
        sendSuccess('Training updated');
    }

    public function enroll($trainingId) {
        $payload = requireRole(['clcdo', 'admin', 'peso']);
        $data = getJsonBody();
        if (empty($data['user_id'])) sendError('user_id required', 422);
        $userId = $data['user_id'];

        $db = getDB();
        $stmt = $db->prepare("SELECT id, program_name, max_participants FROM training_programs WHERE id = ?");
        $stmt->bind_param('i', $trainingId);
        $stmt->execute();
        $prog = $stmt->get_result()->fetch_assoc();
        if (!$prog) sendError('Training not found', 404);

        $countStmt = $db->prepare("SELECT COUNT(*) FROM user_training WHERE training_id = ? AND status != 'dropped'");
        $countStmt->bind_param('i', $trainingId);
        $countStmt->execute();
        $enrolled = $countStmt->get_result()->fetch_row()[0];
        if ($prog['max_participants'] && $enrolled >= $prog['max_participants']) sendError('Training is full', 409);

        $check = $db->prepare("SELECT id FROM user_training WHERE user_id = ? AND training_id = ?");
        $check->bind_param('ii', $userId, $trainingId);
        $check->execute();
        if ($check->get_result()->num_rows > 0) sendError('User already enrolled', 409);

        $stmt = $db->prepare("INSERT INTO user_training (user_id, training_id) VALUES (?,?)");
        $stmt->bind_param('ii', $userId, $trainingId);
        $stmt->execute();

        $notifyStmt = $db->prepare("INSERT INTO notifications (target_user_id, title, message, priority, category) VALUES (?,?,?,?,?)");
        if ($notifyStmt) {
            $title = 'Enrolled in training program';
            $message = sprintf('You have been enrolled in the "%s" program.', $prog['program_name'] ?? 'selected');
            $priority = 'normal';
            $category = 'training';
            $notifyStmt->bind_param('issss', $userId, $title, $message, $priority, $category);
            $notifyStmt->execute();
        }

        sendSuccess('Enrolled successfully', null, 201);
    }

    public function completeEnrollment($trainingId, $userId) {
        requireRole(['clcdo', 'admin']);
        $data = getJsonBody();
        $db = getDB();

        $cert = null;
        if (isset($_FILES['certificate'])) {
            $file = $_FILES['certificate'];
            $dir = __DIR__ . '/../uploads/training_certificates/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $filename = "cert_{$userId}_{$trainingId}_" . time() . ".pdf";
            move_uploaded_file($file['tmp_name'], $dir . $filename);
            $cert = "uploads/training_certificates/$filename";
        }

        $progStmt = $db->prepare("SELECT program_name FROM training_programs WHERE id = ?");
        $progStmt->bind_param('i', $trainingId);
        $progStmt->execute();
        $prog = $progStmt->get_result()->fetch_assoc();

        $date = $data['completion_date'] ?? date('Y-m-d');
        $stmt = $db->prepare("UPDATE user_training SET status='completed', completion_date=?, certificate_path=? WHERE training_id=? AND user_id=?");
        $stmt->bind_param('ssii', $date, $cert, $trainingId, $userId);
        $stmt->execute();

        $notifyStmt = $db->prepare("INSERT INTO notifications (target_user_id, title, message, priority, category) VALUES (?,?,?,?,?)");
        if ($notifyStmt) {
            $title = 'Training completed';
            $message = sprintf('You have completed the "%s" training program.', $prog['program_name'] ?? 'selected');
            $priority = 'normal';
            $category = 'training';
            $notifyStmt->bind_param('issss', $userId, $title, $message, $priority, $category);
            $notifyStmt->execute();
        }

        // Auto-update user skills from training
        $sStmt = $db->prepare("SELECT skill_id FROM training_skills WHERE training_id = ?");
        $sStmt->bind_param('i', $trainingId);
        $sStmt->execute();
        $skills = $sStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($skills as $skill) {
            $exists = $db->prepare("SELECT id FROM user_skills WHERE user_id = ? AND skill_id = ?");
            $exists->bind_param('ii', $userId, $skill['skill_id']);
            $exists->execute();
            if ($exists->get_result()->num_rows === 0) {
                $ins = $db->prepare("INSERT INTO user_skills (user_id, skill_id) VALUES (?,?)");
                $ins->bind_param('ii', $userId, $skill['skill_id']);
                $ins->execute();
            }
        }
        sendSuccess('Training completed and skills updated');
    }

    public function getUserTrainings($userId) {
        requireAuth();
        $db = getDB();
        $stmt = $db->prepare("SELECT ut.*, tp.program_name, tp.location, tp.start_date, tp.end_date FROM user_training ut JOIN training_programs tp ON tp.id = ut.training_id WHERE ut.user_id = ? ORDER BY ut.id DESC");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $trainings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        sendSuccess('Training history', $trainings);
    }

    public function delete($id) {
        $payload = requireRole(['clcdo', 'admin']);
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM training_programs WHERE id = ? AND created_by = ?");
        $stmt->bind_param('ii', $id, $payload['sub']);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) sendError('Training not found or not authorized', 404);
        
        $deleteStmt = $db->prepare("UPDATE training_programs SET archived = TRUE WHERE id = ?");
        $deleteStmt->bind_param('i', $id);
        $deleteStmt->execute();
        sendSuccess('Training program archived');
    }
}