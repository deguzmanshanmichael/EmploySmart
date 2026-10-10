<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/validator.php';
require_once __DIR__ . '/../helpers/rate_limiter.php';

/**
 * Jobseeker resume upload, extraction review and profile synchronization.
 * All routes act on the authenticated jobseeker's own resume only.
 */
class ResumeProfileController {

    private const PROFILE_FIELDS = ['first_name', 'middle_name', 'last_name', 'suffix', 'phone', 'address', 'city', 'province', 'birth_date', 'sex', 'civil_status', 'education_level', 'employment_status', 'bio'];
    private const NAME_FIELDS = ['first_name', 'middle_name', 'last_name', 'suffix'];

    public function __construct() {
        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (!is_file($autoload)) {
            sendError('Resume processing is not installed on the server (missing server/vendor). Please contact the administrator.', 503);
        }
        require_once $autoload;
        require_once __DIR__ . '/../services/resume/ResumeProcessor.php';
    }

    // ─── GET /resume-profile ────────────────────────────────────────────────
    public function show() {
        $payload = requireRole(['jobseeker']);
        sendSuccess('Resume profile', $this->payload((int)$payload['sub']));
    }

    // ─── POST /resume-profile/upload ────────────────────────────────────────
    public function upload() {
        $payload = requireRole(['jobseeker']);
        $userId = (int)$payload['sub'];
        if (!throttleRequest('resume_upload:' . $userId, 15, 3600)) {
            sendError('Too many resume uploads. Please wait a while before trying again.', 429);
        }

        $file = $_FILES['resume'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            sendError('Please choose a resume file to upload.', 400);
        }
        $maxBytes = ResumeProcessor::maxBytes();
        $maxMb = (int)($maxBytes / 1024 / 1024);
        if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            sendError("The file is too large. The maximum size is {$maxMb} MB.", 413);
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            sendError('The upload did not complete. Please try again.', 400);
        }
        if ($file['size'] <= 0) sendError('The uploaded file is empty.', 422);
        if ($file['size'] > $maxBytes) sendError("The file is too large. The maximum size is {$maxMb} MB.", 413);

