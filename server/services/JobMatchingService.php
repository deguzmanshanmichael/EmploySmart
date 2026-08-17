<?php
class JobMatchingService {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function computeScore($userId, $jobId) {
        $user = $this->getUser($userId);
        $job = $this->getJob($jobId);

        if (!$user || !$job || $job['approval_status'] !== 'approved') {
            return 0;
        }

        $userSkills = $this->getUserSkillIds($userId);
        $trainingSkills = $this->getUserTrainingSkillIds($userId);
        $jobSkills = $this->getJobSkillIds($jobId);

        $score = 0;

        // Skill matching (40% of total score)
        if (!empty($jobSkills)) {
            $matchedSkills = count(array_intersect($userSkills, $jobSkills));
            $skillCoverage = $matchedSkills / count($jobSkills);
            $score += $skillCoverage * 40;
        }

        // Training/Certification matching (15% of total score)
        if (!empty($jobSkills)) {
            $matchedTraining = count(array_intersect($trainingSkills, $jobSkills));
            $trainingCoverage = $matchedTraining / count($jobSkills);
            $score += $trainingCoverage * 15;
        }

        // Education level match (20% of total score)
        $educationMatch = $this->educationScore($user['education_level'], $job['education_required']);
        $score += $educationMatch * 20;

        // Experience level match (15% of total score)
        $experienceMatch = $this->experienceScore($user['employment_status'], $job['experience_level']);
        $score += $experienceMatch * 15;

        // Employment status preference (10% of total score)
        $employmentBonus = $this->employmentStatusBonus($user['employment_status'], $job['job_type'] ?? '');
        $score += $employmentBonus * 10;

        return round(min(100, max(0, $score)), 2);
    }

    private function employmentStatusBonus($userStatus, $jobType) {
        // Give preference to full-time for employed users, flexible for others
        $bonusMap = [
            'employed' => ['full_time' => 1, 'part_time' => 0.5, 'contract' => 0.6],
            'unemployed' => ['full_time' => 1, 'part_time' => 0.8, 'contract' => 0.9],
            'student' => ['part_time' => 1, 'full_time' => 0.6, 'contract' => 0.7],
            'self_employed' => ['contract' => 1, 'full_time' => 0.5, 'part_time' => 0.8],
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
        if (!$jobEdu) {
            return 1;
        }

        // Normalize user education level
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
        } elseif (stripos($jobEduLower, 'high school') !== false || stripos($jobEduLower, 'senior high') !== false) {
            $jobLevel = 3;
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

        $expLevelLower = strtolower($expLevel);
        $empStatusLower = strtolower($empStatus);

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

    public function getTopMatchesForUser($userId, $limit = 10) {
        $user = $this->getUser($userId);
        if (!$user) {
            return [];
        }

        $userSkills = $this->getUserSkillIds($userId);
        $trainingSkills = $this->getUserTrainingSkillIds($userId);

        $stmt = $this->db->prepare(
            'SELECT j.*, e.company_name, e.industry FROM jobs j JOIN employers e ON e.id = j.employer_id WHERE j.approval_status = "approved" AND (j.deadline IS NULL OR j.deadline >= CURDATE()) ORDER BY j.created_at DESC'
        );
        $stmt->execute();
        $jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $jobSkillRows = $this->db->query('SELECT js.job_id, js.skill_id, s.skill_name FROM job_skills js LEFT JOIN skills s ON s.id = js.skill_id ORDER BY js.job_id')
            ->fetch_all(MYSQLI_ASSOC);

        $jobSkillsByJob = [];
        foreach ($jobSkillRows as $row) {
            $jobId = (int) $row['job_id'];
            if (!isset($jobSkillsByJob[$jobId])) {
                $jobSkillsByJob[$jobId] = [];
            }
            $jobSkillsByJob[$jobId][] = [
                'id' => (int) $row['skill_id'],
                'name' => $row['skill_name'],
            ];
        }

        $scored = [];
        foreach ($jobs as $job) {
            $jobId = (int) $job['id'];
            $jobSkills = array_values(array_unique(array_map(fn($skill) => (int) $skill['id'], $jobSkillsByJob[$jobId] ?? [])));

            $score = 0;
            
            // Skill matching (40% of total score)
            if (!empty($jobSkills)) {
                $matchedSkills = count(array_intersect($userSkills, $jobSkills));
                $skillCoverage = $matchedSkills / count($jobSkills);
                $score += $skillCoverage * 40;
            }

            // Training/Certification matching (15% of total score)
            if (!empty($jobSkills)) {
                $matchedTraining = count(array_intersect($trainingSkills, $jobSkills));
                $trainingCoverage = $matchedTraining / count($jobSkills);
                $score += $trainingCoverage * 15;
            }

            // Education level match (20% of total score)
            $educationMatch = $this->educationScore($user['education_level'], $job['education_required']);
            $score += $educationMatch * 20;

            // Experience level match (15% of total score)
            $experienceMatch = $this->experienceScore($user['employment_status'], $job['experience_level']);
            $score += $experienceMatch * 15;

            // Employment status preference (10% of total score)
            $employmentBonus = $this->employmentStatusBonus($user['employment_status'], $job['job_type'] ?? '');
            $score += $employmentBonus * 10;

            if ($score > 0) {
                $job['skills'] = array_values(array_filter(array_map(function ($skill) {
                    return $skill['name'] ? ['skill_name' => $skill['name']] : null;
                }, $jobSkillsByJob[$jobId] ?? [])));
                $job['match_score'] = round(min(100, max(0, $score)), 2);
                $scored[] = $job;
            }
        }

        usort($scored, function ($a, $b) {
            return $b['match_score'] <=> $a['match_score'];
        });

        return array_slice($scored, 0, $limit);
    }

    public function computeAndStoreMatches($userId) {
        $user = $this->getUser($userId);
        if (!$user) {
            return;
        }

        $stmt = $this->db->prepare('SELECT id FROM jobs WHERE approval_status = "approved"');
        $stmt->execute();
        $jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $del = $this->db->prepare('DELETE FROM job_matches WHERE user_id = ?');
        $del->bind_param('i', $userId);
        $del->execute();

        $ins = $this->db->prepare('INSERT INTO job_matches (user_id, job_id, match_score) VALUES (?,?,?)');
        foreach ($jobs as $job) {
            $score = $this->computeScore($userId, (int) $job['id']);
            $jobId = (int) $job['id'];
            $ins->bind_param('iid', $userId, $jobId, $score);
            $ins->execute();
        }
    }
}