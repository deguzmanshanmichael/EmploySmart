<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/validator.php';
require_once __DIR__ . '/../services/resume/SkillNormalizer.php';

/**
 * Skills progression and gap tracking for PESO.
 *
 * Demand = skills required by open job postings (tagged job skills plus skills named
 * in the title/requirements). Supply = active jobseekers who have the skill in their
 * profile. Gaps are matched with open training programs so PESO/CLCDO can plan
 * targeted interventions.
 */
class SkillGapController {

    private const STAFF = ['admin', 'peso', 'clcdo'];

    /** GET /skill-gaps */
    public function overview() {
        requireRole(self::STAFF);
        $db = getDB();
        $analysis = $this->analyse($db);
        $jobseekers = $analysis['jobseekers'];

        $rows = [];
        foreach ($analysis['demand'] as $skillId => $d) {
            $holders = $analysis['supply'][$skillId] ?? 0;
            $rows[] = [
                'skill_id' => $skillId,
                'skill_name' => $analysis['catalog'][$skillId],
                'open_jobs' => $d['jobs'],
                'vacancies' => $d['vacancies'],
                'jobseekers_with_skill' => $holders,
                'coverage_pct' => $jobseekers ? round($holders / $jobseekers * 100) : 0,
                'shortage' => max(0, $d['vacancies'] - $holders),
                'sample_jobs' => array_slice($d['titles'], 0, 3),
                'trainings' => $analysis['trainings'][$skillId] ?? [],
            ];
        }
        usort($rows, fn($a, $b) => [$b['shortage'], $b['open_jobs'], $a['coverage_pct']] <=> [$a['shortage'], $a['open_jobs'], $b['coverage_pct']]);

        sendSuccess('Skills gap overview', [
            'totals' => [
                'open_jobs' => $analysis['open_jobs'],
                'jobseekers' => $jobseekers,
                'skills_in_demand' => count($rows),
                'skills_without_training' => count(array_filter($rows, fn($r) => !$r['trainings'])),
                'total_shortage' => array_sum(array_column($rows, 'shortage')),
            ],
            'skills' => $rows,
            'trend' => $this->trend($db),
            'applicants_needing_support' => $this->applicantsNeedingSupport($db, $analysis, $rows),
        ]);
    }

