<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/validator.php';

/**
 * Feedback between PESO and employers about job placements (accepted applications),
 * plus the jobseeker self-feedback used by the Growth Center.
 */
class FeedbackController {

    public const OUTCOMES = [
        'performing_well' => 'Performing well',
        'retained' => 'Retained / regularized',
        'needs_training' => 'Needs additional training',
        'adjustment_issues' => 'Attendance or adjustment concerns',
        'resigned' => 'Resigned',
        'separated' => 'Separated / terminated',
        'other' => 'Other',
    ];
    private const STAFF = ['admin', 'peso'];

    // ─── Generic feedback (kept for the jobseeker Growth Center) ─────────────

    public function create() {
        $payload = requireAuth();
        $data = getJsonBody();
        $errors = validateRequired($data, ['target_type', 'target_id', 'feedback']);
        if (!empty($errors)) sendError('Validation failed', 422, $errors);
        if (!in_array($data['target_type'], ['employer', 'jobseeker', 'placement'], true)) {
            sendError('Invalid target type', 422);
        }
        $role = $payload['role'];
        $targetId = (int)$data['target_id'];
        // Placement feedback goes through its own endpoint; employer feedback is PESO-only;
        // jobseekers may only leave feedback about themselves.
        if ($data['target_type'] === 'placement') sendError('Use the placement feedback page for placement feedback.', 422);
        if ($data['target_type'] === 'employer' && !in_array($role, self::STAFF, true)) sendError('Forbidden', 403);
        if ($data['target_type'] === 'jobseeker' && !in_array($role, self::STAFF, true) && (int)$payload['sub'] !== $targetId) sendError('Forbidden', 403);
        $rating = $this->rating($data['rating'] ?? 5);
        $feedback = $this->text($data['feedback'], 2000);
        if ($feedback === '') sendError('Feedback is required', 422);

        $db = getDB();
        $stmt = $db->prepare("INSERT INTO feedback_threads (sender_id, sender_role, target_type, target_id, rating, feedback, status) VALUES (?, ?, ?, ?, ?, ?, 'open')");
        $stmt->bind_param('issiis', $payload['sub'], $role, $data['target_type'], $targetId, $rating, $feedback);
        $stmt->execute();
        sendSuccess('Feedback submitted', ['id' => $db->insert_id], 201);
    }

