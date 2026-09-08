<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/validator.php';

class JobController {

    private function normalizeJobType($value) {
        $normalized = strtolower(trim((string) $value));
        $map = [
            'full-time' => 'fulltime',
            'fulltime' => 'fulltime',
            'part-time' => 'parttime',
            'parttime' => 'parttime',
            'contract' => 'contract',
            'temporary' => 'temporary',
            'freelance' => 'freelance',
            'internship' => 'internship',
        ];

        return $map[$normalized] ?? null;
    }

    private function normalizeExperienceLevel($value) {
        $normalized = strtolower(trim((string) $value));
        $map = [
            'entry-level' => 'entry',
            'entry' => 'entry',
            'mid-level' => 'mid',
            'mid' => 'mid',
            'senior-level' => 'senior',
            'senior' => 'senior',
            'executive' => 'senior',
        ];

        return $map[$normalized] ?? null;
    }

    public function getAll() {
        [$page, $limit, $offset] = getPaginationParams();
        $search    = getQueryParam('search', '');
        $location  = getQueryParam('location', '');
        $type      = getQueryParam('job_type', '');
        $level     = getQueryParam('experience_level', '');
        $status    = getQueryParam('approval_status', 'approved');

        $db = getDB();
        $where = "WHERE j.approval_status = ? AND j.archived = FALSE";
        $params = [$status]; $types = 's';

        if ($search) { $where .= " AND (j.title LIKE ? OR j.description LIKE ? OR e.company_name LIKE ? OR e.industry LIKE ?)"; $s="%$search%"; $params[]=$s; $params[]=$s; $params[]=$s; $params[]=$s; $types.='ssss'; }
        if ($location) { $where .= " AND j.location LIKE ?"; $params[]="%$location%"; $types.='s'; }
        if ($type) { $where .= " AND j.job_type = ?"; $params[]=$type; $types.='s'; }
        if ($level) { $where .= " AND j.experience_level = ?"; $params[]=$level; $types.='s'; }

        $countStmt = $db->prepare("SELECT COUNT(*) FROM jobs j LEFT JOIN employers e ON e.id = j.employer_id $where");
        $countStmt->bind_param($types, ...$params);
        $countStmt->execute();
        $total = $countStmt->get_result()->fetch_row()[0];

        $params[] = $limit; $params[] = $offset; $types .= 'ii';
        $stmt = $db->prepare("SELECT j.*, COALESCE(e.company_name, 'Employer') AS company_name, e.industry FROM jobs j LEFT JOIN employers e ON e.id = j.employer_id $where ORDER BY j.created_at DESC LIMIT ? OFFSET ?");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($jobs as &$job) {
            $sStmt = $db->prepare("SELECT s.id, s.skill_name FROM job_skills js JOIN skills s ON s.id = js.skill_id WHERE js.job_id = ?");
            $sStmt->bind_param('i', $job['id']);
            $sStmt->execute();
            $job['skills'] = $sStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }

        sendPaginated($jobs, $total, $page, $limit);
    }

