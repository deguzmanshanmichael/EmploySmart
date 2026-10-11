<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/validator.php';
require_once __DIR__ . '/../services/resume/ResumeProcessor.php';

class UserController {

    public function getAll() {
        requireRole(['admin', 'peso', 'clcdo']);
        [$page, $limit, $offset] = getPaginationParams();
        $search = getQueryParam('search', '');
        $role = getQueryParam('role', '');
        $db = getDB();

        // Validate role enum if provided
        if ($role) {
            $allowedRoles = ['jobseeker', 'employer', 'peso', 'clcdo', 'admin'];
            if (!validateEnum($role, $allowedRoles)) {
                sendError('Invalid role filter', 422);
            }
        }
        
        // Validate and limit search string
        if ($search && !validateStringLength($search, 1, 100)) {
            sendError('Search query too long', 422);
        }

        $where = "WHERE u.archived = FALSE";
        $params = [];
        $types = '';
        if ($search) {
            $where .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
            $s = "%$search%";
            $params[] = $s; $params[] = $s; $params[] = $s;
            $types .= 'sss';
        }
        if ($role) {
            $where .= " AND u.role = ?";
            $params[] = $role;
            $types .= 's';
        }

        $countStmt = $db->prepare("SELECT COUNT(*) FROM users u $where");
        if ($types) $countStmt->bind_param($types, ...$params);
        $countStmt->execute();
        $total = $countStmt->get_result()->fetch_row()[0];

        $params[] = $limit; $params[] = $offset;
        $types .= 'ii';
        $stmt = $db->prepare("SELECT u.id, u.first_name, u.last_name, u.email, u.role, u.phone, u.city, u.is_verified, u.employment_status, u.created_at FROM users u $where ORDER BY u.created_at DESC LIMIT ? OFFSET ?");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        sendPaginated($users, $total, $page, $limit);
    }