    public function listForTarget($targetType, $targetId) {
        $payload = requireRole(['admin', 'peso', 'clcdo', 'employer']);
        $targetId = (int)$targetId;
        if ($payload['role'] === 'employer') {
            $own = $targetType === 'placement'
                ? $this->employerOwnsPlacement($targetId, (int)$payload['sub'])
                : ($targetType === 'employer' && $this->employerIdForUser((int)$payload['sub']) === $targetId);
            if (!$own) sendError('Forbidden', 403);
        }
        $db = getDB();
        $stmt = $db->prepare("SELECT ft.*, u.first_name, u.last_name FROM feedback_threads ft LEFT JOIN users u ON u.id = ft.sender_id WHERE ft.target_type = ? AND ft.target_id = ? ORDER BY ft.created_at DESC");
        $stmt->bind_param('si', $targetType, $targetId);
        $stmt->execute();
        sendSuccess('Feedback history', $stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    }

    // ─── Placements ──────────────────────────────────────────────────────────

    /** GET /feedback/placements — accepted applications visible to the caller. */
    public function placements() {
        $payload = requireRole(['admin', 'peso', 'employer']);
        $db = getDB();
        $where = "a.application_status = 'accepted'";
        $types = ''; $params = [];
        if ($payload['role'] === 'employer') {
            $where .= ' AND e.user_id = ?';
            $types .= 'i'; $params[] = (int)$payload['sub'];
        }
        $search = trim((string)($_GET['search'] ?? ''));
        if ($search !== '') {
            $where .= " AND (j.title LIKE ? OR e.company_name LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
            $like = '%' . mb_substr($search, 0, 100) . '%';
            $types .= 'sss'; array_push($params, $like, $like, $like);
        }
        $sql = "SELECT a.id AS application_id, a.applied_at, j.id AS job_id, j.title AS job_title, j.location,
                       e.id AS employer_id, e.company_name, u.id AS jobseeker_id, u.first_name, u.last_name,
                       (SELECT COUNT(*) FROM feedback_threads f WHERE f.target_type = 'placement' AND f.target_id = a.id) AS thread_count,
                       (SELECT COUNT(*) FROM feedback_threads f WHERE f.target_type = 'placement' AND f.target_id = a.id AND f.status = 'open') AS open_count,
                       (SELECT ROUND(AVG(f.rating), 1) FROM feedback_threads f WHERE f.target_type = 'placement' AND f.target_id = a.id) AS avg_rating,
                       (SELECT f.outcome FROM feedback_threads f WHERE f.target_type = 'placement' AND f.target_id = a.id ORDER BY f.created_at DESC, f.id DESC LIMIT 1) AS latest_outcome,
                       (SELECT MAX(COALESCE(f.updated_at, f.created_at)) FROM feedback_threads f WHERE f.target_type = 'placement' AND f.target_id = a.id) AS last_activity
                FROM applications a
                JOIN jobs j ON j.id = a.job_id
                JOIN employers e ON e.id = j.employer_id
                JOIN users u ON u.id = a.user_id
                WHERE $where
                ORDER BY COALESCE(last_activity, a.applied_at) DESC
                LIMIT 300";
        $stmt = $db->prepare($sql);
        if ($types) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $filter = $_GET['filter'] ?? '';
        if ($filter === 'none') $rows = array_values(array_filter($rows, fn($r) => (int)$r['thread_count'] === 0));
        if ($filter === 'open') $rows = array_values(array_filter($rows, fn($r) => (int)$r['open_count'] > 0));
        foreach ($rows as &$r) {
            $r['outcome_label'] = $r['latest_outcome'] ? (self::OUTCOMES[$r['latest_outcome']] ?? $r['latest_outcome']) : null;
        }
        sendSuccess('Placements', ['placements' => $rows, 'outcomes' => self::OUTCOMES]);
    }

    /** GET /feedback/placements/{applicationId} — placement details with all threads and replies. */
    public function placementThreads($applicationId) {
        $payload = requireRole(['admin', 'peso', 'employer']);
        $placement = $this->authorizedPlacement((int)$applicationId, $payload);
        $db = getDB();
        $stmt = $db->prepare("SELECT ft.id, ft.sender_role, ft.rating, ft.outcome, ft.feedback, ft.status, ft.created_at, ft.updated_at, u.first_name, u.last_name
                              FROM feedback_threads ft LEFT JOIN users u ON u.id = ft.sender_id
                              WHERE ft.target_type = 'placement' AND ft.target_id = ? ORDER BY ft.created_at DESC, ft.id DESC");
        $stmt->bind_param('i', $placement['application_id']);
        $stmt->execute();
        $threads = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        if ($threads) {
            $ids = implode(',', array_map(fn($t) => (int)$t['id'], $threads));
            $replies = $db->query("SELECT r.id, r.thread_id, r.sender_role, r.message, r.created_at, u.first_name, u.last_name FROM feedback_replies r LEFT JOIN users u ON u.id = r.sender_id WHERE r.thread_id IN ($ids) ORDER BY r.created_at, r.id")->fetch_all(MYSQLI_ASSOC);
            $byThread = [];
            foreach ($replies as $r) $byThread[(int)$r['thread_id']][] = $r;
            foreach ($threads as &$t) {
                $t['replies'] = $byThread[(int)$t['id']] ?? [];
                $t['outcome_label'] = $t['outcome'] ? (self::OUTCOMES[$t['outcome']] ?? $t['outcome']) : null;
            }
            unset($t);
        }
        sendSuccess('Placement feedback', ['placement' => $placement, 'threads' => $threads, 'outcomes' => self::OUTCOMES]);
    }

    /** POST /feedback/placements/{applicationId} — start a feedback thread. */
    public function createForPlacement($applicationId) {
        $payload = requireRole(['admin', 'peso', 'employer']);
        $placement = $this->authorizedPlacement((int)$applicationId, $payload);
        $data = $this->rawJson();
        $feedback = $this->text($data['feedback'] ?? '', 2000);
        if (mb_strlen($feedback) < 3) sendError('Please write your feedback (at least 3 characters).', 422);
        $outcome = $data['outcome'] ?? null;
        if ($outcome !== null && $outcome !== '' && !isset(self::OUTCOMES[$outcome])) sendError('Invalid placement outcome', 422);
        $outcome = $outcome ?: null;
        $rating = $this->rating($data['rating'] ?? null);
        if ($rating === null) sendError('Please choose a rating from 1 to 5.', 422);

        $db = getDB();
        $stmt = $db->prepare("INSERT INTO feedback_threads (sender_id, sender_role, target_type, target_id, rating, outcome, feedback, status, updated_at) VALUES (?, ?, 'placement', ?, ?, ?, ?, 'open', NOW())");
        $stmt->bind_param('isiiss', $payload['sub'], $payload['role'], $placement['application_id'], $rating, $outcome, $feedback);
        $stmt->execute();
        $threadId = $db->insert_id;
        $this->notifyOtherParty($placement, $payload, 'New placement feedback', 'New feedback about ' . $placement['jobseeker_name'] . ' (' . $placement['job_title'] . ').');
        sendSuccess('Feedback submitted', ['id' => $threadId], 201);
    }

    /** POST /feedback/thread/{id}/replies */
    public function reply($threadId) {
        $payload = requireRole(['admin', 'peso', 'employer']);
        [$thread, $placement] = $this->authorizedThread((int)$threadId, $payload);
        $data = $this->rawJson();
        $message = $this->text($data['message'] ?? '', 2000);
        if (mb_strlen($message) < 2) sendError('Please write a reply.', 422);
        if ($thread['status'] === 'closed') sendError('This feedback thread is closed. Reopen it to reply.', 409);
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO feedback_replies (thread_id, sender_id, sender_role, message) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('iiss', $thread['id'], $payload['sub'], $payload['role'], $message);
        $stmt->execute();
        $u = $db->prepare("UPDATE feedback_threads SET updated_at = NOW() WHERE id = ?");
        $u->bind_param('i', $thread['id']);
        $u->execute();
        $this->notifyOtherParty($placement, $payload, 'Reply to placement feedback', 'New reply about ' . $placement['jobseeker_name'] . ' (' . $placement['job_title'] . ').');
        sendSuccess('Reply sent', ['id' => $db->insert_id], 201);
    }

    /** PATCH /feedback/thread/{id}/status — open or close a thread. */
    public function setStatus($threadId) {
        $payload = requireRole(['admin', 'peso', 'employer']);
        [$thread] = $this->authorizedThread((int)$threadId, $payload);
        $status = $this->rawJson()['status'] ?? '';
        if (!in_array($status, ['open', 'closed'], true)) sendError('Invalid status', 422);
        $db = getDB();
        $stmt = $db->prepare("UPDATE feedback_threads SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('si', $status, $thread['id']);
        $stmt->execute();
        sendSuccess($status === 'closed' ? 'Feedback thread closed' : 'Feedback thread reopened');
    }

    /** GET /feedback/summary — placement outcome overview for PESO. */
    public function summary() {
        requireRole(['admin', 'peso']);
        $db = getDB();
        $one = fn($sql) => $db->query($sql)->fetch_row()[0];
        $placements = (int)$one("SELECT COUNT(*) FROM applications WHERE application_status = 'accepted'");
        $withFeedback = (int)$one("SELECT COUNT(DISTINCT f.target_id) FROM feedback_threads f JOIN applications a ON a.id = f.target_id AND a.application_status = 'accepted' WHERE f.target_type = 'placement'");
        $threads = (int)$one("SELECT COUNT(*) FROM feedback_threads WHERE target_type = 'placement'");
        $open = (int)$one("SELECT COUNT(*) FROM feedback_threads WHERE target_type = 'placement' AND status = 'open'");
        $avg = $one("SELECT ROUND(AVG(rating), 2) FROM feedback_threads WHERE target_type = 'placement'");

        $outcomes = [];
        foreach ($db->query("SELECT outcome, COUNT(*) c FROM feedback_threads WHERE target_type = 'placement' AND outcome IS NOT NULL GROUP BY outcome")->fetch_all(MYSQLI_ASSOC) as $r) {
            $outcomes[] = ['outcome' => $r['outcome'], 'label' => self::OUTCOMES[$r['outcome']] ?? $r['outcome'], 'count' => (int)$r['c']];
        }
        $byEmployer = $db->query(
            "SELECT e.id AS employer_id, e.company_name,
                    COUNT(DISTINCT a.id) AS placements,
                    COUNT(f.id) AS feedback_count,
                    ROUND(AVG(f.rating), 2) AS avg_rating,
                    SUM(f.status = 'open') AS open_threads
             FROM applications a
             JOIN jobs j ON j.id = a.job_id
             JOIN employers e ON e.id = j.employer_id
             LEFT JOIN feedback_threads f ON f.target_type = 'placement' AND f.target_id = a.id
             WHERE a.application_status = 'accepted'
             GROUP BY e.id, e.company_name
             ORDER BY placements DESC, e.company_name
             LIMIT 50"
        )->fetch_all(MYSQLI_ASSOC);
        sendSuccess('Placement feedback summary', [
            'placements' => $placements,
            'placements_with_feedback' => $withFeedback,
            'feedback_coverage' => $placements ? round($withFeedback / $placements * 100) : 0,
            'threads' => $threads,
            'open_threads' => $open,
            'average_rating' => $avg !== null ? (float)$avg : null,
            'outcomes' => $outcomes,
            'by_employer' => $byEmployer,
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function placementRow(int $applicationId): ?array {
        $stmt = getDB()->prepare("SELECT a.id AS application_id, a.applied_at, j.id AS job_id, j.title AS job_title, j.location, e.id AS employer_id, e.company_name, e.user_id AS employer_user_id,
                                         u.id AS jobseeker_id, CONCAT(u.first_name, ' ', u.last_name) AS jobseeker_name
                                  FROM applications a JOIN jobs j ON j.id = a.job_id JOIN employers e ON e.id = j.employer_id JOIN users u ON u.id = a.user_id
                                  WHERE a.id = ? AND a.application_status = 'accepted'");
        $stmt->bind_param('i', $applicationId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    private function authorizedPlacement(int $applicationId, array $payload): array {
        $placement = $this->placementRow($applicationId);
        if (!$placement) sendError('Placement not found. Feedback is available for accepted applications.', 404);
        if ($payload['role'] === 'employer' && (int)$placement['employer_user_id'] !== (int)$payload['sub']) sendError('Forbidden', 403);
        unset($placement['employer_user_id']);
        return $placement;
    }

    private function authorizedThread(int $threadId, array $payload): array {
        $stmt = getDB()->prepare("SELECT * FROM feedback_threads WHERE id = ? AND target_type = 'placement'");
        $stmt->bind_param('i', $threadId);
        $stmt->execute();
        $thread = $stmt->get_result()->fetch_assoc();
        if (!$thread) sendError('Feedback thread not found', 404);
        $placement = $this->authorizedPlacement((int)$thread['target_id'], $payload);
        return [$thread, $placement];
    }

    private function employerOwnsPlacement(int $applicationId, int $userId): bool {
        $stmt = getDB()->prepare("SELECT 1 FROM applications a JOIN jobs j ON j.id = a.job_id JOIN employers e ON e.id = j.employer_id WHERE a.id = ? AND e.user_id = ?");
        $stmt->bind_param('ii', $applicationId, $userId);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }

    private function employerIdForUser(int $userId): ?int {
        $stmt = getDB()->prepare("SELECT id FROM employers WHERE user_id = ? LIMIT 1");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_row();
        return $row ? (int)$row[0] : null;
    }

    /** PESO messages notify the employer; employer messages notify PESO staff. */
    private function notifyOtherParty(array $placement, array $payload, string $title, string $message): void {
        $db = getDB();
        if ($payload['role'] === 'employer') {
            $res = $db->query("SELECT id FROM users WHERE role = 'peso' AND COALESCE(archived, 0) = 0");
            $recipients = array_map(fn($r) => (int)$r[0], $res ? $res->fetch_all() : []);
        } else {
            $stmt = $db->prepare("SELECT user_id FROM employers WHERE id = ?");
            $stmt->bind_param('i', $placement['employer_id']);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_row();
            $recipients = $row && $row[0] ? [(int)$row[0]] : [];
        }
        $stmt = $db->prepare("INSERT INTO notifications (target_user_id, title, message, priority, category) VALUES (?, ?, ?, 'normal', 'feedback')");
        foreach ($recipients as $userId) {
            if ($userId === (int)$payload['sub']) continue;
            $stmt->bind_param('iss', $userId, $title, $message);
            @$stmt->execute();
        }
    }

    private function rating($value): ?int {
        if (!is_numeric($value)) return null;
        $value = (int)$value;
        return $value >= 1 && $value <= 5 ? $value : null;
    }

    private function text($value, int $max): string {
        if (!is_scalar($value)) return '';
        $value = strip_tags((string)$value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        return mb_substr(trim($value), 0, $max);
    }

    private function rawJson(): array {
        $data = json_decode((string)file_get_contents('php://input'), true);
        return is_array($data) ? $data : [];
    }
}
