<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/validator.php';

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
        $allowed = ($payload['role'] === 'admin') || ($payload['sub'] == $id);

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

    private function parseResumeText($filePath) {
        if (!is_file($filePath)) return null;

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($ext === 'txt') {
            $text = file_get_contents($filePath);
            return $text ? trim($text) : null;
        }

        if ($ext === 'docx') {
            if (class_exists('ZipArchive')) {
                $zip = new ZipArchive();
                if ($zip->open($filePath) === true) {
                    $xml = $zip->getFromName('word/document.xml');
                    $zip->close();
                    if ($xml) {
                        $text = preg_replace('/<[^>]+>/', ' ', $xml);
                        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                        $text = preg_replace('/\s+/', ' ', $text);
                        return trim($text);
                    }
                }
            }
        }

        if ($ext === 'pdf') {
            if (function_exists('shell_exec')) {
                $command = 'pdftotext ' . escapeshellarg($filePath) . ' - 2>/dev/null';
                $output = shell_exec($command);
                if (!empty($output)) {
                    return trim($output);
                }
            }

            $content = file_get_contents($filePath);
            if ($content) {
                $matches = [];
                preg_match_all('/\((?:[^()]|\\.)*\)/', $content, $matches);
                $text = implode(' ', $matches[0] ?? []);
                $text = str_replace(['\\(', '\\)'], ['(', ')'], $text);
                $text = preg_replace('/\s+/', ' ', $text);
                return trim($text);
            }
        }

        return null;
    }

    private function wrapText($text, $limit = 96) {
        $text = preg_replace('/\s+/', ' ', trim($text));
        if (empty($text)) return [];

        $words = preg_split('/\s+/', $text);
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if (mb_strlen($candidate) <= $limit) {
                $current = $candidate;
            } else {
                if ($current !== '') $lines[] = $current;
                $current = $word;
            }
        }

        if ($current !== '') $lines[] = $current;
        return $lines;
    }

    private function escapePdfText($text) {
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace('(', '\\(', $text);
        $text = str_replace(')', '\\)', $text);
        $text = preg_replace('/[^\x20-\x7E]/', '', $text);
        return $text;
    }

    private function buildResumePdf($lines) {
        $pageWidth = 612;
        $pageHeight = 792;
        $marginLeft = 50;
        $marginTop = 760;
        $lineHeight = 13;
        $fontSize = 11;

        $stream = '';
        $stream .= "q\n";
        $stream .= "0.15 0.31 0.58 rg\n";
        $stream .= "50 720 512 60 re f\n";
        $stream .= "0.98 0.99 1 rg\n";
        $stream .= "50 720 512 60 re S\n";
        $stream .= "Q\n";
        $stream .= "BT /F1 16 Tf 70 750 Td (EmploySmart Professional Resume) Tj ET\n";
        $stream .= "BT /F1 10 Tf 70 730 Td (Generated from your profile, training, and approved roles) Tj ET\n";
        $y = $marginTop - 30;
        foreach ($lines as $line) {
            if ($y < 40) {
                break;
            }
            $stream .= "BT /F1 $fontSize Tf $marginLeft $y Td (" . $this->escapePdfText($line) . ") Tj ET\n";
            $y -= $lineHeight;
        }

        $objects = [];
        $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj";
        $objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj";
        $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $pageWidth $pageHeight] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj";
        $objects[] = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj";
        $objects[] = "5 0 obj\n<< /Length 6 0 R >>\nstream\n$stream\nendstream\nendobj";
        $objects[] = "6 0 obj\n" . strlen($stream) . "\nendobj";

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        $pos = strlen($pdf);
        foreach ($objects as $obj) {
            $offsets[] = $pos;
            $pdf .= $obj . "\n";
            $pos = strlen($pdf);
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n$xrefOffset\n%%EOF";

        return $pdf;
    }

    public function generateResume($id) {
        $payload = requireAuth();
        verifyOwnership($id, $payload, ['admin', 'peso']);
        $db = getDB();

        $stmt = $db->prepare("SELECT u.*, e.company_name FROM users u LEFT JOIN employers e ON e.user_id = u.id WHERE u.id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if (!$user) sendError('User not found', 404);

        $sStmt = $db->prepare("SELECT s.skill_name FROM user_skills us JOIN skills s ON s.id = us.skill_id WHERE us.user_id = ? ORDER BY s.skill_name");
        $sStmt->bind_param('i', $id);
        $sStmt->execute();
        $skills = $sStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $tStmt = $db->prepare("SELECT ut.status, ut.completion_date, tp.program_name, tp.location FROM user_training ut JOIN training_programs tp ON tp.id = ut.training_id WHERE ut.user_id = ? AND ut.status = 'completed' ORDER BY ut.completion_date DESC, ut.id DESC");
        $tStmt->bind_param('i', $id);
        $tStmt->execute();
        $trainings = $tStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $aStmt = $db->prepare("SELECT a.application_status, a.applied_at, j.title, j.location, e.company_name FROM applications a JOIN jobs j ON j.id = a.job_id JOIN employers e ON e.id = j.employer_id WHERE a.user_id = ? AND a.application_status = 'accepted' ORDER BY a.applied_at DESC");
        $aStmt->bind_param('i', $id);
        $aStmt->execute();
        $approvedJobs = $aStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $resumePath = null;
        if (!empty($user['resume_path'])) {
            $resumePath = __DIR__ . '/../' . ltrim($user['resume_path'], '/');
            if (!file_exists($resumePath)) $resumePath = null;
        }

        $extractedText = $resumePath ? $this->parseResumeText($resumePath) : null;
        $lines = [];
        $lines[] = '';
        $lines[] = '';
        $lines[] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        $lines[] = $user['email'] ?? '';
        $lines[] = $user['phone'] ?? '';
        $lines[] = trim(($user['address'] ?? '') . ' ' . ($user['city'] ?? ''));
        $lines[] = '';
        $lines[] = 'PROFILE';
        $lines[] = '--------------------------------';
        $lines[] = $user['bio'] ?: 'Motivated professional ready to contribute to a growing team.';
        $lines[] = '';
        $lines[] = 'EDUCATION & STATUS';
        $lines[] = '--------------------------------';
        $lines[] = 'Education: ' . ($user['education_level'] ? str_replace('_', ' ', $user['education_level']) : 'Not provided');
        $lines[] = 'Employment: ' . ($user['employment_status'] ? str_replace('_', ' ', $user['employment_status']) : 'Not provided');
        $lines[] = '';
        $lines[] = 'SKILLS';
        $lines[] = '--------------------------------';
        if (!empty($skills)) {
            $lines[] = implode(', ', array_map(fn($item) => $item['skill_name'], $skills));
        } else {
            $lines[] = 'No skills recorded yet.';
        }
        $lines[] = '';
        $lines[] = 'COMPLETED TRAININGS';
        $lines[] = '--------------------------------';
        if (!empty($trainings)) {
            foreach ($trainings as $training) {
                $lines[] = '- ' . ($training['program_name'] ?? 'Training') . ' (' . ($training['completion_date'] ?? 'Completed') . ')';
                $lines[] = '  Location: ' . ($training['location'] ?? 'N/A');
            }
        } else {
            $lines[] = 'No completed trainings recorded yet.';
        }
        $lines[] = '';
        $lines[] = 'APPROVED JOBS';
        $lines[] = '--------------------------------';
        if (!empty($approvedJobs)) {
            foreach ($approvedJobs as $job) {
                $lines[] = '- ' . ($job['title'] ?? 'Position') . ' at ' . ($job['company_name'] ?? 'Employer');
                $lines[] = '  Location: ' . ($job['location'] ?? 'N/A');
            }
        } else {
            $lines[] = 'No approved jobs recorded yet.';
        }

        $dir = __DIR__ . '/../uploads/resumes/generated/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $filename = 'resume_' . $id . '_generated.pdf';
        $filepath = $dir . $filename;
        file_put_contents($filepath, $this->buildResumePdf($lines));

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        readfile($filepath);
        exit;
    }

    public function update($id) {
        $payload = requireAuth();
        verifyOwnership($id, $payload, ['admin']);
        $data = getJsonBody();
        $db = getDB();

        $allowed = ['first_name','middle_name','last_name','suffix','sex','birth_date','phone','alternate_phone','address','city','province','zip_code','civil_status','nationality','education_level','employment_status','bio'];
        $sets = []; $params = []; $types = '';
        
        // Validate each field if provided
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];
                
                // Type-specific validation
                if ($field === 'email' && $value) {
                    if (!validateEmail($value)) sendError('Invalid email format', 422);
                    // Check email uniqueness
                    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $stmt->bind_param('si', $value, $id);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) sendError('Email already in use', 409);
                }
                
                if ($field === 'phone' && $value) {
                    if (!validatePhone($value)) sendError('Invalid phone number format', 422);
                }
                
                if ($field === 'birth_date' && $value) {
                    if (!validateDate($value, 'Y-m-d')) sendError('Invalid birth date format', 422);
                }
                
                // Validate string lengths
                if (!validateStringLength($value, 1, 255)) {
                    sendError(ucfirst(str_replace('_', ' ', $field)) . ' too long', 422);
                }
                
                $sets[] = "$field = ?";
                $params[] = $value;
                $types .= 's';
            }
        }
        if (empty($sets)) sendError('No fields to update', 400);

        $params[] = $id; $types .= 'i';
        $stmt = $db->prepare("UPDATE users SET " . implode(',', $sets) . " WHERE id = ?");
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) sendError('Update failed', 500);

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

        $dir = __DIR__ . '/../uploads/resumes/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        
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

        $path = "uploads/resumes/$filename";
        $db = getDB();
        $stmt = $db->prepare("UPDATE users SET resume_path = ? WHERE id = ?");
        $stmt->bind_param('si', $path, $id);
        $stmt->execute();

        $parsedDir = __DIR__ . '/../uploads/resumes/parsed/';
        if (!is_dir($parsedDir)) mkdir($parsedDir, 0755, true);
        $parsedText = $this->parseResumeText($filepath);
        if ($parsedText) {
            file_put_contents($parsedDir . 'resume_' . $id . '.txt', $parsedText);
        }

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

        $dir = __DIR__ . '/../uploads/avatars/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        
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
        requireRole(['admin', 'peso']);
        $db = getDB();
        $stmt = $db->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        sendSuccess('User verified');
    }

    public function deleteUser($id) {
        requireRole(['admin']);
        $db = getDB();
        $stmt = $db->prepare("UPDATE users SET archived = TRUE WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        sendSuccess('User archived');
    }

    public function getDashboardStats() {
        requireRole(['admin', 'peso']);
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
        
        sendSuccess('Dashboard stats', $stats);
    }
}