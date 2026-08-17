<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/validator.php';

class MessageController {
    private function getApplicationContext($applicationId, $payload) {
        $db = getDB();
        $applicationId = (int) $applicationId;

        if ($payload['role'] === 'employer') {
            $stmt = $db->prepare(
                "SELECT a.id, a.user_id AS jobseeker_id, a.job_id, j.employer_id, e.user_id AS employer_user_id " .
                "FROM applications a " .
                "JOIN jobs j ON j.id = a.job_id " .
                "JOIN employers e ON e.id = j.employer_id " .
                "WHERE a.id = ? AND e.user_id = ?"
            );
            $stmt->bind_param('ii', $applicationId, $payload['sub']);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if (!$row) {
                sendError('Forbidden', 403);
            }
            return $row;
        }

        if ($payload['role'] === 'jobseeker') {
            $stmt = $db->prepare(
                "SELECT a.id, a.user_id AS jobseeker_id, a.job_id, j.employer_id " .
                "FROM applications a JOIN jobs j ON j.id = a.job_id WHERE a.id = ? AND a.user_id = ?"
            );
            $stmt->bind_param('ii', $applicationId, $payload['sub']);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if (!$row) {
                sendError('Forbidden', 403);
            }
            return $row;
        }

        sendError('Forbidden', 403);
    }

    public function getThread($applicationId) {
        $payload = requireRole(['employer', 'jobseeker', 'peso', 'admin']);
        $context = $this->getApplicationContext($applicationId, $payload);
        $db = getDB();

        $threadStmt = $db->prepare('SELECT * FROM message_threads WHERE application_id = ? LIMIT 1');
        $threadStmt->bind_param('i', $applicationId);
        $threadStmt->execute();
        $thread = $threadStmt->get_result()->fetch_assoc();

        if (!$thread) {
            sendSuccess('No messages yet', [
                'thread' => null,
                'messages' => [],
                'can_send' => $payload['role'] === 'employer',
            ]);
        }

        $messagesStmt = $db->prepare(
            'SELECT m.*, u.first_name, u.last_name FROM messages m LEFT JOIN users u ON u.id = m.sender_id WHERE m.thread_id = ? ORDER BY m.created_at ASC'
        );
        $messagesStmt->bind_param('i', $thread['id']);
        $messagesStmt->execute();
        $messages = $messagesStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        sendSuccess('Messages loaded', [
            'thread' => $thread,
            'messages' => $messages,
            'can_send' => $payload['role'] === 'employer',
        ]);
    }

    public function sendMessage($applicationId) {
        $payload = requireRole(['employer']);
        $context = $this->getApplicationContext($applicationId, $payload);
        $data = getJsonBody();
        $errors = validateRequired($data, ['message']);
        if (!empty($errors)) {
            sendError('Validation failed', 422, $errors);
        }

        $messageText = trim((string) ($data['message'] ?? ''));
        if ($messageText === '') {
            sendError('Message is required', 422);
        }
        if (strlen($messageText) > 4000) {
            sendError('Message is too long', 422);
        }

        $db = getDB();
        $threadStmt = $db->prepare('SELECT id FROM message_threads WHERE application_id = ? LIMIT 1');
        $threadStmt->bind_param('i', $applicationId);
        $threadStmt->execute();
        $thread = $threadStmt->get_result()->fetch_assoc();

        $threadId = null;
        if (!$thread) {
            $insertThread = $db->prepare(
                'INSERT INTO message_threads (application_id, employer_id, jobseeker_id, initiated_by_role) VALUES (?, ?, ?, ?)'
            );
            $initiatedBy = 'employer';
            $insertThread->bind_param('iiis', $applicationId, $context['employer_id'], $context['jobseeker_id'], $initiatedBy);
            if (!$insertThread->execute()) {
                sendError('Unable to start messages', 500);
            }
            $threadId = $db->insert_id;
        } else {
            $threadId = (int) $thread['id'];
        }

        $insertMessage = $db->prepare(
            'INSERT INTO messages (thread_id, sender_id, sender_role, receiver_id, message_text) VALUES (?, ?, ?, ?, ?)'
        );
        $senderRole = 'employer';
        $receiverId = (int) $context['jobseeker_id'];
        $insertMessage->bind_param('iiiis', $threadId, $payload['sub'], $senderRole, $receiverId, $messageText);
        if (!$insertMessage->execute()) {
            sendError('Unable to send message', 500);
        }

        sendSuccess('Message sent', ['thread_id' => $threadId], 201);
    }
}
