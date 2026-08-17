<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/validator.php';

class EmployerController {

    public function getAll() {
        requireRole(['admin', 'peso']);
        [$page, $limit, $offset] = getPaginationParams();
        $status = getQueryParam('status', '');
        $db = getDB();

        $where = "WHERE 1=1";
        $params = []; $types = '';
        if ($status) { $where .= " AND e.verification_status = ?"; $params[] = $status; $types .= 's'; }

        $params[] = $limit; $params[] = $offset; $types .= 'ii';
        $stmt = $db->prepare("SELECT e.*, u.first_name, u.last_name, u.email FROM employers e JOIN users u ON u.id = e.user_id $where ORDER BY e.created_at DESC LIMIT ? OFFSET ?");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $employers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $count = $db->prepare("SELECT COUNT(*) FROM employers e $where");
        array_pop($params); array_pop($params);
        $countTypes = rtrim($types, 'ii');
        if ($countTypes) $count->bind_param($countTypes, ...$params);
        $count->execute();
        $total = $count->get_result()->fetch_row()[0];

        sendPaginated($employers, $total, $page, $limit);
    }

    public function getOne($id) {
        requireAuth();
        $db = getDB();
        $stmt = $db->prepare("SELECT e.*, u.first_name, u.last_name, u.email FROM employers e JOIN users u ON u.id = e.user_id WHERE e.id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $emp = $stmt->get_result()->fetch_assoc();
        if (!$emp) sendError('Employer not found', 404);
        sendSuccess('Employer data', $emp);
    }

    public function getByUser($userId) {
        $payload = requireAuth();
        if ($payload['sub'] != $userId && !in_array($payload['role'], ['admin','peso'])) sendError('Forbidden', 403);
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM employers WHERE user_id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $emp = $stmt->get_result()->fetch_assoc();
        if (!$emp) sendError('Employer profile not found', 404);
        sendSuccess('Employer profile', $emp);
    }

    public function update($id) {
        $payload = requireAuth();
        $db = getDB();

        // Authorization check
        if ($payload['role'] !== 'admin' && $payload['role'] !== 'peso') {
            verifyResourceOwnership('employers', $id, $payload['sub'], 'user_id');
        }

        $data = getJsonBody();
        $allowed = ['company_name','industry','company_size','website','company_address','contact_person','contact_email','contact_phone'];
        $sets = []; $params = []; $types = '';
        
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];
                
                // Field-specific validation
                if ($field === 'contact_email' && $value) {
                    if (!validateEmail($value)) sendError('Invalid email format', 422);
                }
                
                if ($field === 'contact_phone' && $value) {
                    if (!validatePhone($value)) sendError('Invalid phone format', 422);
                }
                
                if ($field === 'website' && $value) {
                    if (!validateUrl($value)) sendError('Invalid website URL', 422);
                }
                
                if ($field === 'company_size' && $value) {
                    $allowedSizes = ['1-50', '51-200', '201-500', '501-1000', '1000+'];
                    if (!validateEnum($value, $allowedSizes)) sendError('Invalid company size', 422);
                }
                
                if ($field === 'industry' && $value) {
                    if (!validateStringLength($value, 2, 100)) sendError('Industry name too long', 422);
                }
                
                // Validate string lengths for other fields
                if (!validateStringLength($value, 1, 500)) {
                    sendError(ucfirst(str_replace('_', ' ', $field)) . ' too long', 422);
                }
                
                $sets[] = "$field = ?"; 
                $params[] = $value; 
                $types .= 's';
            }
        }
        if (empty($sets)) sendError('No fields to update', 400);
        
        $params[] = $id; $types .= 'i';
        $stmt = $db->prepare("UPDATE employers SET " . implode(',', $sets) . " WHERE id = ?");
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) sendError('Update failed', 500);
        
        sendSuccess('Employer profile updated');
    }

    public function verify($id) {
        $payload = requireRole(['admin', 'peso']);
        $data = getJsonBody();
        $errors = validateRequired($data, ['status']);
        if (!empty($errors)) sendError('Validation failed', 422, $errors);
        
        $status = $data['status'];
        if (!validateEnum($status, ['approved','rejected','pending'])) {
            sendError('Invalid status value', 422);
        }
        
        $db = getDB();
        
        // Verify employer exists
        $stmt = $db->prepare("SELECT id, user_id FROM employers WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $employer = $stmt->get_result()->fetch_assoc();
        if (!$employer) sendError('Employer not found', 404);
        
        // Update employer status
        $stmt = $db->prepare("UPDATE employers SET verification_status = ? WHERE id = ?");
        $stmt->bind_param('si', $status, $id);
        if (!$stmt->execute()) sendError('Update failed', 500);
        
        // Also verify the user account if approved
        if ($status === 'approved') {
            $stmt = $db->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
            $stmt->bind_param('i', $employer['user_id']);
            $stmt->execute();
        }
        
        sendSuccess("Employer $status");
    }

    public function uploadDocument($id) {
        $payload = requireAuth();
        
        // Authorization: only admin/peso or the owner can upload
        if ($payload['role'] !== 'admin' && $payload['role'] !== 'peso') {
            verifyResourceOwnership('employers', $id, $payload['sub'], 'user_id');
        }
        
        if (!isset($_FILES['document'])) sendError('No file uploaded', 400);

        $file = $_FILES['document'];
        
        // Validate file was uploaded without errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            sendError('File upload error: ' . $file['error'], 400);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf','jpg','jpeg','png'], true)) {
            sendError('Invalid file type. Allowed: PDF, JPG, PNG', 422);
        }
        
        if ($file['size'] === 0 || $file['size'] > 10 * 1024 * 1024) {
            sendError('File size must be between 1B and 10MB', 422);
        }
        
        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png'];
        if (!in_array($mimeType, $allowedMimes, true)) {
            sendError('Invalid file type detected', 422);
        }

        $dir = __DIR__ . '/../uploads/verification_docs/employers/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        
        // Generate secure filename
        $filename = "permit_{$id}_" . bin2hex(random_bytes(8)) . ".$ext";
        $filepath = $dir . $filename;
        
        // Verify path is within uploads directory
        $realPath = realpath($filepath) ?? $filepath;
        if (strpos($realPath, realpath($dir)) !== 0) {
            sendError('Invalid file path', 400);
        }
        
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            sendError('Upload failed', 500);
        }

        $path = "uploads/verification_docs/employers/$filename";
        $db = getDB();
        $stmt = $db->prepare("UPDATE employers SET business_permit = ? WHERE id = ?");
        $stmt->bind_param('si', $path, $id);
        $stmt->execute();
        sendSuccess('Document uploaded', ['path' => $path]);
    }

    public function getAnalytics($employerId) {
        $payload = requireAuth();
        $db = getDB();
        $data = [];

        $stmt = $db->prepare("SELECT COUNT(*) FROM jobs WHERE employer_id = ?");
        $stmt->bind_param('i', $employerId);
        $stmt->execute();
        $data['total_jobs'] = $stmt->get_result()->fetch_row()[0];

        $stmt = $db->prepare("SELECT COUNT(*) FROM jobs WHERE employer_id = ? AND approval_status='approved'");
        $stmt->bind_param('i', $employerId);
        $stmt->execute();
        $data['approved_jobs'] = $stmt->get_result()->fetch_row()[0];

        $stmt = $db->prepare("SELECT COUNT(*) FROM applications a JOIN jobs j ON j.id = a.job_id WHERE j.employer_id = ?");
        $stmt->bind_param('i', $employerId);
        $stmt->execute();
        $data['total_applications'] = $stmt->get_result()->fetch_row()[0];

        $stmt = $db->prepare("SELECT COUNT(*) FROM applications a JOIN jobs j ON j.id = a.job_id WHERE j.employer_id = ? AND a.application_status='pending'");
        $stmt->bind_param('i', $employerId);
        $stmt->execute();
        $data['pending_applications'] = $stmt->get_result()->fetch_row()[0];

        $stmt = $db->prepare("SELECT COUNT(*) FROM applications a JOIN jobs j ON j.id = a.job_id WHERE j.employer_id = ? AND a.application_status='accepted'");
        $stmt->bind_param('i', $employerId);
        $stmt->execute();
        $data['accepted_applications'] = $stmt->get_result()->fetch_row()[0];

        sendSuccess('Analytics', $data);
    }
}