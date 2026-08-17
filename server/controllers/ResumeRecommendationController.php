<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/validator.php';

class ResumeRecommendationController {

    public function recommendFromResume($userId) {
        $payload = requireAuth();
        verifyOwnership($userId, $payload, ['admin', 'peso', 'clcdo']);

        $db = getDB();
        $stmt = $db->prepare("SELECT resume_path, education_level, employment_status, bio FROM users WHERE id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if (!$user || empty($user['resume_path'])) {
            sendError('No resume uploaded for this user', 404);
        }

        $resumePath = __DIR__ . '/../' . ltrim((string)$user['resume_path'], '/');
        $resumeText = '';
        if (file_exists($resumePath)) {
            $resumeText = $this->readResumeText($resumePath);
        }

        if ($resumeText === '') {
            $resumeText = trim((string)($user['bio'] ?? ''));
        }

        $normalizedResumeText = $this->normalizeText($resumeText);
        $resumeSkills = $this->extractSkillKeywords($db, $normalizedResumeText);

        $jobsStmt = $db->prepare(
            "SELECT j.id as job_id, j.title, j.job_type, j.education_required, j.experience_level, e.company_name, e.industry
             FROM jobs j
             JOIN employers e ON e.id = j.employer_id
             WHERE j.approval_status = 'approved' AND (j.deadline IS NULL OR j.deadline >= CURDATE())"
        );
        $jobsStmt->execute();
        $jobs = $jobsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $jobSkillStmt = $db->prepare(
            "SELECT js.job_id, s.skill_name
             FROM job_skills js
             JOIN skills s ON s.id = js.skill_id"
        );
        $jobSkillStmt->execute();
        $jobSkillRows = $jobSkillStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $jobSkillsByJob = [];
        foreach ($jobSkillRows as $row) {
            $jobId = (int)$row['job_id'];
            $jobSkillsByJob[$jobId][] = strtolower(trim((string)$row['skill_name']));
        }

        $matchedJobs = [];
        foreach ($jobs as $job) {
            $jobId = (int)$job['job_id'];
            $jobSkillNames = array_values(array_unique($jobSkillsByJob[$jobId] ?? []));
            if (empty($jobSkillNames)) {
                continue;
            }

            $matchedSkillNames = array_values(array_unique(array_intersect($resumeSkills, $jobSkillNames)));
            $similarity = count($matchedSkillNames) / count($jobSkillNames);
            $educationFactor = $this->educationScore($user['education_level'] ?? '', $job['education_required'] ?? '');
            $experienceFactor = $this->experienceScore($user['employment_status'] ?? '', $job['experience_level'] ?? '');
            $jobTypeFactor = $this->jobTypeScore($user['employment_status'] ?? '', $job['job_type'] ?? '');
            $matchScore = round(min(100, max(0, ($similarity * 70) + ($educationFactor * 15) + ($experienceFactor * 10) + ($jobTypeFactor * 5))), 2);

            if ($matchScore < 10) {
                continue;
            }

            $matchedJobs[] = [
                'job_id' => $jobId,
                'title' => $job['title'],
                'company_name' => $job['company_name'],
                'industry' => $job['industry'] ?? null,
                'location' => $job['location'] ?? null,
                'job_type' => $job['job_type'] ?? null,
                'match_score' => $matchScore,
                'matched_skills' => $matchedSkillNames,
                'total_required_skills' => count($jobSkillNames),
            ];
        }

        usort($matchedJobs, fn($a, $b) => $b['match_score'] <=> $a['match_score']);
        $matchedJobs = array_slice($matchedJobs, 0, 10);

        $recommendations = [];
        foreach ($matchedJobs as $job) {
            $recommendations[] = [
                'job_id' => $job['job_id'],
                'skill_name' => $job['title'],
                'matched_from_resume' => true,
                'match_score' => $job['match_score'],
                'company_name' => $job['company_name'],
            ];
        }

        sendSuccess('Resume-based recommendations', [
            'resume_text_excerpt' => substr($resumeText, 0, 400),
            'recommendations' => $recommendations,
            'matched_jobs' => $matchedJobs,
            'education_level' => $user['education_level'],
            'total_matches' => count($matchedJobs),
        ]);
    }