    public function getOne($id) {
        $payload = requireAuth();
        $db = getDB();
        $allowed = in_array($payload['role'], ['admin', 'peso'], true) || ($payload['sub'] == $id);

        if (!$allowed && $payload['role'] === 'employer') {
            $stmt = $db->prepare(
                "SELECT 1 FROM applications a JOIN jobs j ON j.id = a.job_id JOIN employers e ON e.id = j.employer_id WHERE a.user_id = ? AND e.user_id = ? LIMIT 1"
            );
            $stmt->bind_param('ii', $id, $payload['sub']);
            $stmt->execute();
            $allowed = $stmt->get_result()->num_rows > 0;
        }

        if (!$allowed) {
            sendError('Forbidden', 403);
        }
        $stmt = $db->prepare("SELECT u.*, e.company_name, e.industry, e.verification_status FROM users u LEFT JOIN employers e ON e.user_id = u.id WHERE u.id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if (!$user) sendError('User not found', 404);
        unset($user['password']);
        $user['profile_picture'] = existingUploadPath($user['profile_picture'] ?? null);
        if (!empty($user['resume_path']) && ResumeProcessor::resolvePath($user['resume_path']) === null) {
            $user['resume_path'] = null;
        }

        // Get skills
        $sStmt = $db->prepare("SELECT s.id, s.skill_name FROM user_skills us JOIN skills s ON s.id = us.skill_id WHERE us.user_id = ?");
        $sStmt->bind_param('i', $id);
        $sStmt->execute();
        $user['skills'] = $sStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $tStmt = $db->prepare("SELECT ut.id, ut.status, ut.completion_date, ut.certificate_path, tp.program_name, tp.location FROM user_training ut JOIN training_programs tp ON tp.id = ut.training_id WHERE ut.user_id = ? ORDER BY ut.completion_date DESC, ut.id DESC");
        $tStmt->bind_param('i', $id);
        $tStmt->execute();
        $user['completed_trainings'] = $tStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $user['profile_summary'] = [
            'skill_count' => count($user['skills']),
            'completed_training_count' => count(array_filter($user['completed_trainings'], fn($item) => $item['status'] === 'completed')),
            'certifications' => array_values(array_filter($user['completed_trainings'], fn($item) => !empty($item['certificate_path']))),
        ];

        sendSuccess('User data', $user);
    }

    /** System-generated resume (PDF) in the EmploySmart design, with the profile photo. */
    public function generateResume($id) {
        $payload = requireAuth();
        $this->authorizeResumeViewer($id, $payload);
        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (!is_file($autoload)) sendError('Resume generation is not installed on the server (missing server/vendor).', 503);
        require_once $autoload;
        require_once __DIR__ . '/../services/ResumePdfService.php';
        @set_time_limit(60);
        try {
            [$pdf, $filename] = (new ResumePdfService(getDB()))->render((int)$id);
        } catch (RuntimeException $e) {
            sendError('User not found', 404);
        } catch (\Throwable $e) {
            error_log('Resume generation failed: ' . $e->getMessage());
            sendError('The resume could not be generated. Please try again.', 422);
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, no-store');
        echo $pdf;
        exit;
    }

    public function downloadUploadedResume($id) {
        $payload = requireAuth();
        $this->authorizeResumeViewer($id, $payload);
        $db = getDB();
        $stmt = $db->prepare("SELECT resume_path FROM users WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $filePath = ResumeProcessor::resolvePath($user['resume_path'] ?? '');
        if (!$filePath) {
            sendError("This jobseeker hasn't uploaded any resume yet.", 404);
        }
        $mime = mime_content_type($filePath) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($filePath);
        exit;
    }

    private function authorizeResumeViewer($id, $payload) {
        if (in_array($payload['role'], ['admin', 'peso'], true) || (int)$payload['sub'] === (int)$id) return;
        if ($payload['role'] === 'employer') {
            $db = getDB();
            $stmt = $db->prepare("SELECT 1 FROM applications a JOIN jobs j ON j.id = a.job_id JOIN employers e ON e.id = j.employer_id WHERE a.user_id = ? AND e.user_id = ? LIMIT 1");
            $stmt->bind_param('ii', $id, $payload['sub']);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) return;
        }
        sendError('Forbidden', 403);
    }

    public function update($id) {
        $payload = requireAuth();
        verifyOwnership($id, $payload, ['admin']);
        // Raw JSON: values are cleaned below and stored as typed (React escapes output),
        // so "Purok 1 & 2" is not saved as "Purok 1 &amp; 2".
        $data = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($data)) sendError('Invalid request body', 400);
        $db = getDB();

        $labels = [
            'first_name' => 'First name', 'middle_name' => 'Middle name', 'last_name' => 'Last name', 'suffix' => 'Suffix',
            'sex' => 'Sex', 'birth_date' => 'Birth date', 'phone' => 'Phone', 'alternate_phone' => 'Alternate phone',
            'address' => 'Address', 'city' => 'City', 'province' => 'Province', 'zip_code' => 'ZIP code',
            'civil_status' => 'Civil status', 'nationality' => 'Nationality', 'education_level' => 'Education level',
            'employment_status' => 'Employment status', 'bio' => 'Bio',
        ];
        $enums = [
            'sex' => ['male', 'female', 'other'],
            'civil_status' => ['single', 'married', 'widowed', 'separated'],
            'education_level' => ['no_formal_education', 'elementary', 'high_school', 'senior_high', 'vocational', 'college', 'postgraduate'],
            'employment_status' => ['unemployed', 'employed', 'self_employed', 'student'],
        ];
        $maxLength = ['suffix' => 20, 'phone' => 30, 'alternate_phone' => 30, 'zip_code' => 20, 'address' => 500, 'bio' => 2000];
        $required = ['first_name', 'last_name'];
        // Columns that cannot be NULL keep their current value when left blank.
        $keepWhenBlank = ['sex', 'employment_status'];

        $sets = []; $params = []; $errors = [];
        foreach ($labels as $field => $label) {
            if (!array_key_exists($field, $data)) continue;
            $value = $data[$field];
            if ($value !== null && !is_scalar($value)) { $errors[$field] = "$label is invalid."; continue; }
            $value = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', strip_tags((string)($value ?? ''))));

            if ($value === '') {
                if (in_array($field, $required, true)) { $errors[$field] = "$label is required."; continue; }
                if (in_array($field, $keepWhenBlank, true)) continue;
                $sets[] = "$field = NULL";
                continue;
            }
            if (mb_strlen($value) > ($maxLength[$field] ?? 100)) { $errors[$field] = "$label is too long (maximum " . ($maxLength[$field] ?? 100) . ' characters).'; continue; }
            if (isset($enums[$field]) && !in_array($value, $enums[$field], true)) { $errors[$field] = "Please choose a valid $label."; continue; }
            if (($field === 'phone' || $field === 'alternate_phone') && (!preg_match('/^\+?[\d\s\-()]+$/', $value) || !validatePhone($value))) { $errors[$field] = "$label must be a valid number (10 to 15 digits)."; continue; }
            if ($field === 'birth_date' && (!validateDate($value, 'Y-m-d') || $value > date('Y-m-d'))) { $errors[$field] = 'Birth date must be a valid past date.'; continue; }
            if ($field === 'zip_code' && !preg_match('/^\d{4,10}$/', $value)) { $errors[$field] = 'ZIP code must be 4 to 10 digits.'; continue; }
            $sets[] = "$field = ?";
            $params[] = $value;
        }
        if ($errors) sendError(reset($errors), 422, $errors);
        if (empty($sets)) sendError('No fields to update', 400);

        $params[] = (int)$id;
        try {
            $stmt = $db->prepare("UPDATE users SET " . implode(', ', $sets) . " WHERE id = ?");
            $stmt->bind_param(str_repeat('s', count($params) - 1) . 'i', ...$params);
            $stmt->execute();
        } catch (\Throwable $e) {
            error_log('Profile update failed: ' . $e->getMessage());
            sendError('Your profile could not be saved. Please check the fields and try again.', 422);
        }
        sendSuccess('Profile updated');
    }

    public function updatePassword($id) {
        $payload = requireAuth();
        if ($payload['sub'] != $id) sendError('Forbidden', 403);
        $data = getJsonBody();
        $errors = validateRequired($data, ['current_password', 'new_password']);
        if (!empty($errors)) sendError('Validation failed', 422, $errors);
        if (!validatePassword($data['new_password'])) sendError('New password too short', 422);

        $db = getDB();
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if (!$user || !password_verify($data['current_password'], $user['password'])) {
            sendError('Current password is incorrect', 401);
        }

        $hashed = password_hash($data['new_password'], PASSWORD_BCRYPT);
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param('si', $hashed, $id);
        $stmt->execute();
        sendSuccess('Password updated');
    }

    public function uploadResume($id) {
        $payload = requireAuth();
        verifyOwnership($id, $payload);
        
        if (!isset($_FILES['resume']) || empty($_FILES['resume']['name'])) sendError('No file uploaded', 400);

        $file = $_FILES['resume'];

        if (!is_array($file) || !isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            sendError('Invalid upload', 400);
        }
        
        // Validate file was uploaded without errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            sendError('File upload error: ' . $file['error'], 400);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf','doc','docx'], true)) {
            sendError('Invalid file type. Allowed: PDF, DOC, DOCX', 422);
        }
        if ($file['size'] === 0 || $file['size'] > 5 * 1024 * 1024) {
            sendError('File size must be between 1B and 5MB', 422);
        }
        
        // Validate MIME type
        $mimeType = null;
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mimeType = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
            }
        }
        if (!$mimeType && function_exists('mime_content_type')) {
            $mimeType = mime_content_type($file['tmp_name']);
        }
        
        $allowedMimes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if ($mimeType && !in_array($mimeType, $allowedMimes, true)) {
            sendError('Invalid file type detected', 422);
        }

        $dir = ResumeProcessor::storageDir();
        $safeDir = realpath($dir) ?: $dir;
        if (!is_dir($safeDir) || !is_writable($safeDir)) {
            sendError('Upload directory is not writable', 500);
        }
        
        // Generate secure filename - prevent path traversal
        $filename = "resume_{$id}_" . bin2hex(random_bytes(8)) . ".$ext";
        $filepath = rtrim($safeDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($filename);
        
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            sendError('Upload failed', 500);
        }

        $path = ResumeProcessor::PRIVATE_PREFIX . $filename;
        $db = getDB();
        $stmt = $db->prepare("UPDATE users SET resume_path = ? WHERE id = ?");
        $stmt->bind_param('si', $path, $id);
        $stmt->execute();
        // Information extracted from a previous resume no longer describes the current file.
        $stmt = $db->prepare("DELETE FROM resume_profiles WHERE user_id = ? AND file_path <> ?");
        $stmt->bind_param('is', $id, $path);
        $stmt->execute();

        sendSuccess('Resume uploaded', ['resume_path' => $path]);
    }

    public function uploadProfilePicture($id) {
        $payload = requireAuth();
        verifyOwnership($id, $payload);
        
        if (!isset($_FILES['photo']) || empty($_FILES['photo']['name'])) sendError('No file uploaded', 400);

        $file = $_FILES['photo'];

        if (!is_array($file) || !isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            sendError('Invalid upload', 400);
        }
        
        // Validate file was uploaded without errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            sendError('File upload error: ' . $file['error'], 400);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) {
            sendError('Invalid file type. Allowed: JPG, PNG, WebP', 422);
        }
        if ($file['size'] === 0 || $file['size'] > 2 * 1024 * 1024) {
            sendError('File size must be between 1B and 2MB', 422);
        }
        
        // Validate MIME type
        $mimeType = null;
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mimeType = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
            }
        }
        if (!$mimeType && function_exists('mime_content_type')) {
            $mimeType = mime_content_type($file['tmp_name']);
        }
        
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if ($mimeType && !in_array($mimeType, $allowedMimes, true)) {
            sendError('Invalid image file detected', 422);
        }

        $dir = uploadsDir('avatars') . '/';
        
        $safeDir = realpath($dir) ?: $dir;
        if (!is_dir($safeDir) || !is_writable($safeDir)) {
            sendError('Upload directory is not writable', 500);
        }
        
        // Generate secure filename - prevent path traversal
        $filename = "avatar_{$id}_" . bin2hex(random_bytes(8)) . ".$ext";
        $filepath = rtrim($safeDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($filename);
        
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            sendError('Upload failed', 500);
        }

        $path = "uploads/avatars/$filename";
        $db = getDB();
        $stmt = $db->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
        $stmt->bind_param('si', $path, $id);
        $stmt->execute();
        sendSuccess('Profile picture updated', ['profile_picture' => $path]);
    }

    public function verifyUser($id) {
        $payload = requireAuth();
        $db = getDB();
        $roleStmt = $db->prepare("SELECT role FROM users WHERE id = ?");
        $roleStmt->bind_param('i', $id);
        $roleStmt->execute();
        $target = $roleStmt->get_result()->fetch_assoc();
        if (!$target) sendError('User not found', 404);
        if ($target['role'] === 'jobseeker' && $payload['role'] !== 'admin') {
            sendError('Only an administrator can verify jobseeker accounts.', 403);
        }
        if (!in_array($payload['role'], ['admin', 'peso'], true)) {
            sendError('Forbidden', 403);
        }
        $stmt = $db->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        sendSuccess('User verified');
    }

    public function deleteUser($id) {
        requireRole(['admin']);
        $db = getDB();

        $pendingJobs = 0;
        $stmt = $db->prepare("SELECT COUNT(*) FROM jobs j JOIN employers e ON e.id = j.employer_id WHERE e.user_id = ? AND j.approval_status = 'pending' AND j.archived = FALSE");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $pendingJobs = (int)$stmt->get_result()->fetch_row()[0];

        $stmt = $db->prepare("SELECT COUNT(*) FROM applications WHERE user_id = ? AND application_status = 'pending'");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $pendingApplications = (int)$stmt->get_result()->fetch_row()[0];

        if ($pendingJobs > 0 || $pendingApplications > 0) {
            $reasons = [];
            if ($pendingJobs > 0) $reasons[] = "$pendingJobs pending job posting" . ($pendingJobs === 1 ? '' : 's');
            if ($pendingApplications > 0) $reasons[] = "$pendingApplications pending job application" . ($pendingApplications === 1 ? '' : 's');
            sendError('Account cannot be archived while it has ' . implode(' and ', $reasons) . '.', 409);
        }

        $stmt = $db->prepare("UPDATE users SET archived = TRUE WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        sendSuccess('User archived');
    }

    public function getDashboardStats() {
        requireRole(['admin', 'peso', 'clcdo']);
        $db = getDB();
        $stats = [];
        
  
        $stmt = $db->prepare("SELECT COUNT(*) FROM users");
        $stmt->execute();
        $stats['total_users'] = $stmt->get_result()->fetch_row()[0];
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role=?");
        $stmt->bind_param("s", $role);
        $role = 'jobseeker';
        $stmt->execute();
        $stats['total_jobseekers'] = $stmt->get_result()->fetch_row()[0];
        
        $role = 'employer';
        $stmt->execute();
        $stats['total_employers'] = $stmt->get_result()->fetch_row()[0];
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE is_verified=?");
        $stmt->bind_param("i", $verified);
        $verified = 1;
        $stmt->execute();
        $stats['verified_users'] = $stmt->get_result()->fetch_row()[0];
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM jobs WHERE approval_status=?");
        $stmt->bind_param("s", $status);
        $status = 'approved';
        $stmt->execute();
        $stats['total_jobs'] = $stmt->get_result()->fetch_row()[0];
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM applications");
        $stmt->execute();
        $stats['total_applications'] = $stmt->get_result()->fetch_row()[0];
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM employers WHERE verification_status=?");
        $stmt->bind_param("s", $ver_status);
        $ver_status = 'pending';
        $stmt->execute();
        $stats['pending_employers'] = $stmt->get_result()->fetch_row()[0];
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM training_programs WHERE status=?");
        $stmt->bind_param("s", $train_status);
        $train_status = 'ongoing';
        $stmt->execute();
        $stats['active_trainings'] = $stmt->get_result()->fetch_row()[0];

        $stats['pending_jobs'] = 0;
        $stmt = $db->query("SELECT COUNT(*) AS total FROM jobs WHERE approval_status = 'pending' AND archived = FALSE");
        if ($stmt) $stats['pending_jobs'] = (int)$stmt->fetch_assoc()['total'];
        $stats['pending_applications'] = 0;
        $stmt = $db->query("SELECT COUNT(*) AS total FROM applications WHERE application_status = 'pending'");
        if ($stmt) $stats['pending_applications'] = (int)$stmt->fetch_assoc()['total'];
        $stats['role_counts'] = [];
        $stmt = $db->query("SELECT role, COUNT(*) AS total FROM users WHERE archived = FALSE GROUP BY role");
        if ($stmt) {
            while ($row = $stmt->fetch_assoc()) $stats['role_counts'][$row['role']] = (int)$row['total'];
        }
        
        sendSuccess('Dashboard stats', $stats);
    }
}