    public function getOne($id) {
        $db = getDB();
        $stmt = $db->prepare("SELECT j.*, COALESCE(e.company_name, 'Employer') AS company_name, e.industry, e.website, e.company_address, e.contact_email, e.contact_phone FROM jobs j LEFT JOIN employers e ON e.id = j.employer_id WHERE j.id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $job = $stmt->get_result()->fetch_assoc();
        if (!$job) sendError('Job not found', 404);

        $sStmt = $db->prepare("SELECT s.id, s.skill_name FROM job_skills js JOIN skills s ON s.id = js.skill_id WHERE js.job_id = ?");
        $sStmt->bind_param('i', $id);
        $sStmt->execute();
        $job['skills'] = $sStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        sendSuccess('Job data', $job);
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

    public function create() {
        $payload = requireRole(['employer']);
        $data = getJsonBody();
        $errors = validateRequired($data, ['title', 'description', 'location', 'job_type']);
        if (!empty($errors)) sendError('Validation failed', 422, $errors);

        // Validate field formats
        if (!validateStringLength($data['title'], 3, 200)) sendError('Job title must be 3-200 characters', 422);
        if (!validateStringLength($data['description'], 10, 5000)) sendError('Description must be 10-5000 characters', 422);
        if (!validateStringLength($data['location'], 2, 100)) sendError('Location must be 2-100 characters', 422);

        $jobType = $this->normalizeJobType($data['job_type'] ?? '');
        if (!$jobType) sendError('Invalid job type', 422);

        $experienceLevel = null;
        if (isset($data['experience_level'])) {
            $experienceLevel = $this->normalizeExperienceLevel($data['experience_level']);
            if (!$experienceLevel) sendError('Invalid experience level', 422);
        }
        
        if (isset($data['vacancies']) && !validateNumericRange($data['vacancies'], 1, 1000)) {
            sendError('Vacancies must be between 1 and 1000', 422);
        }
        
        if (isset($data['deadline']) && !validateDate($data['deadline'], 'Y-m-d')) {
            sendError('Invalid deadline date format', 422);
        }
        
        // Validate skills array
        if (isset($data['skills']) && !validateArrayLength($data['skills'], 20)) {
            sendError('Too many skills (max 20)', 422);
        }

        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM employers WHERE user_id = ? AND verification_status = 'approved'");
        $stmt->bind_param('i', $payload['sub']);
        $stmt->execute();
        $employer = $stmt->get_result()->fetch_assoc();
        if (!$employer) sendError('Employer not verified. Cannot post jobs.', 403);

        $empId = $employer['id'];
        $vacancies = (int) ($data['vacancies'] ?? 1);
        $title = (string) ($data['title'] ?? '');
        $description = (string) ($data['description'] ?? '');
        $requirements = isset($data['requirements']) ? (string) $data['requirements'] : null;
        $location = (string) ($data['location'] ?? '');
        $salaryRange = isset($data['salary_range']) ? (string) $data['salary_range'] : null;
        $deadline = isset($data['deadline']) ? (string) $data['deadline'] : null;
        $educationRequired = isset($data['education_required']) ? (string) $data['education_required'] : null;
        $approvalStatus = 'pending';

        $stmt = $db->prepare("INSERT INTO jobs (employer_id,title,description,requirements,location,salary_range,vacancies,deadline,job_type,experience_level,education_required,approval_status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('isssssisssss',
            $empId, $title, $description, $requirements,
            $location, $salaryRange, $vacancies,
            $deadline, $jobType, $experienceLevel,
            $educationRequired, $approvalStatus
        );
        if (!$stmt->execute()) sendError('Failed to create job', 500);
        $jobId = $db->insert_id;

        // Validate and add skills
        if (!empty($data['skills']) && is_array($data['skills'])) {
            foreach ($data['skills'] as $skillId) {
                // Validate skill exists
                if (!validateInteger($skillId)) continue;
                $sStmt = $db->prepare("INSERT INTO job_skills (job_id, skill_id) VALUES (?,?)");
                $sStmt->bind_param('ii', $jobId, $skillId);
                $sStmt->execute();
            }
        }

        sendSuccess('Job posted. Awaiting PESO approval.', ['job_id' => $jobId], 201);
    }

    public function update($id) {
        $payload = requireRole(['employer', 'peso', 'admin']);
        $data = getJsonBody();
        $db = getDB();

        if ($payload['role'] === 'employer') {
            $stmt = $db->prepare("SELECT j.id FROM jobs j JOIN employers e ON e.id = j.employer_id WHERE j.id = ? AND e.user_id = ?");
            $stmt->bind_param('ii', $id, $payload['sub']);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) sendError('Forbidden', 403);
        }

        $allowed = ['title','description','requirements','location','salary_range','vacancies','deadline','job_type','experience_level','education_required'];
        $sets = []; $params = []; $types = '';
        $normalizedData = $data;

        if (array_key_exists('job_type', $normalizedData)) {
            $normalizedJobType = $this->normalizeJobType($normalizedData['job_type']);
            if (!$normalizedJobType) sendError('Invalid job type', 422);
            $normalizedData['job_type'] = $normalizedJobType;
        }

        if (array_key_exists('experience_level', $normalizedData)) {
            $normalizedExperience = $this->normalizeExperienceLevel($normalizedData['experience_level']);
            if (!$normalizedExperience) sendError('Invalid experience level', 422);
            $normalizedData['experience_level'] = $normalizedExperience;
        }

        foreach ($allowed as $field) {
            if (array_key_exists($field, $normalizedData)) {
                $sets[] = "$field = ?"; $params[] = $normalizedData[$field]; $types .= 's';
            }
        }
        if (!empty($sets)) {
            $params[] = $id; $types .= 'i';
            $stmt = $db->prepare("UPDATE jobs SET " . implode(',', $sets) . " WHERE id = ?");
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
        }

        if (isset($data['skills'])) {
            $db->prepare("DELETE FROM job_skills WHERE job_id = ?")->execute();
            foreach ($data['skills'] as $skillId) {
                $sStmt = $db->prepare("INSERT INTO job_skills (job_id, skill_id) VALUES (?,?)");
                $sStmt->bind_param('ii', $id, $skillId);
                $sStmt->execute();
            }
        }

        sendSuccess('Job updated');
    }

    public function delete($id) {
        $payload = requireRole(['employer', 'peso', 'admin']);
        $db = getDB();
        if ($payload['role'] === 'employer') {
            $stmt = $db->prepare("SELECT j.id FROM jobs j JOIN employers e ON e.id = j.employer_id WHERE j.id = ? AND e.user_id = ?");
            $stmt->bind_param('ii', $id, $payload['sub']);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) sendError('Forbidden', 403);
        }
        $stmt = $db->prepare("UPDATE jobs SET archived = TRUE WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        sendSuccess('Job archived');
    }

    public function approve($id) {
        $payload = requireRole(['peso', 'admin']);
        $data = getJsonBody();
        $status = $data['status'] ?? 'approved';
        if (!in_array($status, ['approved','rejected'])) sendError('Invalid status', 422);
        $db = getDB();
        $stmt = $db->prepare("UPDATE jobs SET approval_status = ?, approved_by = ? WHERE id = ?");
        $stmt->bind_param('sii', $status, $payload['sub'], $id);
        $stmt->execute();
        sendSuccess("Job $status");
    }

    public function getByEmployer($employerId) {
        $payload = requireAuth();
        [$page, $limit, $offset] = getPaginationParams();
        $db = getDB();

        $stmt = $db->prepare("SELECT j.*, COALESCE(e.company_name, 'Employer') AS company_name FROM jobs j LEFT JOIN employers e ON e.id = j.employer_id WHERE j.employer_id = ? ORDER BY j.created_at DESC LIMIT ? OFFSET ?");
        $stmt->bind_param('iii', $employerId, $limit, $offset);
        $stmt->execute();
        $jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $count = $db->prepare("SELECT COUNT(*) FROM jobs WHERE employer_id = ?");
        $count->bind_param('i', $employerId);
        $count->execute();
        $total = $count->get_result()->fetch_row()[0];

        sendPaginated($jobs, $total, $page, $limit);
    }

    public function getPendingJobs() {
        requireRole(['peso', 'admin']);
        [$page, $limit, $offset] = getPaginationParams();
        $db = getDB();
        $stmt = $db->prepare("SELECT j.*, COALESCE(e.company_name, 'Employer') AS company_name FROM jobs j LEFT JOIN employers e ON e.id = j.employer_id WHERE (j.approval_status = 'pending' OR j.approval_status IS NULL OR j.approval_status = '') ORDER BY j.created_at ASC LIMIT ? OFFSET ?");
        $stmt->bind_param('ii', $limit, $offset);
        $stmt->execute();
        $jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM jobs WHERE approval_status = ? OR approval_status IS NULL OR approval_status = ''");
        $status = 'pending';
        $stmt->bind_param('s', $status);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_row()[0];
        
        sendPaginated($jobs, $total, $page, $limit);
    }
}