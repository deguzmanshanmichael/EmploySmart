<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/validator.php';

class FeedbackController {

    public function create() {
        $payload = requireAuth();
        $data = getJsonBody();
        $errors = validateRequired($data, ['target_type', 'target_id', 'feedback']);
        if (!empty($errors)) sendError('Validation failed', 422, $errors);

        if (!in_array($data['target_type'], ['employer', 'jobseeker', 'placement'], true)) {
            sendError('Invalid target type', 422);
        }

        if (!isset($data['rating']) || !is_numeric($data['rating'])) {
            $data['rating'] = 5;
        }

        $db = getDB();
        $stmt = $db->prepare("INSERT INTO feedback_threads (sender_id, sender_role, target_type, target_id, rating, feedback, status) VALUES (?, ?, ?, ?, ?, ?, 'open')");
        $stmt->bind_param('isssis', $payload['sub'], $payload['role'], $data['target_type'], $data['target_id'], $data['rating'], $data['feedback']);
        $stmt->execute();

        sendSuccess('Feedback submitted', ['id' => $db->insert_id], 201);
    }

    public function listForTarget($targetType, $targetId) {
        requireRole(['admin', 'peso', 'clcdo', 'employer']);
        $db = getDB();
        $stmt = $db->prepare("SELECT ft.*, u.first_name, u.last_name FROM feedback_threads ft LEFT JOIN users u ON u.id = ft.sender_id WHERE ft.target_type = ? AND ft.target_id = ? ORDER BY ft.created_at DESC");
        $stmt->bind_param('si', $targetType, $targetId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        sendSuccess('Feedback history', $rows);
    }
}
