<?php
class JobMatchingService {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function computeScore($userId, $jobId) {
        $user = $this->getUser($userId);
        $job = $this->getJob($jobId);

        if (!$user || !$job || strtolower(trim((string)$job['approval_status'])) !== 'approved' || !empty($job['archived'])) {
            return 0;
        }
        if (!empty($job['deadline']) && $job['deadline'] < date('Y-m-d')) return 0;

        $userSkills = $this->getUserSkillIds($userId);
        $trainingSkills = $this->getUserTrainingSkillIds($userId);
        $jobSkills = $this->getJobSkillIds($jobId);

        return $this->scoreJob($user, $job, $userSkills, $trainingSkills, $jobSkills);
    }

    private function employmentStatusBonus($userStatus, $jobType) {
        $userStatus = strtolower(trim((string)$userStatus));
        $jobType = strtolower(trim(str_replace(['_', '-'], '', (string)$jobType)));
        $bonusMap = [
            'employed' => ['fulltime' => 1, 'parttime' => 0.5, 'contract' => 0.6, 'internship' => 0.4, 'temporary' => 0.5, 'freelance' => 0.6],
            'unemployed' => ['fulltime' => 1, 'parttime' => 0.8, 'contract' => 0.9, 'internship' => 0.8, 'temporary' => 0.9, 'freelance' => 0.8],
            'student' => ['parttime' => 1, 'fulltime' => 0.6, 'contract' => 0.7, 'internship' => 1, 'temporary' => 0.8, 'freelance' => 0.8],
            'self_employed' => ['contract' => 1, 'fulltime' => 0.5, 'parttime' => 0.8, 'internship' => 0.4, 'temporary' => 0.7, 'freelance' => 1],
        ];

        return $bonusMap[$userStatus][$jobType] ?? 0.5;
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

        // If job has no education requirement, give full score
        $jobEdu = strtolower(trim(str_replace(['_', '-'], ' ', (string)$jobEdu)));
        if ($jobEdu === '' || $jobEdu === 'any' || $jobEdu === 'not required' || $jobEdu === 'none') {
            return 1;
        }

        // Normalize user education level
        $userEdu = strtolower(trim(str_replace([' ', '-'], '_', (string)$userEdu)));
        $userLevel = $levels[$userEdu] ?? 0;
        
        // Parse job education requirement
        $jobLevel = 0;
        $jobEduLower = strtolower($jobEdu);
        
        if (stripos($jobEduLower, 'postgraduate') !== false || stripos($jobEduLower, 'master') !== false || stripos($jobEduLower, 'phd') !== false) {
            $jobLevel = 6;
        } elseif (stripos($jobEduLower, 'college') !== false || stripos($jobEduLower, 'bachelor') !== false || stripos($jobEduLower, 'university') !== false) {
            $jobLevel = 5;
        } elseif (stripos($jobEduLower, 'vocational') !== false) {
            $jobLevel = 4;
        } elseif (stripos($jobEduLower, 'senior high') !== false) {
            $jobLevel = 3;
        } elseif (stripos($jobEduLower, 'high school') !== false) {
            $jobLevel = 2;
        } elseif (stripos($jobEduLower, 'elementary') !== false) {
            $jobLevel = 1;
        }

        // Score based on education match
        if ($userLevel >= $jobLevel) {
            return 1; // Perfect match or exceeds requirement
        } elseif ($userLevel == $jobLevel - 1) {
            return 0.7; // One level below
        } elseif ($userLevel == $jobLevel - 2) {
            return 0.4; // Two levels below
        } else {
            return 0.1; // More than two levels below
        }
    }

    private function experienceScore($empStatus, $expLevel) {
        if (!$expLevel) {
            return 1; // No experience requirement = perfect match
        }

        $expLevelLower = strtolower(trim((string)$expLevel));
        $empStatusLower = strtolower(trim((string)$empStatus));

        // Entry level jobs
        if (stripos($expLevelLower, 'entry') !== false || stripos($expLevelLower, 'junior') !== false) {
            $entryApplicable = in_array($empStatusLower, ['unemployed', 'student', 'self_employed'], true);
            if ($entryApplicable) {
                return 1;
            }
            if ($empStatusLower === 'employed') {
                return 0.8; // Experienced person can take entry role
            }
            return 0.6;
        }

        // Mid level jobs
        if (stripos($expLevelLower, 'mid') !== false || stripos($expLevelLower, 'intermediate') !== false) {
            if (in_array($empStatusLower, ['employed', 'self_employed'], true)) {
                return 1;
            }
            if ($empStatusLower === 'unemployed') {
                return 0.5; // Might have prior experience
            }
            return 0.3;
        }

        // Senior level jobs
        if (stripos($expLevelLower, 'senior') !== false || stripos($expLevelLower, 'executive') !== false) {
            if ($empStatusLower === 'employed') {
                return 1;
            }
            if ($empStatusLower === 'self_employed') {
                return 0.9; // Self-employment counts as experience
            }
            return 0.2;
        }

        // Default: any status can apply
        return 0.6;
    }