    private function readResumeText($filePath) {
        if (!is_file($filePath)) {
            return '';
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($ext === 'txt') {
            $text = file_get_contents($filePath);
            return trim((string)$text);
        }

        if ($ext === 'pdf') {
            if (function_exists('shell_exec')) {
                $command = 'pdftotext ' . escapeshellarg($filePath) . ' - 2>/dev/null';
                $output = shell_exec($command);
                if (!empty($output)) {
                    return trim((string)$output);
                }
            }
        }

        if ($ext === 'docx') {
            $zip = new ZipArchive();
            if ($zip->open($filePath) === true) {
                $xml = $zip->getFromName('word/document.xml');
                $zip->close();
                if ($xml) {
                    $text = preg_replace('/<[^>]+>/', ' ', $xml);
                    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $text = preg_replace('/\s+/', ' ', $text);
                    return trim((string)$text);
                }
            }
        }

        $content = file_get_contents($filePath);
        if (is_string($content) && $content !== '') {
            $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', ' ', $content);
            return trim((string)$content);
        }

        return '';
    }

    private function normalizeText($text) {
        $text = mb_strtolower((string)$text, 'UTF-8');
        $text = preg_replace('/[^a-z0-9\s\-]/u', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    private function extractSkillKeywords($db, $text) {
        $allSkills = $db->query("SELECT skill_name FROM skills WHERE archived = FALSE ORDER BY skill_name")->fetch_all(MYSQLI_ASSOC);
        $knownSkills = array_map(fn($row) => strtolower(trim((string)$row['skill_name'])), $allSkills);
        $keywords = [];

        foreach ($knownSkills as $skill) {
            if ($skill === '') {
                continue;
            }

            if (strpos($text, $skill) !== false) {
                $keywords[] = $skill;
            }
        }

        $words = preg_split('/\s+/', $text);
        foreach ($words as $word) {
            if (strlen($word) >= 4) {
                $keywords[] = $word;
            }
        }

        return array_values(array_unique(array_filter($keywords, fn($word) => $word !== '')));
    }

    private function educationScore($userEdu, $jobEdu) {
        $levels = [
            'no_formal_education' => 0,
            'elementary' => 1,
            'high_school' => 2,
            'senior_high' => 3,
            'vocational' => 4,
            'college' => 5,
            'postgraduate' => 6,
        ];

        $userLevel = $levels[$userEdu] ?? 0;
        if (!$jobEdu) {
            return 1;
        }

        $jobEduLower = strtolower((string)$jobEdu);
        $jobLevel = 0;
        foreach ($levels as $key => $value) {
            if (strpos($jobEduLower, str_replace('_', ' ', $key)) !== false || strpos($jobEduLower, $key) !== false) {
                $jobLevel = $value;
                break;
            }
        }

        if ($jobLevel === 0) {
            return 1;
        }

        if ($userLevel >= $jobLevel) return 1;
        if ($userLevel === $jobLevel - 1) return 0.7;
        if ($userLevel === $jobLevel - 2) return 0.4;
        return 0.1;
    }

    private function experienceScore($empStatus, $expLevel) {
        if (!$expLevel) return 1;
        $empStatus = strtolower((string)$empStatus);
        $expLevel = strtolower((string)$expLevel);

        if (strpos($expLevel, 'entry') !== false || strpos($expLevel, 'junior') !== false) {
            if (in_array($empStatus, ['unemployed', 'student', 'self_employed'], true)) return 1;
            if ($empStatus === 'employed') return 0.8;
            return 0.6;
        }

        if (strpos($expLevel, 'mid') !== false || strpos($expLevel, 'intermediate') !== false) {
            if (in_array($empStatus, ['employed', 'self_employed'], true)) return 1;
            if ($empStatus === 'unemployed') return 0.5;
            return 0.3;
        }

        if (strpos($expLevel, 'senior') !== false || strpos($expLevel, 'executive') !== false) {
            if ($empStatus === 'employed') return 1;
            if ($empStatus === 'self_employed') return 0.9;
            return 0.2;
        }

        return 0.6;
    }

    private function jobTypeScore($userStatus, $jobType) {
        $userStatus = strtolower((string)$userStatus);
        $jobType = strtolower((string)$jobType);

        $map = [
            'employed' => ['full_time' => 1, 'part_time' => 0.7, 'contract' => 0.8],
            'unemployed' => ['full_time' => 1, 'part_time' => 0.9, 'contract' => 0.8],
            'student' => ['part_time' => 1, 'full_time' => 0.6, 'contract' => 0.7],
            'self_employed' => ['contract' => 1, 'full_time' => 0.7, 'part_time' => 0.8],
        ];

        return $map[$userStatus][$jobType] ?? 0.6;
    }
}