        $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'docx'], true)) {
            sendError('Unsupported file type. Please upload your resume as a PDF (recommended) or DOCX file.', 415);
        }
        $mime = function_exists('finfo_open') ? (finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file['tmp_name']) ?: '') : '';
        $allowedMimes = $ext === 'pdf'
            ? ['application/pdf', 'application/x-pdf', 'application/octet-stream']
            : ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'];
        $head = (string)file_get_contents($file['tmp_name'], false, null, 0, 1024);
        $magicOk = $ext === 'pdf' ? str_contains($head, '%PDF-') : str_starts_with($head, "PK");
        if (($mime !== '' && !in_array($mime, $allowedMimes, true)) || !$magicOk) {
            sendError($ext === 'pdf'
                ? 'This file is not a valid PDF document. It may be corrupted or renamed from another format.'
                : 'This file is not a valid DOCX document.', 422);
        }

        $dir = ResumeProcessor::storageDir();
        if (!is_dir($dir) || !is_writable($dir)) {
            error_log('Resume storage directory is not writable: ' . $dir);
            sendError('Resume storage is not available on the server. Please contact the administrator.', 503);
        }
        $filename = 'resume_' . $userId . '_' . bin2hex(random_bytes(12)) . '.' . $ext;
        $target = $dir . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            sendError('The file could not be saved. Please try again.', 500);
        }
        @chmod($target, 0640);

        @set_time_limit(180);
        $db = getDB();
        try {
            $result = (new ResumeProcessor())->process($target, $ext, $this->accountHints($userId), $this->catalog());
        } catch (ResumeExtractionException $e) {
            @unlink($target);
            sendError($e->getMessage(), $e->getCode() ?: 422, ['reason' => $e->reason]);
        } catch (\Throwable $e) {
            @unlink($target);
            error_log('Resume processing failed: ' . get_class($e) . ' ' . $e->getMessage());
            sendError('We could not read this resume. Please make sure it is a standard PDF or DOCX file and try again.', 422, ['reason' => 'unreadable']);
        }

        $storedPath = ResumeProcessor::PRIVATE_PREFIX . $filename;
        $previous = $this->profileRow($userId);
        $oldUserPath = $this->userResumePath($userId);
        $originalName = mb_substr(basename(str_replace('\\', '/', (string)$file['name'])), 0, 200);
        $hash = hash_file('sha256', $target);
        $parsedJson = $result['parsed'] !== null ? json_encode($result['parsed'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : null;
        $mimeType = $ext === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        $size = (int)$file['size'];
        $aiUsed = $result['ai_used'] ? 1 : 0;

        $db->begin_transaction();
        try {
            $stmt = $db->prepare(
                "INSERT INTO resume_profiles (user_id, file_path, original_name, mime_type, file_size, file_hash, page_count, status, extraction_method, ai_used, raw_text, parsed_data, confirmed_data, confirmed_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NULL,NULL)
                 ON DUPLICATE KEY UPDATE file_path=VALUES(file_path), original_name=VALUES(original_name), mime_type=VALUES(mime_type), file_size=VALUES(file_size),
                   file_hash=VALUES(file_hash), page_count=VALUES(page_count), status=VALUES(status), extraction_method=VALUES(extraction_method), ai_used=VALUES(ai_used),
                   raw_text=VALUES(raw_text), parsed_data=VALUES(parsed_data), confirmed_data=NULL, confirmed_at=NULL, created_at=CURRENT_TIMESTAMP"
            );
            $stmt->bind_param('isssisississ', $userId, $storedPath, $originalName, $mimeType, $size, $hash, $result['pages'], $result['status'], $result['method'], $aiUsed, $result['raw_text'], $parsedJson);
            $stmt->execute();
            $upd = $db->prepare("UPDATE users SET resume_path = ? WHERE id = ?");
            $upd->bind_param('si', $storedPath, $userId);
            $upd->execute();
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            @unlink($target);
            error_log('Resume save failed: ' . $e->getMessage());
            sendError('The resume could not be saved. Please try again.', 500);
        }

        // The replaced file is removed only after the new one is safely recorded.
        foreach (array_unique(array_filter([$previous['file_path'] ?? null, $oldUserPath])) as $old) {
            if ($old !== $storedPath) $this->deleteStoredFile($old);
        }
        $this->deleteLegacyParsedText($userId);

        $message = $result['status'] === 'needs_ocr'
            ? 'Your resume was uploaded, but it looks like a scanned image without readable text. Text recognition is needed before we can read it.'
            : 'Resume processed. Please review the extracted information before it is added to your profile.';
        sendSuccess($message, $this->payload($userId));
    }

    // ─── POST /resume-profile/ocr ───────────────────────────────────────────
    /** Accepts text recognised in the browser (Tesseract) for scanned resumes. */
    public function submitOcrText() {
        $payload = requireRole(['jobseeker']);
        $userId = (int)$payload['sub'];
        $row = $this->profileRow($userId);
        if (!$row) sendError('Please upload your resume first.', 404);
        if ($row['status'] !== 'needs_ocr') sendError('Text recognition is only needed for scanned resumes.', 409);

        $body = $this->rawJson();
        $text = is_string($body['text'] ?? null) ? $body['text'] : '';
        $text = ResumeTextExtractor::cleanText(mb_substr($text, 0, 100000));
        if (preg_match_all('/\p{L}/u', $text) < 60) {
            sendError('Not enough readable text was recognised. Please upload a clearer scan or a text-based PDF.', 422, ['reason' => 'ocr_insufficient']);
        }
        require_once __DIR__ . '/../services/resume/ResumeProcessor.php';
        $parsed = (new ResumeProcessor())->parseText($text, $this->accountHints($userId), $this->catalog(), null, true);
        $json = json_encode($parsed, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        $stmt = $this->db()->prepare("UPDATE resume_profiles SET status='parsed', extraction_method='ocr_browser', raw_text=?, parsed_data=? WHERE user_id=?");
        $stmt->bind_param('ssi', $text, $json, $userId);
        $stmt->execute();
        sendSuccess('Text recognised. Please review the extracted information carefully.', $this->payload($userId));
    }

    // ─── POST /resume-profile/confirm ───────────────────────────────────────
    public function confirm() {
        $payload = requireRole(['jobseeker']);
        $userId = (int)$payload['sub'];
        $row = $this->profileRow($userId);
        if (!$row || !in_array($row['status'], ['parsed', 'confirmed'], true)) {
            sendError('There is no processed resume to confirm. Please upload your resume first.', 409);
        }
        $body = $this->rawJson();
        $resume = $this->sanitizeResume(is_array($body['resume'] ?? null) ? $body['resume'] : []);
        $updates = is_array($body['profile_updates'] ?? null) ? $body['profile_updates'] : [];

        $db = $this->db();
        $stmt = $db->prepare("SELECT " . implode(',', self::PROFILE_FIELDS) . ", is_verified FROM users WHERE id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $current = $stmt->get_result()->fetch_assoc();
        if (!$current) sendError('User not found', 404);

        [$sets, $errors, $skipped] = $this->validateProfileUpdates($updates, $current);
        if ($errors) sendError('Some profile fields are invalid. Please correct them and confirm again.', 422, $errors);

        // Map confirmed skills to catalog skills (server-side, never trusting client IDs).
        $catalog = $this->catalog();
        $existingIds = array_map('intval', array_column($this->userSkills($userId), 'id'));
        $toAdd = [];
        foreach ($resume['skills'] as &$skill) {
            $match = SkillNormalizer::mapToCatalog($skill['name'], $catalog);
            $skill['catalog_skill_id'] = $match['id'] ?? null;
            $skill['catalog_skill_name'] = $match['skill_name'] ?? null;
            if ($match && !in_array($match['id'], $existingIds, true) && !in_array($match['id'], $toAdd, true)) {
                $toAdd[] = $match['id'];
            }
        }
        unset($skill);
        $previousConfirmed = json_decode((string)($row['confirmed_data'] ?? ''), true) ?: [];
        $resume['added_skill_ids'] = array_values(array_unique(array_merge((array)($previousConfirmed['added_skill_ids'] ?? []), $toAdd)));
        $resume['confirmed_profile_fields'] = array_keys($sets);

        $db->begin_transaction();
        try {
            if ($sets) {
                $sql = 'UPDATE users SET ' . implode(', ', array_map(fn($f) => "$f = ?", array_keys($sets))) . ' WHERE id = ?';
                $params = array_values($sets);
                $params[] = $userId;
                $u = $db->prepare($sql);
                $u->bind_param(str_repeat('s', count($sets)) . 'i', ...$params);
                $u->execute();
            }
            if ($toAdd) {
                // INSERT ... SELECT guarded by NOT EXISTS keeps user_skills free of duplicates.
                $ins = $db->prepare("INSERT INTO user_skills (user_id, skill_id) SELECT ?, ? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM user_skills WHERE user_id = ? AND skill_id = ?)");
                foreach ($toAdd as $skillId) {
                    $ins->bind_param('iiii', $userId, $skillId, $userId, $skillId);
                    $ins->execute();
                }
            }
            $json = json_encode($resume, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            $r = $db->prepare("UPDATE resume_profiles SET status='confirmed', confirmed_data=?, confirmed_at=NOW() WHERE user_id=?");
            $r->bind_param('si', $json, $userId);
            $r->execute();
            $log = $db->prepare("INSERT INTO system_logs (user_id, action) VALUES (?, ?)");
            if ($log) {
                $action = 'Confirmed resume information (' . count($sets) . ' profile fields, ' . count($toAdd) . ' skills added)';
                $log->bind_param('is', $userId, $action);
                @$log->execute();
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            error_log('Resume confirmation failed: ' . $e->getMessage());
            sendError('Your profile could not be updated. No changes were saved. Please try again.', 500);
        }

        $data = $this->payload($userId);
        $data['applied'] = ['profile_fields' => array_keys($sets), 'skills_added' => count($toAdd), 'skipped' => $skipped];
        sendSuccess('Your profile has been updated from your resume.', $data);
    }

    // ─── DELETE /resume-profile ─────────────────────────────────────────────
    public function destroy() {
        $payload = requireRole(['jobseeker']);
        $userId = (int)$payload['sub'];
        $row = $this->profileRow($userId);
        $userPath = $this->userResumePath($userId);
        if (!$row && !$userPath) sendError('You have no uploaded resume to delete.', 404);

        $removeSkills = ($_GET['remove_skills'] ?? '') === '1';
        $confirmed = $row ? (json_decode((string)($row['confirmed_data'] ?? ''), true) ?: []) : [];
        $db = $this->db();
        $db->begin_transaction();
        try {
            if ($removeSkills && !empty($confirmed['added_skill_ids'])) {
                $del = $db->prepare("DELETE FROM user_skills WHERE user_id = ? AND skill_id = ?");
                foreach ((array)$confirmed['added_skill_ids'] as $skillId) {
                    $skillId = (int)$skillId;
                    $del->bind_param('ii', $userId, $skillId);
                    $del->execute();
                }
            }
            $d = $db->prepare("DELETE FROM resume_profiles WHERE user_id = ?");
            $d->bind_param('i', $userId);
            $d->execute();
            $u = $db->prepare("UPDATE users SET resume_path = NULL WHERE id = ?");
            $u->bind_param('i', $userId);
            $u->execute();
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            error_log('Resume deletion failed: ' . $e->getMessage());
            sendError('The resume could not be deleted. Please try again.', 500);
        }
        foreach (array_unique(array_filter([$row['file_path'] ?? null, $userPath])) as $path) {
            $this->deleteStoredFile($path);
        }
        $this->deleteLegacyParsedText($userId);
        sendSuccess('Your resume and the information extracted from it have been deleted.', $this->payload($userId));
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    private function db(): mysqli {
        return getDB();
    }

    private function payload(int $userId): array {
        $row = $this->profileRow($userId);
        $db = $this->db();
        $stmt = $db->prepare("SELECT first_name, middle_name, last_name, suffix, email, " . implode(',', array_diff(self::PROFILE_FIELDS, self::NAME_FIELDS)) . ", is_verified, resume_path FROM users WHERE id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $profile = $stmt->get_result()->fetch_assoc() ?: [];
        $profile['is_verified'] = !empty($profile['is_verified']);
        $resumePath = !empty($profile['resume_path']) && ResumeProcessor::resolvePath($profile['resume_path']) !== null ? $profile['resume_path'] : null;
        $hasLegacyResume = !$row && $resumePath !== null;
        unset($profile['resume_path']);

        return [
            'resume' => $row ? [
                'original_name' => $row['original_name'],
                'file_size' => (int)$row['file_size'],
                'page_count' => (int)$row['page_count'],
                'status' => $row['status'],
                'extraction_method' => $row['extraction_method'],
                'ai_used' => (bool)$row['ai_used'],
                'uploaded_at' => $row['created_at'],
                'confirmed_at' => $row['confirmed_at'],
            ] : null,
            'has_legacy_resume' => $hasLegacyResume,
            'resume_path' => $resumePath,
            'extracted' => $row && $row['parsed_data'] ? json_decode($row['parsed_data'], true) : null,
            'confirmed' => $row && $row['confirmed_data'] ? json_decode($row['confirmed_data'], true) : null,
            'current_profile' => $profile,
            'current_skills' => $this->userSkills($userId),
            'limits' => ['max_size_mb' => (int)(ResumeProcessor::maxBytes() / 1024 / 1024), 'max_pages' => ResumeProcessor::maxPages(), 'types' => ['pdf', 'docx']],
            'ai_enabled' => ResumeAiExtractor::enabled(),
        ];
    }

    private function profileRow(int $userId): ?array {
        $stmt = $this->db()->prepare("SELECT * FROM resume_profiles WHERE user_id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    private function userResumePath(int $userId): ?string {
        $stmt = $this->db()->prepare("SELECT resume_path FROM users WHERE id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $path = $stmt->get_result()->fetch_row()[0] ?? null;
        return $path ?: null;
    }

    private function userSkills(int $userId): array {
        $stmt = $this->db()->prepare("SELECT s.id, s.skill_name FROM user_skills us JOIN skills s ON s.id = us.skill_id WHERE us.user_id = ? GROUP BY s.id, s.skill_name ORDER BY s.skill_name");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    private function catalog(): array {
        $res = $this->db()->query("SELECT id, skill_name FROM skills WHERE COALESCE(archived, 0) = 0");
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    private function accountHints(int $userId): array {
        $stmt = $this->db()->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: [];
    }

    private function deleteStoredFile(string $stored): void {
        // Only files inside the resume storage directory or the legacy uploads/resumes folder are removed.
        $path = ResumeProcessor::resolvePath($stored);
        if ($path === null) return;
        $real = realpath($path);
        $allowed = array_filter([realpath(ResumeProcessor::storageDir()), realpath(__DIR__ . '/../uploads/resumes')]);
        foreach ($allowed as $dir) {
            if ($real !== false && str_starts_with($real, $dir . DIRECTORY_SEPARATOR)) {
                @unlink($real);
                return;
            }
        }
    }

    private function deleteLegacyParsedText(int $userId): void {
        $legacy = __DIR__ . '/../uploads/resumes/parsed/resume_' . $userId . '.txt';
        if (is_file($legacy)) @unlink($legacy);
    }

    /** JSON body without the HTML-entity encoding used by getJsonBody(); values are sanitized below. */
    private function rawJson(): array {
        $raw = file_get_contents('php://input');
        if (strlen((string)$raw) > 2 * 1024 * 1024) sendError('Request too large', 413);
        $data = json_decode((string)$raw, true);
        return is_array($data) ? $data : [];
    }

    private function clean($value, int $max = 255): ?string {
        if (!is_scalar($value)) return null;
        $value = strip_tags((string)$value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        $value = trim(preg_replace('/[ \t]+/u', ' ', $value));
        if ($value === '') return null;
        return mb_substr($value, 0, $max);
    }

    private function cleanDate($value): ?string {
        $value = $this->clean($value, 10);
        return $value !== null && preg_match('/^(19|20)\d{2}(-(0[1-9]|1[0-2]))?$/', $value) ? $value : null;
    }

    /** Whitelist and bound the confirmed resume structure. */
    private function sanitizeResume(array $r): array {
        $list = fn($v, $max) => array_slice(is_array($v) ? array_values($v) : [], 0, $max);
        $out = [
            'full_name' => $this->clean($r['full_name'] ?? null, 150),
            'email' => filter_var($r['email'] ?? '', FILTER_VALIDATE_EMAIL) ? mb_substr((string)$r['email'], 0, 150) : null,
            'phone' => $this->clean($r['phone'] ?? null, 30),
            'location' => $this->clean($r['location'] ?? null, 200),
            'city' => $this->clean($r['city'] ?? null, 100),
            'province' => $this->clean($r['province'] ?? null, 100),
            'objective' => $this->clean($r['objective'] ?? null, 2000),
            'education_level' => in_array($r['education_level'] ?? null, ['no_formal_education', 'elementary', 'high_school', 'senior_high', 'vocational', 'college', 'postgraduate'], true) ? $r['education_level'] : null,
            'education' => [], 'experience' => [], 'skills' => [], 'certifications' => [], 'trainings' => [],
            'projects' => [], 'awards' => [], 'languages' => [], 'volunteer' => [],
        ];
        foreach ($list($r['education'] ?? [], 15) as $e) {
            if (!is_array($e)) continue;
            $entry = [
                'degree' => $this->clean($e['degree'] ?? null, 150), 'field_of_study' => $this->clean($e['field_of_study'] ?? null, 150),
                'institution' => $this->clean($e['institution'] ?? null, 200), 'start_date' => $this->cleanDate($e['start_date'] ?? null),
                'end_date' => $this->cleanDate($e['end_date'] ?? null),
                'status' => in_array($e['status'] ?? null, ['completed', 'ongoing', 'incomplete', 'expected'], true) ? $e['status'] : null,
                'level' => in_array($e['level'] ?? null, ['elementary', 'high_school', 'senior_high', 'vocational', 'college', 'postgraduate'], true) ? $e['level'] : null,
                'honors' => $this->clean($e['honors'] ?? null, 150),
            ];
            if ($entry['degree'] || $entry['institution']) $out['education'][] = $entry;
        }
        $months = 0; $hasMonths = false;
        foreach ($list($r['experience'] ?? [], 25) as $e) {
            if (!is_array($e)) continue;
            $entry = [
                'title' => $this->clean($e['title'] ?? null, 150), 'company' => $this->clean($e['company'] ?? null, 150),
                'location' => $this->clean($e['location'] ?? null, 150), 'start_date' => $this->cleanDate($e['start_date'] ?? null),
                'end_date' => $this->cleanDate($e['end_date'] ?? null), 'is_current' => !empty($e['is_current']),
                'responsibilities' => array_values(array_filter(array_map(fn($x) => $this->clean($x, 400), $list($e['responsibilities'] ?? [], 20)))),
            ];
            if (!$entry['title'] && !$entry['company']) continue;
            $entry['duration_months'] = $this->months($entry['start_date'], $entry['is_current'] ? date('Y-m') : $entry['end_date']);
            $out['experience'][] = $entry;
        }
        $out['total_experience_months'] = $this->totalMonths($out['experience']);
        $seen = [];
        foreach ($list($r['skills'] ?? [], 100) as $s) {
            $name = $this->clean(is_array($s) ? ($s['name'] ?? null) : $s, 60);
            if ($name === null || mb_strlen($name) < 2) continue;
            $key = SkillNormalizer::key($name);
            if (isset($seen[$key])) continue; // duplicates collapse to one skill
            $seen[$key] = true;
            $out['skills'][] = ['name' => $name, 'category' => SkillNormalizer::categorize($name)];
        }
        foreach ($list($r['certifications'] ?? [], 30) as $c) {
            $name = $this->clean(is_array($c) ? ($c['name'] ?? null) : $c, 250);
            if ($name) $out['certifications'][] = ['name' => $name, 'type' => in_array($c['type'] ?? null, ['license', 'eligibility', 'certification'], true) ? $c['type'] : 'certification', 'issuer' => $this->clean($c['issuer'] ?? null, 100), 'date' => $this->cleanDate($c['date'] ?? null)];
        }
        foreach (['trainings', 'projects', 'awards', 'volunteer'] as $k) {
            foreach ($list($r[$k] ?? [], 30) as $i) {
                $name = $this->clean(is_array($i) ? ($i['name'] ?? null) : $i, 250);
                if ($name) $out[$k][] = ['name' => $name, 'date' => is_array($i) ? $this->cleanDate($i['date'] ?? null) : null];
            }
        }
        foreach ($list($r['languages'] ?? [], 15) as $l) {
            $name = $this->clean(is_array($l) ? ($l['name'] ?? null) : $l, 60);
            if ($name) $out['languages'][] = ['name' => $name, 'proficiency' => is_array($l) ? $this->clean($l['proficiency'] ?? null, 40) : null];
        }
        return $out;
    }

    private function months(?string $start, ?string $end): ?int {
        if (!$start || !$end || $end < $start) return null;
        [$sy, $sm] = array_map('intval', explode('-', $start . '-01'));
        [$ey, $em] = array_map('intval', explode('-', $end . '-01'));
        return ($ey - $sy) * 12 + ($em - $sm) + 1;
    }

    private function totalMonths(array $experience): ?int {
        $periods = [];
        foreach ($experience as $e) {
            $end = $e['is_current'] ? date('Y-m') : $e['end_date'];
            if ($e['start_date'] && $end && $end >= substr($e['start_date'], 0, 7)) $periods[] = [substr($e['start_date'] . '-01', 0, 7), substr($end . '-01', 0, 7)];
        }
        if (!$periods) return null;
        usort($periods, fn($a, $b) => strcmp($a[0], $b[0]));
        $total = 0; $curStart = null; $curEnd = null;
        foreach ($periods as [$s, $e]) {
            if ($curEnd !== null && $s <= $curEnd) { $curEnd = max($curEnd, $e); continue; }
            if ($curStart !== null) $total += $this->months($curStart, $curEnd);
            $curStart = $s; $curEnd = $e;
        }
        $total += $this->months($curStart, $curEnd);
        return $total;
    }

    /** @return array{0: array<string,string>, 1: array<string,string>, 2: array<string,string>} */
    private function validateProfileUpdates(array $updates, array $current): array {
        $sets = []; $errors = []; $skipped = [];
        foreach ($updates as $field => $value) {
            if (!in_array($field, self::PROFILE_FIELDS, true)) continue;
            $value = $this->clean($value, $field === 'bio' ? 2000 : 255);
            if ($value === null) continue; // never blank out existing information
            if (in_array($field, self::NAME_FIELDS, true) && !empty($current['is_verified']) && trim((string)$current[$field]) !== '' && $current[$field] !== $value) {
                $skipped[$field] = 'Your account is verified, so your name can only be changed by an administrator.';
                continue;
            }
            switch ($field) {
                case 'first_name': case 'last_name': case 'middle_name':
                    if (!preg_match("/^[\p{L}][\p{L} .'\-]{0,99}$/u", $value)) $errors[$field] = 'Names may only contain letters, spaces, periods, apostrophes and hyphens.';
                    break;
                case 'suffix':
                    if (mb_strlen($value) > 20) $errors[$field] = 'Suffix is too long.';
                    break;
                case 'phone':
                    if (!preg_match('/^\+?[\d\s\-()]+$/', $value) || !validatePhone($value)) $errors[$field] = 'Enter a valid phone number (10 to 15 digits).';
                    break;
                case 'birth_date':
                    if (!validateDate($value) || $value > date('Y-m-d')) $errors[$field] = 'Enter a valid birth date (YYYY-MM-DD).';
                    break;
                case 'sex':
                    if (!in_array($value, ['male', 'female', 'other'], true)) $errors[$field] = 'Invalid value.';
                    break;
                case 'civil_status':
                    if (!in_array($value, ['single', 'married', 'widowed', 'separated'], true)) $errors[$field] = 'Invalid value.';
                    break;
                case 'education_level':
                    if (!in_array($value, ['no_formal_education', 'elementary', 'high_school', 'senior_high', 'vocational', 'college', 'postgraduate'], true)) $errors[$field] = 'Invalid value.';
                    break;
                case 'employment_status':
                    if (!in_array($value, ['unemployed', 'employed', 'self_employed', 'student'], true)) $errors[$field] = 'Invalid value.';
                    break;
                case 'city': case 'province':
                    if (mb_strlen($value) > 100) $errors[$field] = 'Too long.';
                    break;
            }
            if (!isset($errors[$field])) $sets[$field] = $value;
        }
        return [$sets, $errors, $skipped];
    }
}
