<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/validator.php';

class NotificationController {
    public function getAll() {
        $payload = requireAuth();
        $db = getDB();

        $stmt = $db->prepare(
            "SELECT id, target_user_id, title, message, priority, category, is_read, created_at FROM notifications WHERE target_user_id = ? ORDER BY created_at DESC"
        );
        $stmt->bind_param('i', $payload['sub']);
        $stmt->execute();
        $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        sendSuccess('Notifications loaded', $notifications);
    }

    public function create() {
        $payload = requireAuth();
        $data = getJsonBody();

        $errors = [];
        if (empty($data['title'])) {
            $errors['title'] = 'Title is required';
        }
        if (empty($data['message'])) {
            $errors['message'] = 'Message is required';
        }
        if (!empty($errors)) {
            sendError('Validation failed', 422, $errors);
        }

        $title = sanitize($data['title']);
        $message = sanitize($data['message']);
        $priority = in_array($data['priority'] ?? 'normal', ['normal', 'high'], true) ? $data['priority'] : 'normal';
        $category = sanitize($data['category'] ?? 'general');
        $targetUserId = !empty($data['target_user_id']) ? (int)$data['target_user_id'] : $payload['sub'];

        if ($targetUserId !== $payload['sub'] && !in_array($payload['role'], ['admin', 'peso', 'clcdo'], true)) {
            sendError('Forbidden', 403);
        }

        $db = getDB();
        $stmt = $db->prepare(
            "INSERT INTO notifications (target_user_id, title, message, priority, category) VALUES (?,?,?,?,?)"
        );
        $stmt->bind_param('issss', $targetUserId, $title, $message, $priority, $category);
        if (!$stmt->execute()) {
            sendError('Failed to create notification', 500, ['sql_error' => $stmt->error]);
        }

        $notification = [
            'id' => $db->insert_id,
            'target_user_id' => $targetUserId,
            'title' => $title,
            'message' => $message,
            'priority' => $priority,
            'category' => $category,
            'is_read' => false,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        sendSuccess('Notification created', $notification, 201);
    }

    public function markRead($id) {
        $payload = requireAuth();
        $db = getDB();
        $stmt = $db->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND target_user_id = ?");
        $stmt->bind_param('ii', $id, $payload['sub']);
        $stmt->execute();
        sendSuccess('Notification marked as read');
    }

    public function markAllRead() {
        $payload = requireAuth();
        $db = getDB();
        $stmt = $db->prepare("UPDATE notifications SET is_read = TRUE WHERE target_user_id = ?");
        $stmt->bind_param('i', $payload['sub']);
        $stmt->execute();
        sendSuccess('All notifications marked as read');
    }
}
