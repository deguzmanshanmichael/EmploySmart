<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/validator.php';

/**
 * Date-ranged records for printable reports. All figures come from the database;
 * the client groups records by month and draws the charts.
 */
class ReportController {

    private const MAX_ROWS = 5000;

    /** GET /reports/employment?from=YYYY-MM-DD&to=YYYY-MM-DD — PESO/admin */
    public function employment() {
        requireRole(['admin', 'peso']);
        [$from, $to] = $this->range();
        $db = getDB();
        $start = "$from 00:00:00"; $end = "$to 23:59:59";

        $jobseekers = $this->rows($db, "SELECT u.id, CONCAT_WS(' ', u.first_name, NULLIF(u.middle_name, ''), u.last_name, NULLIF(u.suffix, '')) AS name,
                    u.sex, u.birth_date, u.age, u.phone, u.address, u.city, u.province, u.education_level, u.employment_status, u.created_at AS date
             FROM users u WHERE u.role = 'jobseeker' AND COALESCE(u.archived, 0) = 0 AND u.created_at BETWEEN ? AND ? ORDER BY u.created_at", $start, $end);
        foreach ($jobseekers as &$j) {
            $j['age'] = $j['birth_date'] ? (int)date_diff(date_create($j['birth_date']), date_create('today'))->y : ($j['age'] !== null ? (int)$j['age'] : null);
            unset($j['birth_date']);
        }
        unset($j);

        $jobs = $this->rows($db, "SELECT j.id, j.title, COALESCE(e.company_name, 'Employer') AS company_name, j.location, j.vacancies, j.job_type,
                    j.approval_status, COALESCE(j.archived, 0) AS archived, j.deadline, j.created_at AS date
             FROM jobs j LEFT JOIN employers e ON e.id = j.employer_id WHERE j.created_at BETWEEN ? AND ? ORDER BY j.created_at", $start, $end);

        $applications = $this->rows($db, "SELECT a.id, CONCAT_WS(' ', u.first_name, u.last_name) AS applicant, u.sex, j.title AS job_title,
                    COALESCE(e.company_name, 'Employer') AS company_name, a.application_status AS status, a.applied_at AS date
             FROM applications a JOIN users u ON u.id = a.user_id JOIN jobs j ON j.id = a.job_id LEFT JOIN employers e ON e.id = j.employer_id
             WHERE a.applied_at BETWEEN ? AND ? ORDER BY a.applied_at", $start, $end);

        $employers = $this->rows($db, "SELECT e.id, e.company_name, e.industry, e.verification_status, e.created_at AS date
             FROM employers e WHERE e.created_at BETWEEN ? AND ? ORDER BY e.created_at", $start, $end);

        sendSuccess('Employment report data', [
            'range' => ['from' => $from, 'to' => $to],
            'jobseekers' => $jobseekers,
            'jobs' => $jobs,
            'applications' => $applications,
            'employers' => $employers,
            'totals' => [
                'all_jobseekers' => (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'jobseeker' AND COALESCE(archived, 0) = 0")->fetch_row()[0],
                'open_jobs' => (int)$db->query("SELECT COUNT(*) FROM jobs WHERE approval_status = 'approved' AND COALESCE(archived, 0) = 0 AND (deadline IS NULL OR deadline >= CURDATE())")->fetch_row()[0],
                'pending_jobs' => (int)$db->query("SELECT COUNT(*) FROM jobs WHERE approval_status = 'pending' AND COALESCE(archived, 0) = 0")->fetch_row()[0],
                'pending_employers' => (int)$db->query("SELECT COUNT(*) FROM employers WHERE verification_status = 'pending'")->fetch_row()[0],
                'pending_applications' => (int)$db->query("SELECT COUNT(*) FROM applications WHERE application_status = 'pending'")->fetch_row()[0],
            ],
        ]);
    }

    /** GET /reports/training?from=&to= — CLCDO/PESO/admin */
    public function training() {
        requireRole(['admin', 'peso', 'clcdo']);
        [$from, $to] = $this->range();
        $db = getDB();

        // Programs running at any time inside the range (start before the range end and not ended before it starts).
        $programs = $this->rows($db, "SELECT tp.id, tp.program_name, tp.location, tp.status, tp.start_date, tp.end_date, tp.max_participants,
                    COALESCE(tp.start_date, DATE(tp.created_at)) AS date,
                    (SELECT COUNT(*) FROM user_training ut WHERE ut.training_id = tp.id AND ut.status <> 'dropped') AS enrolled,
                    (SELECT COUNT(*) FROM user_training ut WHERE ut.training_id = tp.id AND ut.status = 'completed') AS completed,
                    (SELECT COUNT(*) FROM user_training ut WHERE ut.training_id = tp.id AND ut.status = 'pending') AS pending,
                    (SELECT GROUP_CONCAT(s.skill_name ORDER BY s.skill_name SEPARATOR ', ') FROM training_skills ts JOIN skills s ON s.id = ts.skill_id WHERE ts.training_id = tp.id) AS skills
             FROM training_programs tp
             WHERE COALESCE(tp.archived, 0) = 0
               AND COALESCE(tp.start_date, DATE(tp.created_at)) <= ? AND COALESCE(tp.end_date, tp.start_date, DATE(tp.created_at)) >= ?
             ORDER BY date, tp.program_name", $to, $from);

        $completions = $this->rows($db, "SELECT ut.id, CONCAT_WS(' ', u.first_name, u.last_name) AS participant, u.sex, tp.program_name,
                    ut.completion_date AS date, (ut.certificate_path IS NOT NULL AND ut.certificate_path <> '') AS has_certificate
             FROM user_training ut JOIN users u ON u.id = ut.user_id JOIN training_programs tp ON tp.id = ut.training_id
             WHERE ut.status = 'completed' AND ut.completion_date BETWEEN ? AND ? ORDER BY ut.completion_date", $from, $to);

        sendSuccess('Training report data', ['range' => ['from' => $from, 'to' => $to], 'programs' => $programs, 'completions' => $completions]);
    }

    /** GET /reports/logs?from=&to=&role=&search= — admin; every matching entry (not just one page). */
    public function logs() {
        requireRole(['admin']);
        [$from, $to] = $this->range();
        $db = getDB();
        $where = 'sl.log_time BETWEEN ? AND ?';
        $types = 'ss'; $params = ["$from 00:00:00", "$to 23:59:59"];
        $role = strtolower(trim((string)($_GET['role'] ?? '')));
        if ($role !== '') { $where .= ' AND LOWER(u.role) = ?'; $types .= 's'; $params[] = $role; }
        $search = trim((string)($_GET['search'] ?? ''));
        if ($search !== '') { $where .= ' AND sl.action LIKE ?'; $types .= 's'; $params[] = '%' . mb_substr($search, 0, 100) . '%'; }
        $stmt = $db->prepare("SELECT sl.id, sl.action, sl.log_time AS date, CONCAT_WS(' ', u.first_name, u.last_name) AS name, u.role
                              FROM system_logs sl LEFT JOIN users u ON u.id = sl.user_id WHERE $where ORDER BY sl.log_time LIMIT " . self::MAX_ROWS);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        sendSuccess('Log report data', ['range' => ['from' => $from, 'to' => $to], 'logs' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
    }

    /** Validated range; defaults to the current quarter up to today. */
    private function range(): array {
        $from = (string)($_GET['from'] ?? '');
        $to = (string)($_GET['to'] ?? '');
        if ($from === '' || $to === '') {
            $month = (int)date('n');
            $from = date('Y') . '-' . str_pad((string)(intdiv($month - 1, 3) * 3 + 1), 2, '0', STR_PAD_LEFT) . '-01';
            $to = date('Y-m-d');
        }
        if (!validateDate($from) || !validateDate($to)) sendError('Dates must use the YYYY-MM-DD format.', 422);
        if ($from > $to) sendError('The start date must be on or before the end date.', 422);
        if ((strtotime($to) - strtotime($from)) > 366 * 3 * 86400) sendError('Please choose a date range of up to 3 years.', 422);
        return [$from, $to];
    }

    private function rows(mysqli $db, string $sql, ...$params): array {
        $stmt = $db->prepare($sql . ' LIMIT ' . self::MAX_ROWS);
        $stmt->bind_param(str_repeat('s', count($params)), ...$params);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