    /** GET /skill-gaps/user/{id|me} */
    public function applicant($userId) {
        $payload = requireAuth();
        if ($userId === 'me') $userId = $payload['sub'];
        if (!validateInteger((string)$userId)) sendError('Invalid user', 422);
        $userId = (int)$userId;
        if (!in_array($payload['role'], self::STAFF, true) && (int)$payload['sub'] !== $userId) sendError('Forbidden', 403);
        $db = getDB();
        $stmt = $db->prepare("SELECT id, first_name, last_name, email, city, education_level, employment_status FROM users WHERE id = ? AND role = 'jobseeker'");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if (!$user) sendError('Jobseeker not found', 404);

        $analysis = $this->analyse($db);
        $own = $this->userSkillIds($db, $userId);
        $missing = []; $strengths = [];
        foreach ($analysis['demand'] as $skillId => $d) {
            $entry = ['skill_id' => $skillId, 'skill_name' => $analysis['catalog'][$skillId], 'open_jobs' => $d['jobs'], 'sample_jobs' => array_slice($d['titles'], 0, 3), 'trainings' => $analysis['trainings'][$skillId] ?? []];
            if (isset($own[$skillId])) $strengths[] = $entry; else $missing[] = $entry;
        }
        usort($missing, fn($a, $b) => $b['open_jobs'] <=> $a['open_jobs']);
        usort($strengths, fn($a, $b) => $b['open_jobs'] <=> $a['open_jobs']);

        // Progression: completed trainings in date order with the skills each one added.
        $stmt = $db->prepare("SELECT ut.training_id, ut.status, ut.completion_date, tp.program_name, tp.start_date,
                                     GROUP_CONCAT(s.skill_name ORDER BY s.skill_name SEPARATOR '||') AS skills
                              FROM user_training ut JOIN training_programs tp ON tp.id = ut.training_id
                              LEFT JOIN training_skills ts ON ts.training_id = ut.training_id LEFT JOIN skills s ON s.id = ts.skill_id
                              WHERE ut.user_id = ? GROUP BY ut.id ORDER BY COALESCE(ut.completion_date, tp.start_date), ut.id");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $timeline = []; $acquired = [];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $t) {
            $skills = $t['skills'] ? explode('||', $t['skills']) : [];
            if ($t['status'] === 'completed') foreach ($skills as $s) $acquired[$s] = true;
            $timeline[] = ['program_name' => $t['program_name'], 'status' => $t['status'], 'date' => $t['completion_date'] ?: $t['start_date'], 'skills' => $skills, 'skills_from_training_total' => count($acquired)];
        }

        $isStaff = in_array($payload['role'], self::STAFF, true);
        $result = [
            'user' => $user,
            'skills' => array_values(array_map(fn($id) => ['id' => $id, 'skill_name' => $analysis['catalog'][$id] ?? ''], array_keys($own))),
            'in_demand_strengths' => $strengths,
            'missing_in_demand' => $missing,
            'demand_coverage_pct' => $analysis['demand'] ? round(count($strengths) / count($analysis['demand']) * 100) : null,
            'timeline' => $timeline,
            'placement_feedback' => $this->placementFeedbackFor($db, $userId, $isStaff),
        ];
        if ($isStaff) {
            $stmt = $db->prepare("SELECT title, message, created_at, is_read FROM notifications WHERE target_user_id = ? AND category = 'training_recommendation' ORDER BY created_at DESC LIMIT 10");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result['interventions'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
        sendSuccess('Applicant skills progression', $result);
    }

    /** POST /skill-gaps/user/{id}/recommend — targeted intervention: recommend a training. */
    public function recommendTraining($userId) {
        $payload = requireRole(self::STAFF);
        if (!validateInteger((string)$userId)) sendError('Invalid user', 422);
        $userId = (int)$userId;
        $data = json_decode((string)file_get_contents('php://input'), true) ?: [];
        $trainingId = (int)($data['training_id'] ?? 0);
        $note = trim(mb_substr(strip_tags((string)($data['message'] ?? '')), 0, 500));
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM users WHERE id = ? AND role = 'jobseeker' AND COALESCE(archived, 0) = 0");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_row()) sendError('Jobseeker not found', 404);
        $stmt = $db->prepare("SELECT tp.id, tp.program_name, tp.start_date, tp.location, GROUP_CONCAT(s.skill_name ORDER BY s.skill_name SEPARATOR ', ') AS skills
                              FROM training_programs tp LEFT JOIN training_skills ts ON ts.training_id = tp.id LEFT JOIN skills s ON s.id = ts.skill_id
                              WHERE tp.id = ? AND COALESCE(tp.archived, 0) = 0 AND tp.status IN ('upcoming', 'ongoing') GROUP BY tp.id");
        $stmt->bind_param('i', $trainingId);
        $stmt->execute();
        $training = $stmt->get_result()->fetch_assoc();
        if (!$training) sendError('Training program not found or no longer open', 404);

        $office = strtoupper($payload['role']) === 'CLCDO' ? 'CLCDO' : 'PESO';
        $title = 'Training recommended for you';
        $message = "$office recommends the training \"{$training['program_name']}\""
            . ($training['skills'] ? " to build: {$training['skills']}" : '')
            . ($training['start_date'] ? " (starts {$training['start_date']}" . ($training['location'] ? ", {$training['location']}" : '') . ')' : '')
            . '. Open Growth Center or the training list to apply.'
            . ($note !== '' ? " Note: $note" : '');
        $stmt = $db->prepare("INSERT INTO notifications (target_user_id, title, message, priority, category) VALUES (?, ?, ?, 'high', 'training_recommendation')");
        $stmt->bind_param('iss', $userId, $title, $message);
        $stmt->execute();
        $log = $db->prepare("INSERT INTO system_logs (user_id, action) VALUES (?, ?)");
        if ($log) {
            $action = "Recommended training #{$training['id']} to jobseeker #$userId";
            $log->bind_param('is', $payload['sub'], $action);
            @$log->execute();
        }
        sendSuccess('Training recommendation sent to the jobseeker.', ['message' => $message]);
    }

    // ─── Analysis ────────────────────────────────────────────────────────────

    private function analyse(mysqli $db): array {
        $catalogRows = $db->query("SELECT id, skill_name FROM skills WHERE COALESCE(archived, 0) = 0")->fetch_all(MYSQLI_ASSOC);
        $catalog = [];
        foreach ($catalogRows as $r) $catalog[(int)$r['id']] = $r['skill_name'];

        $jobs = $db->query("SELECT id, title, requirements, GREATEST(COALESCE(vacancies, 1), 1) AS vacancies FROM jobs
                            WHERE approval_status = 'approved' AND COALESCE(archived, 0) = 0 AND (deadline IS NULL OR deadline >= CURDATE())")->fetch_all(MYSQLI_ASSOC);
        $tagged = [];
        foreach ($db->query("SELECT js.job_id, js.skill_id FROM job_skills js")->fetch_all(MYSQLI_ASSOC) as $r) {
            $tagged[(int)$r['job_id']][(int)$r['skill_id']] = true;
        }
        $demand = [];
        foreach ($jobs as $job) {
            $ids = $tagged[(int)$job['id']] ?? [];
            foreach (SkillNormalizer::findInText($job['title'] . "\n" . $job['requirements']) as $canonical => $alias) {
                $match = SkillNormalizer::mapToCatalog($canonical, $catalogRows);
                if ($match) $ids[$match['id']] = true;
            }
            foreach (array_keys($ids) as $skillId) {
                if (!isset($catalog[$skillId])) continue;
                $demand[$skillId] ??= ['jobs' => 0, 'vacancies' => 0, 'titles' => []];
                $demand[$skillId]['jobs']++;
                $demand[$skillId]['vacancies'] += (int)$job['vacancies'];
                if (!in_array($job['title'], $demand[$skillId]['titles'], true)) $demand[$skillId]['titles'][] = $job['title'];
            }
        }

        $supply = [];
        foreach ($db->query("SELECT us.skill_id, COUNT(DISTINCT us.user_id) c FROM user_skills us JOIN users u ON u.id = us.user_id
                             WHERE u.role = 'jobseeker' AND COALESCE(u.archived, 0) = 0 GROUP BY us.skill_id")->fetch_all(MYSQLI_ASSOC) as $r) {
            $supply[(int)$r['skill_id']] = (int)$r['c'];
        }
        $jobseekers = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'jobseeker' AND COALESCE(archived, 0) = 0")->fetch_row()[0];

        $trainings = [];
        foreach ($db->query("SELECT ts.skill_id, tp.id, tp.program_name, tp.status, tp.start_date, tp.location FROM training_skills ts
                             JOIN training_programs tp ON tp.id = ts.training_id
                             WHERE COALESCE(tp.archived, 0) = 0 AND tp.status IN ('upcoming', 'ongoing') ORDER BY tp.start_date")->fetch_all(MYSQLI_ASSOC) as $r) {
            $trainings[(int)$r['skill_id']][] = ['id' => (int)$r['id'], 'program_name' => $r['program_name'], 'status' => $r['status'], 'start_date' => $r['start_date'], 'location' => $r['location']];
        }
        return ['catalog' => $catalog, 'demand' => $demand, 'supply' => $supply, 'jobseekers' => $jobseekers, 'trainings' => $trainings, 'open_jobs' => count($jobs)];
    }

    /** Monthly training completions and skills gained over the last 12 months. */
    private function trend(mysqli $db): array {
        $rows = $db->query("SELECT DATE_FORMAT(ut.completion_date, '%Y-%m') AS month, COUNT(DISTINCT ut.id) AS completions,
                                   COUNT(ts.id) AS skills_gained, COUNT(DISTINCT ut.user_id) AS jobseekers
                            FROM user_training ut LEFT JOIN training_skills ts ON ts.training_id = ut.training_id
                            WHERE ut.status = 'completed' AND ut.completion_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                            GROUP BY month")->fetch_all(MYSQLI_ASSOC);
        $byMonth = [];
        foreach ($rows as $r) $byMonth[$r['month']] = $r;
        $out = [];
        for ($i = 11; $i >= 0; $i--) {
            $m = date('Y-m', strtotime("first day of -$i month"));
            $out[] = ['month' => $m, 'completions' => (int)($byMonth[$m]['completions'] ?? 0), 'skills_gained' => (int)($byMonth[$m]['skills_gained'] ?? 0), 'jobseekers' => (int)($byMonth[$m]['jobseekers'] ?? 0)];
        }
        return $out;
    }

    /** Jobseekers missing the most in-demand skills, not yet placed, or flagged by an employer. */
    private function applicantsNeedingSupport(mysqli $db, array $analysis, array $rows): array {
        $top = array_slice(array_column($rows, 'skill_id'), 0, 10);
        if (!$top) return [];
        $users = $db->query("SELECT u.id, u.first_name, u.last_name, u.city, u.employment_status,
                                    EXISTS(SELECT 1 FROM applications a WHERE a.user_id = u.id AND a.application_status = 'accepted') AS placed,
                                    (SELECT COUNT(*) FROM user_training ut WHERE ut.user_id = u.id AND ut.status IN ('pending', 'enrolled', 'in_progress')) AS active_trainings,
                                    EXISTS(SELECT 1 FROM feedback_threads f JOIN applications a2 ON a2.id = f.target_id
                                           WHERE f.target_type = 'placement' AND a2.user_id = u.id AND f.outcome = 'needs_training') AS flagged_by_employer
                             FROM users u WHERE u.role = 'jobseeker' AND COALESCE(u.archived, 0) = 0 LIMIT 3000")->fetch_all(MYSQLI_ASSOC);
        $skillsByUser = [];
        foreach ($db->query("SELECT us.user_id, us.skill_id FROM user_skills us JOIN users u ON u.id = us.user_id WHERE u.role = 'jobseeker'")->fetch_all(MYSQLI_ASSOC) as $r) {
            $skillsByUser[(int)$r['user_id']][(int)$r['skill_id']] = true;
        }
        $out = [];
        foreach ($users as $u) {
            $have = $skillsByUser[(int)$u['id']] ?? [];
            $missing = array_values(array_filter($top, fn($id) => !isset($have[$id])));
            $flagged = (bool)$u['flagged_by_employer'];
            if (!$flagged && count($missing) < max(1, (int)ceil(count($top) / 2))) continue;
            if ((bool)$u['placed'] && !$flagged) continue;
            $out[] = [
                'id' => (int)$u['id'],
                'name' => trim($u['first_name'] . ' ' . $u['last_name']),
                'city' => $u['city'],
                'employment_status' => $u['employment_status'],
                'skill_count' => count($have),
                'missing_count' => count($missing),
                'missing_top_skills' => array_map(fn($id) => $analysis['catalog'][$id], array_slice($missing, 0, 5)),
                'placed' => (bool)$u['placed'],
                'active_trainings' => (int)$u['active_trainings'],
                'flagged_by_employer' => $flagged,
            ];
        }
        usort($out, fn($a, $b) => [$b['flagged_by_employer'], $b['missing_count'], $a['active_trainings']] <=> [$a['flagged_by_employer'], $a['missing_count'], $b['active_trainings']]);
        return array_slice($out, 0, 30);
    }

    private function userSkillIds(mysqli $db, int $userId): array {
        $stmt = $db->prepare("SELECT DISTINCT skill_id FROM user_skills WHERE user_id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $ids = [];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) $ids[(int)$r['skill_id']] = true;
        return $ids;
    }

    private function placementFeedbackFor(mysqli $db, int $userId, bool $isStaff): array {
        if (!$isStaff) return [];
        $stmt = $db->prepare("SELECT f.rating, f.outcome, f.created_at, j.title AS job_title, e.company_name FROM feedback_threads f
                              JOIN applications a ON a.id = f.target_id JOIN jobs j ON j.id = a.job_id JOIN employers e ON e.id = j.employer_id
                              WHERE f.target_type = 'placement' AND a.user_id = ? ORDER BY f.created_at DESC LIMIT 10");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        require_once __DIR__ . '/FeedbackController.php';
        foreach ($rows as &$r) $r['outcome_label'] = $r['outcome'] ? (FeedbackController::OUTCOMES[$r['outcome']] ?? $r['outcome']) : null;
        return $rows;
    }
}