    private function getUserSkillIds($userId) {
        $stmt = $this->db->prepare('SELECT skill_id FROM user_skills WHERE user_id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        return array_values(array_unique(array_map('intval', array_column($rows, 'skill_id'))));
    }

    private function getJobSkillIds($jobId) {
        $stmt = $this->db->prepare('SELECT skill_id FROM job_skills WHERE job_id = ?');
        $stmt->bind_param('i', $jobId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        return array_values(array_unique(array_map('intval', array_column($rows, 'skill_id'))));
    }

    private function getUserTrainingSkillIds($userId) {
        $stmt = $this->db->prepare(
            'SELECT DISTINCT ts.skill_id FROM user_training ut JOIN training_skills ts ON ts.training_id = ut.training_id WHERE ut.user_id = ? AND ut.status = "completed"'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        return array_values(array_unique(array_map('intval', array_column($rows, 'skill_id'))));
    }

    private function getUser($userId) {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    private function getJob($jobId) {
        $stmt = $this->db->prepare('SELECT * FROM jobs WHERE id = ?');
        $stmt->bind_param('i', $jobId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    private function scoreJob($user, $job, $userSkills, $trainingSkills, $jobSkills) {
        $score = 0.0;

        // Skill and completed-training overlap are meaningful only when the job has tagged skills.
        if (!empty($jobSkills)) {
            $jobSkills = array_values(array_unique(array_map('intval', $jobSkills)));
            $denominator = count($jobSkills);
            $score += (count(array_intersect($userSkills, $jobSkills)) / $denominator) * 40;
            $score += (count(array_intersect($trainingSkills, $jobSkills)) / $denominator) * 15;
        } else {
            // Missing job skill tags are unknown evidence, not a perfect skill match.
            $score += 27.5;
        }

        $score += $this->educationScore($user['education_level'] ?? null, $job['education_required'] ?? null) * 20;
        $score += $this->experienceScore($user['employment_status'] ?? '', $job['experience_level'] ?? '') * 15;
        $score += $this->employmentStatusBonus($user['employment_status'] ?? '', $job['job_type'] ?? '') * 10;

        return round(min(100, max(0, $score)), 2);
    }

    public function getTopMatchesForUser($userId, $limit = 10) {
        $scored = $this->getScoredJobsForUser($userId);
        return array_slice($scored, 0, max(0, (int)$limit));
    }

    private function getScoredJobsForUser($userId) {
        $user = $this->getUser($userId);
        if (!$user) {
            return [];
        }

        $userSkills = $this->getUserSkillIds($userId);
        $trainingSkills = $this->getUserTrainingSkillIds($userId);

        $stmt = $this->db->prepare(
            'SELECT j.*, e.company_name, e.industry, js.skill_id, s.skill_name FROM jobs j LEFT JOIN employers e ON e.id = j.employer_id LEFT JOIN job_skills js ON js.job_id = j.id LEFT JOIN skills s ON s.id = js.skill_id WHERE j.approval_status = ? AND j.archived = 0 AND (j.deadline IS NULL OR j.deadline >= CURDATE()) ORDER BY j.created_at DESC'
        );
        $approvedStatus = 'approved';
        $stmt->bind_param('s', $approvedStatus);
        $stmt->execute();
        $result = $stmt->get_result();
        $jobsById = [];
        while ($row = $result->fetch_assoc()) {
            $jobId = (int)$row['id'];
            if (!isset($jobsById[$jobId])) {
                $jobsById[$jobId] = $row;
                $jobsById[$jobId]['_skills'] = [];
            }
            if ($row['skill_id'] !== null) {
                $skillId = (int)$row['skill_id'];
                $jobsById[$jobId]['_skills'][$skillId] = [
                    'id' => $skillId,
                    'name' => $row['skill_name'],
                ];
            }
        }

        $scored = [];
        foreach ($jobsById as $job) {
            $jobSkillRows = array_values($job['_skills']);
            $jobSkillIds = array_column($jobSkillRows, 'id');
            $job['skills'] = array_values(array_filter(array_map(static function ($skill) {
                return $skill['name'] ? ['skill_name' => $skill['name']] : null;
            }, $jobSkillRows)));
            unset($job['_skills'], $job['skill_id'], $job['skill_name']);
            $job['match_score'] = $this->scoreJob($user, $job, $userSkills, $trainingSkills, $jobSkillIds);
            $scored[] = $job;
        }

        usort($scored, static function ($a, $b) {
            $scoreOrder = $b['match_score'] <=> $a['match_score'];
            return $scoreOrder !== 0 ? $scoreOrder : strcmp((string)$b['created_at'], (string)$a['created_at']);
        });

        return $scored;
    }

    public function computeAndStoreMatches($userId) {
        $userId = (int)$userId;
        $scoredJobs = $this->getScoredJobsForUser($userId);

        $del = $this->db->prepare('DELETE FROM job_matches WHERE user_id = ?');
        $del->bind_param('i', $userId);
        $del->execute();

        $ins = $this->db->prepare('INSERT INTO job_matches (user_id, job_id, match_score) VALUES (?,?,?)');
        foreach ($scoredJobs as $job) {
            $score = $job['match_score'];
            $jobId = (int) $job['id'];
            $ins->bind_param('iid', $userId, $jobId, $score);
            $ins->execute();
        }
    }
}