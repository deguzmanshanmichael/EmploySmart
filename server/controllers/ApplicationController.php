<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/validator.php';

class ApplicationController {

    public function apply() {
        $payload = requireRole(['jobseeker']);
        $data = getJsonBody();
        $errors = validateRequired($data, ['job_id']);
        if (!empty($errors)) sendError('Validation failed', 422, $errors);

        // Validate job_id is integer
        if (!validateInteger($data['job_id'])) sendError('Invalid job ID', 422);

        $message = $data['message'] ?? $data['cover_letter'] ?? null;
        if (!is_string($message) || trim($message) === '') {
            sendError('Message for the employer is required', 422);
        }
        if (!validateStringLength($message, 3, 2000)) {
            sendError('Message must be between 3 and 2000 characters', 422);
        }
        
        // Validate optional fields
        if (isset($data['resume_used']) && !validateStringLength($data['resume_used'], 0, 500)) {
            sendError('Resume path too long', 422);
        }

        $db = getDB();

        // Check already applied
        $stmt = $db->prepare("SELECT id FROM applications WHERE job_id = ? AND user_id = ?");
        $stmt->bind_param('ii', $data['job_id'], $payload['sub']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) sendError('You already applied for this job', 409);

        // Check job exists and is approved
        $stmt = $db->prepare("SELECT id FROM jobs WHERE id = ? AND approval_status = 'approved' AND archived = FALSE");
        $stmt->bind_param('i', $data['job_id']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) sendError('Job not available', 404);

        $jobId = (int) $data['job_id'];
        $userId = (int) $payload['sub'];
        $resumeUsed = isset($data['resume_used']) ? (string) $data['resume_used'] : null;
        $coverLetter = (string) $message;

        $stmt = $db->prepare("INSERT INTO applications (job_id, user_id, resume_used, cover_letter) VALUES (?,?,?,?)");
        $stmt->bind_param('iiss', $jobId, $userId, $resumeUsed, $coverLetter);
        if (!$stmt->execute()) sendError('Application failed', 500);

        sendSuccess('Application submitted', ['application_id' => $db->insert_id], 201);
    }

    public function getMyApplications() {
        $payload = requireRole(['jobseeker']);
        [$page, $limit, $offset] = getPaginationParams();
        $db = getDB();

        $stmt = $db->prepare("SELECT a.*, j.title, j.location, j.job_type, j.salary_range, j.deadline, e.company_name FROM applications a JOIN jobs j ON j.id = a.job_id JOIN employers e ON e.id = j.employer_id WHERE a.user_id = ? ORDER BY a.applied_at DESC LIMIT ? OFFSET ?");
        $stmt->bind_param('iii', $payload['sub'], $limit, $offset);
        $stmt->execute();
        $apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $count = $db->prepare("SELECT COUNT(*) FROM applications WHERE user_id = ?");
        $count->bind_param('i', $payload['sub']);
        $count->execute();
        $total = $count->get_result()->fetch_row()[0];

        sendPaginated($apps, $total, $page, $limit);
    }

    public function getJobApplicants($jobId) {
        $payload = requireRole(['employer', 'peso', 'admin']);
        [$page, $limit, $offset] = getPaginationParams();
        $db = getDB();

        if ($payload['role'] === 'employer') {
            $stmt = $db->prepare("SELECT j.id FROM jobs j JOIN employers e ON e.id = j.employer_id WHERE j.id = ? AND e.user_id = ?");
            $stmt->bind_param('ii', $jobId, $payload['sub']);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) sendError('Forbidden', 403);
        }

        $stmt = $db->prepare("SELECT a.*, u.first_name, u.last_name, u.email, u.phone, u.education_level, u.resume_path FROM applications a JOIN users u ON u.id = a.user_id WHERE a.job_id = ? ORDER BY a.applied_at DESC LIMIT ? OFFSET ?");
        $stmt->bind_param('iii', $jobId, $limit, $offset);
        $stmt->execute();
        $apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $count = $db->prepare("SELECT COUNT(*) FROM applications WHERE job_id = ?");
        $count->bind_param('i', $jobId);
        $count->execute();
        $total = $count->get_result()->fetch_row()[0];

        sendPaginated($apps, $total, $page, $limit);
    }

    public function updateStatus($id) {
        $payload = requireRole(['employer', 'peso', 'admin']);
        $data = getJsonBody();
        $errors = validateRequired($data, ['status']);
        if (!empty($errors)) sendError('Validation failed', 422, $errors);
        
        $status = $data['status'];
        $allowed = ['pending','reviewed','accepted','rejected','declined'];
        if (!validateEnum($status, $allowed)) sendError('Invalid status', 422);
        if ($status === 'declined') $status = 'rejected';

        $currentStatus = null;
        $statusCheck = $db->prepare("SELECT application_status FROM applications WHERE id = ?");
        $statusCheck->bind_param('i', $applicationId);
        $statusCheck->execute();
        $currentStatusRow = $statusCheck->get_result()->fetch_row();
        if ($currentStatusRow) {
            $currentStatus = $currentStatusRow[0];
        }

        if ($currentStatus === 'accepted' && $status === 'rejected') {
            sendError('Cannot decline an already accepted applicant', 409);
        }

        if ($currentStatus === 'rejected' && $status === 'accepted') {
            sendError('Cannot approve a declined applicant', 409);
        }
        
        // Validate optional fields
        if (isset($data['interview_date']) && !validateDate($data['interview_date'], 'Y-m-d')) {
            sendError('Invalid interview date format', 422);
        }
        
        if (isset($data['remarks']) && !validateStringLength($data['remarks'], 0, 1000)) {
            sendError('Remarks too long (max 1000 characters)', 422);
        }

        $db = getDB();
        
        // Verify application exists and user has access
        if ($payload['role'] === 'employer') {
            $stmt = $db->prepare("SELECT a.id FROM applications a JOIN jobs j ON j.id = a.job_id JOIN employers e ON e.id = j.employer_id WHERE a.id = ? AND e.user_id = ?");
            $stmt->bind_param('ii', $id, $payload['sub']);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) sendError('Forbidden', 403);
        }
        
        $remarks = isset($data['remarks']) ? (string) $data['remarks'] : null;
        $interviewDate = isset($data['interview_date']) ? (string) $data['interview_date'] : null;
        $applicationId = (int) $id;

        $stmt = $db->prepare("UPDATE applications SET application_status = ?, remarks = ?, interview_date = ? WHERE id = ?");
        $stmt->bind_param('sssi', $status, $remarks, $interviewDate, $applicationId);
        if (!$stmt->execute()) sendError('Update failed', 500);
        
        sendSuccess('Application status updated');
    }

    public function withdraw($id) {
        $payload = requireRole(['jobseeker']);
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM applications WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $id, $payload['sub']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) sendError('Not found', 404);
        $stmt = $db->prepare("DELETE FROM applications WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        sendSuccess('Application withdrawn');
    }
}