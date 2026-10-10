<?php
require_once __DIR__ . '/SkillNormalizer.php';

/**
 * Resume-driven job recommendations.
 *
 * Hybrid scoring over real, currently open job postings:
 *   skills (normalized + transferable)  35
 *   experience (role relevance + years) 25
 *   education (level + field)           15
 *   credentials (licenses/certificates) 10
 *   text similarity (TF-IDF cosine)     10
 *   location                             5
 * Components a job gives no evidence for are left out of the weighting rather than
 * being counted as a match. Every statement in the explanation comes from data that
 * exists in the confirmed resume and the job posting.
 */
class ResumeMatchingService {

    private const WEIGHTS = ['skills' => 35, 'experience' => 25, 'education' => 15, 'credentials' => 10, 'similarity' => 10, 'location' => 5];
    private const LEVELS = ['no_formal_education' => 0, 'elementary' => 1, 'high_school' => 2, 'senior_high' => 3, 'vocational' => 4, 'college' => 5, 'postgraduate' => 6];
    private const LEVEL_LABELS = ['no_formal_education' => 'no formal education', 'elementary' => 'elementary', 'high_school' => 'high school', 'senior_high' => 'senior high school', 'vocational' => 'vocational/technical', 'college' => 'college degree', 'postgraduate' => 'postgraduate degree'];
    private const STOPWORDS = ['the', 'and', 'for', 'with', 'from', 'that', 'this', 'are', 'was', 'were', 'will', 'have', 'has', 'our', 'your', 'you', 'their', 'into', 'able', 'must', 'should', 'can', 'all', 'any', 'per', 'its', 'not', 'but', 'who', 'job', 'work', 'years', 'year', 'experience', 'skills', 'skill', 'required', 'preferred', 'least', 'plus', 'good', 'strong', 'excellent', 'knowledge', 'ability', 'including', 'responsible', 'various', 'other', 'also', 'well', 'such', 'more', 'than', 'within', 'using', 'used', 'new', 'etc', 'applicant', 'applicants', 'candidate', 'candidates', 'position', 'role', 'company'];
    private const CREDENTIAL_TERMS = [
        'licensed' => '/\b(licensed|license|licensure|board (?:passer|exam)|prc)\b/i',
        'nc' => '/\bnc\s?(i{1,3}|iv)\b|national certificate|tesda certif/i',
        'driver' => '/driver\'?s?\s+licen[cs]e|professional driver|valid licen[cs]e/i',
        'civil_service' => '/civil service/i',
        'certified' => '/\bcertif(ied|ication|icate)\b/i',
    ];

    private mysqli $db;

    public function __construct(mysqli $db) {
        $this->db = $db;
    }

    /**
     * @param array $resume confirmed resume data (see ResumeProfileController::confirm)
     */
    public function recommend(int $userId, array $resume, int $limit = 10): array {
        $jobs = $this->openJobs($userId);
        if (!$jobs) return [];

        $profile = $this->resumeProfile($resume);
        $docs = [];
        foreach ($jobs as $job) {
            $docs[$job['id']] = $this->tokens($job['title'] . ' ' . $job['title'] . ' ' . $job['requirements'] . ' ' . $job['description'] . ' ' . $job['industry']);
        }
        $idf = $this->idf(array_merge(array_values($docs), [$profile['tokens']]));
        $resumeVector = $this->vector($profile['tokens'], $idf);

        $results = [];
        foreach ($jobs as $job) {
            $match = $this->scoreJob($job, $profile, $this->cosine($resumeVector, $this->vector($docs[$job['id']], $idf)));
            if ($match !== null) $results[] = $match;
        }
        usort($results, fn($a, $b) => $b['match_score'] <=> $a['match_score'] ?: strcmp((string)$b['created_at'], (string)$a['created_at']));
        return array_slice($results, 0, max(1, min(50, $limit)));
    }

    /** Approved, not archived, deadline not passed, and not already applied to. */
    private function openJobs(int $userId): array {
        $stmt = $this->db->prepare(
            "SELECT j.id, j.title, j.description, j.requirements, j.location, j.salary_range, j.vacancies, j.deadline,
                    j.job_type, j.experience_level, j.education_required, j.created_at,
                    COALESCE(e.company_name, 'Employer') AS company_name, e.industry
             FROM jobs j
             LEFT JOIN employers e ON e.id = j.employer_id
             WHERE j.approval_status = 'approved' AND COALESCE(j.archived, 0) = 0
               AND (j.deadline IS NULL OR j.deadline >= CURDATE())
               AND NOT EXISTS (SELECT 1 FROM applications a WHERE a.job_id = j.id AND a.user_id = ?)
             ORDER BY j.created_at DESC
             LIMIT 500"
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $jobs = [];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $row['skills'] = [];
            foreach (['title', 'description', 'requirements', 'location', 'industry'] as $k) $row[$k] = (string)($row[$k] ?? '');
            $jobs[(int)$row['id']] = $row;
        }
        if (!$jobs) return [];
        $ids = implode(',', array_map('intval', array_keys($jobs)));
        $res = $this->db->query("SELECT js.job_id, s.id, s.skill_name FROM job_skills js JOIN skills s ON s.id = js.skill_id WHERE js.job_id IN ($ids)");
        while ($res && ($r = $res->fetch_assoc())) {
            $jobs[(int)$r['job_id']]['skills'][] = ['id' => (int)$r['id'], 'skill_name' => $r['skill_name']];
        }
        return $jobs;
    }

    // ─── Resume side ────────────────────────────────────────────────────────

    private function resumeProfile(array $resume): array {
        $skills = [];
        foreach ((array)($resume['skills'] ?? []) as $s) {
            $name = is_array($s) ? (string)($s['name'] ?? '') : (string)$s;
            if ($name === '') continue;
            $skills[SkillNormalizer::key($name)] = $name;
            if (is_array($s) && !empty($s['catalog_skill_name'])) {
                $skills[SkillNormalizer::key($s['catalog_skill_name'])] = $skills[SkillNormalizer::key($s['catalog_skill_name'])] ?? $name;
            }
        }
        $experience = (array)($resume['experience'] ?? []);
        $credentials = [];
        foreach (array_merge((array)($resume['certifications'] ?? []), (array)($resume['trainings'] ?? [])) as $c) {
            $credentials[] = is_array($c) ? (string)($c['name'] ?? '') : (string)$c;
        }
        $education = (array)($resume['education'] ?? []);
        $level = $resume['education_level'] ?? null;
        $text = implode(' ', array_merge(
            array_values($skills),
            array_map(fn($e) => ($e['title'] ?? '') . ' ' . ($e['title'] ?? '') . ' ' . implode(' ', (array)($e['responsibilities'] ?? [])), $experience),
            array_map(fn($e) => ($e['degree'] ?? '') . ' ' . ($e['field_of_study'] ?? ''), $education),
            $credentials,
            [(string)($resume['objective'] ?? '')]
        ));
        return [
            'skills' => $skills,
            'experience' => $experience,
            'months' => isset($resume['total_experience_months']) ? (int)$resume['total_experience_months'] : null,
            'education' => $education,
            'level' => $level && isset(self::LEVELS[$level]) ? $level : null,
            'credentials' => array_values(array_filter($credentials)),
            'location' => mb_strtolower(trim(($resume['city'] ?? '') . ' ' . ($resume['province'] ?? '') . ' ' . ($resume['location'] ?? ''))),
            'tokens' => $this->tokens($text),
        ];
    }

    // ─── Scoring ───────────────────────────────────────────────────────────

    private function scoreJob(array $job, array $p, float $similarity): ?array {
        $components = [];
        $reasons = [];
        $gaps = [];
        $jobText = $job['title'] . "\n" . $job['requirements'] . "\n" . $job['description'];

        // Skills: tagged job skills plus skills named in the posting text.
        $required = [];
        foreach ($job['skills'] as $s) $required[SkillNormalizer::key($s['skill_name'])] = $s['skill_name'];
        foreach (SkillNormalizer::findInText($job['title'] . "\n" . $job['requirements']) as $canonical => $alias) {
            $required[SkillNormalizer::key($canonical)] = $required[SkillNormalizer::key($canonical)] ?? $canonical;
        }
        if (!$required) {
            foreach (SkillNormalizer::findInText($job['description']) as $canonical => $alias) {
                $required[SkillNormalizer::key($canonical)] = $canonical;
            }
        }
        $matched = []; $matchedText = []; $transferable = []; $missing = [];
        foreach ($required as $key => $label) {
            if (isset($p['skills'][$key])) {
                $matched[] = $p['skills'][$key];
                $matchedText[] = SkillNormalizer::key($p['skills'][$key]) === SkillNormalizer::key($label) ? $p['skills'][$key] : $label . ' (your ' . $p['skills'][$key] . ')';
                continue;
            }
            $related = null;
            foreach ($p['skills'] as $rk => $resumeSkill) {
                if (SkillNormalizer::related($resumeSkill, $label)) { $related = $resumeSkill; break; }
            }
            if ($related !== null) { $transferable[] = ['resume_skill' => $related, 'job_skill' => $label]; continue; }
            $missing[] = $label;
        }
        if ($required) {
            $components['skills'] = (count($matched) + 0.5 * count($transferable)) / count($required);
            if ($matched) $reasons[] = 'Your resume shows ' . count($matched) . ' of the ' . count($required) . ' skills this job asks for: ' . $this->listText($matchedText) . '.';
            if ($transferable) $reasons[] = 'Related experience that may transfer: ' . $this->listText(array_map(fn($t) => $t['resume_skill'] . ' (for ' . $t['job_skill'] . ')', $transferable)) . '.';
            foreach ($missing as $m) $gaps[] = ['type' => 'skill', 'label' => $m, 'detail' => 'Not found in your resume'];
        }

        // Experience: related roles + years.
        $bestTitle = 0.0; $bestRole = null;
        foreach ($p['experience'] as $e) {
            $rel = $this->titleRelevance((string)($e['title'] ?? ''), $job['title']);
            if ($rel > $bestTitle) { $bestTitle = $rel; $bestRole = $e; }
        }
        $requiredYears = $this->requiredYears($job['requirements'] . ' ' . $job['description'], $job['experience_level']);
        $years = $p['months'] !== null ? $p['months'] / 12 : null;
        if ($requiredYears === 0.0) {
            $yearsScore = 1.0;
        } elseif ($years === null) {
            $yearsScore = $p['experience'] ? 0.4 : 0.0;
            $gaps[] = ['type' => 'experience', 'label' => 'Years of experience', 'detail' => "This job asks for about {$this->num($requiredYears)}+ year(s); the dates in your resume do not show how long you have worked"];
        } else {
            $yearsScore = min(1.0, $years / $requiredYears);
            if ($years + 0.05 < $requiredYears) {
                $gaps[] = ['type' => 'experience', 'label' => 'Years of experience', 'detail' => "This job asks for about {$this->num($requiredYears)}+ year(s); your resume shows about {$this->num($years)}"];
            } else {
                $reasons[] = "Your resume shows about {$this->num($years)} year(s) of work experience, meeting the {$this->num($requiredYears)}+ year(s) this job asks for.";
            }
        }
        $components['experience'] = 0.6 * $bestTitle + 0.4 * $yearsScore;
        if ($bestRole !== null && $bestTitle >= 0.5) {
            $where = !empty($bestRole['company']) ? ' at ' . $bestRole['company'] : '';
            $relation = $bestTitle >= 0.8 ? 'is closely related to' : 'is in a related field to';
            $reasons[] = 'Your experience as ' . $bestRole['title'] . $where . ' ' . $relation . ' this ' . $job['title'] . ' role.';
        }

        // Education: level and field of study.
        $requiredLevel = $this->requiredLevel((string)$job['education_required'], $job['requirements']);
        if ($requiredLevel !== null) {
            $have = $p['level'] !== null ? self::LEVELS[$p['level']] : null;
            $need = self::LEVELS[$requiredLevel];
            if ($have === null) {
                $edu = 0.3;
                $gaps[] = ['type' => 'education', 'label' => ucfirst(self::LEVEL_LABELS[$requiredLevel]), 'detail' => 'Required education could not be verified from your resume'];
            } else {
                $edu = $have >= $need ? 1.0 : ($have === $need - 1 ? 0.6 : ($have === $need - 2 ? 0.3 : 0.0));
                if ($have >= $need) {
                    $reasons[] = 'Your ' . self::LEVEL_LABELS[$p['level']] . ' meets the ' . self::LEVEL_LABELS[$requiredLevel] . ' requirement.';
                } else {
                    $gaps[] = ['type' => 'education', 'label' => ucfirst(self::LEVEL_LABELS[$requiredLevel]), 'detail' => 'Your resume shows ' . self::LEVEL_LABELS[$p['level']]];
                }
            }
            $field = $this->requiredField($job['requirements']);
            if ($field !== null) {
                $fieldMatch = $this->educationFieldMatches($p['education'], $field);
                if ($fieldMatch !== null) {
                    $reasons[] = 'Your studies in ' . $fieldMatch . ' match the field this job asks for.';
                } else {
                    $edu *= 0.6;
                    $gaps[] = ['type' => 'education', 'label' => 'Degree in ' . $field, 'detail' => 'No matching field of study found in your resume'];
                }
            }
            $components['education'] = $edu;
        }

        // Credentials: licenses, national certificates, eligibility.
        $mandatoryMissing = false;
        $neededCreds = [];
        $clauses = preg_split('/[,;\n]|\.\s/u', $job['requirements']);
        foreach (self::CREDENTIAL_TERMS as $key => $re) {
            // A driver's license is not a professional license; "certified" only counts when nothing more specific is asked.
            if ($key === 'licensed' && isset($neededCreds['driver'])) continue;
            if ($key === 'certified' && $neededCreds) continue;
            foreach ($clauses as $clause) {
                if (preg_match($re, $clause, $m)) {
                    if ($key === 'licensed' && preg_match(self::CREDENTIAL_TERMS['driver'], $clause)) continue;
                    $neededCreds[$key] = ['label' => trim($m[0]), 'clause' => trim($clause)];
                    break;
                }
            }
        }
        if ($neededCreds) {
            $have = 0;
            foreach ($neededCreds as $key => $need) {
                $label = $need['label'];
                $found = null;
                foreach ($p['credentials'] as $c) {
                    if (preg_match(self::CREDENTIAL_TERMS[$key], $c) && $this->sameCredentialField($need['clause'], $c)) { $found = $c; break; }
                }
                if ($found !== null) {
                    $have++;
                    $reasons[] = 'Your resume lists "' . $found . '", relevant to the "' . $label . '" requirement.';
                } else {
                    if ($key !== 'certified') $mandatoryMissing = true;
                    $gaps[] = ['type' => 'credential', 'label' => ucfirst($label), 'detail' => 'Not found in your resume; required credentials must be verified by the employer'];
                }
            }
            $components['credentials'] = $have / count($neededCreds);
        }

        $components['similarity'] = min(1.0, $similarity / 0.35);

        if ($p['location'] !== '' && $job['location'] !== '') {
            $components['location'] = $this->locationScore($p['location'], mb_strtolower($job['location']));
            if ($components['location'] >= 0.99) $reasons[] = 'This job is located in ' . $job['location'] . ', the same area as the address on your resume.';
        }

        $weighted = 0.0; $weights = 0.0;
        foreach ($components as $name => $value) {
            $weighted += self::WEIGHTS[$name] * $value;
            $weights += self::WEIGHTS[$name];
        }
        $score = $weights > 0 ? ($weighted / $weights) * 100 : 0;
        // Without verified mandatory credentials or education the estimate stays modest.
        if ($mandatoryMissing) $score = min($score, 60);
        if (isset($components['education']) && $components['education'] < 0.3) $score = min($score, 50);
        $score = round($score, 1);

        $evidence = count($matched) + count($transferable) > 0 || $bestTitle >= 0.5 || $similarity >= 0.12;
        if ($score < 35 || !$evidence) return null;

        return [
            'job_id' => (int)$job['id'],
            'id' => (int)$job['id'],
            'title' => $job['title'],
            'company_name' => $job['company_name'],
            'industry' => $job['industry'] ?: null,
            'location' => $job['location'] ?: null,
            'job_type' => $job['job_type'],
            'experience_level' => $job['experience_level'],
            'education_required' => $job['education_required'],
            'salary_range' => $job['salary_range'],
            'vacancies' => $job['vacancies'],
            'deadline' => $job['deadline'],
            'description' => $job['description'],
            'requirements' => $job['requirements'],
            'skills' => $job['skills'],
            'created_at' => $job['created_at'],
            'summary' => $this->summary($job['description'] ?: $job['requirements']),
            'match_score' => $score,
            'matched_skills' => array_values(array_unique($matched)),
            'transferable_skills' => $transferable,
            'missing_qualifications' => $gaps,
            'explanation' => $reasons ? implode(' ', $reasons) : 'Your resume content is similar to the duties and requirements described in this posting.',
            'score_breakdown' => array_map(fn($v) => round($v * 100), $components),
        ];
    }

    /**
     * A license or certificate only counts for the field it names: a PRC license for
     * engineering does not satisfy "Licensed Registered Nurse".
     */
    private function sameCredentialField(string $jobClause, string $credential): bool {
        $generic = ['licensed', 'license', 'licence', 'licensure', 'valid', 'board', 'passer', 'exam', 'examination', 'prc', 'professional', 'certified', 'certification', 'certificate', 'national', 'required', 'preferred', 'must', 'holder', 'with', 'have', 'and', 'the', 'for', 'or', 'of', 'in', 'a', 'an', 'nc', 'ii', 'iii', 'iv', 'tesda'];
        $tokens = fn($s) => array_values(array_diff(array_unique(array_map(fn($t) => $this->stem($t), preg_split('/[^\p{L}]+/u', mb_strtolower($s), -1, PREG_SPLIT_NO_EMPTY))), array_map(fn($t) => $this->stem($t), $generic)));
        $need = array_filter($tokens($jobClause), fn($t) => mb_strlen($t) > 2);
        if (!$need) return true; // e.g. "valid license": any license of that kind is relevant evidence
        return (bool)array_intersect($need, $tokens($credential));
    }

    private function titleRelevance(string $resumeTitle, string $jobTitle): float {
        $a = $this->titleTokens($resumeTitle);
        $b = $this->titleTokens($jobTitle);
        if (!$a || !$b) return 0.0;
        $common = count(array_intersect($a, $b));
        $score = $common / count($b);
        // Same skill family (e.g. Cashier vs Sales Associate) counts as partially related.
        if ($score < 0.5) {
            foreach (SkillNormalizer::findInText($resumeTitle) as $ra => $x) {
                foreach (SkillNormalizer::findInText($jobTitle) as $ja => $y) {
                    if ($ra === $ja) return max($score, 0.8);
                    if (SkillNormalizer::related($ra, $ja)) $score = max($score, 0.5);
                }
            }
        }
        return min(1.0, $score);
    }

    private function titleTokens(string $title): array {
        $title = mb_strtolower($title);
        $title = str_replace(['front-end', 'frontend', 'back-end', 'backend', 'full-stack', 'fullstack', 'sr.', 'jr.'], ['front end', 'front end', 'back end', 'back end', 'full stack', 'full stack', 'senior', 'junior'], $title);
        $generic = ['senior', 'junior', 'jr', 'sr', 'i', 'ii', 'iii', 'lead', 'head', 'part', 'time', 'full', 'trainee', 'intern', 'assistant', 'associate', 'staff', 'level', 'entry', 'of', 'and', 'the', 'a'];
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $title, -1, PREG_SPLIT_NO_EMPTY);
        $tokens = array_map(fn($t) => $this->stem($t), array_filter($tokens, fn($t) => !in_array($t, $generic, true) && mb_strlen($t) > 1));
        return array_values(array_unique($tokens));
    }

    private function requiredYears(string $text, ?string $level): float {
        if (preg_match_all('/(\d{1,2})\s*(?:\+|-\s*\d{1,2}|to\s*\d{1,2})?\s*\+?\s*(?:years?|yrs?)/i', $text, $m)) {
            $values = array_map('intval', $m[1]);
            $values = array_filter($values, fn($v) => $v > 0 && $v <= 20);
            if ($values) return (float)min($values);
        }
        return match ($level) { 'senior' => 5.0, 'mid' => 2.0, default => 0.0 };
    }

    private function requiredLevel(string $educationRequired, string $requirements): ?string {
        $text = mb_strtolower(str_replace('_', ' ', $educationRequired . ' ' . $requirements));
        if (trim(mb_strtolower($educationRequired)) === '' && !preg_match('/degree|diploma|graduate|bachelor|college|high school|vocational/', $text)) return null;
        if (preg_match('/\b(any|not required|none|no requirement)\b/', mb_strtolower($educationRequired))) return null;
        if (preg_match('/postgraduate|master|phd|doctor/', $text)) return 'postgraduate';
        if (preg_match('/\bcollege\b|bachelor|university|degree holder|\bbs\b|graduate of a 4/', $text)) return 'college';
        if (preg_match('/associate\'?s? degree|vocational|tesda|technical/', $text)) return 'vocational';
        if (preg_match('/senior high/', $text)) return 'senior_high';
        if (preg_match('/high school|secondary/', $text)) return 'high_school';
        if (preg_match('/elementary/', $text)) return 'elementary';
        return null;
    }

    private function requiredField(string $requirements): ?string {
        if (preg_match('/(?:degree|graduate|diploma|course)\s+(?:in|of)\s+([A-Za-z][A-Za-z\/&, ]{2,60}?)(?:\s+or\b|[,.;\n]|$)/i', $requirements, $m)) {
            $field = trim($m[1]);
            if (!preg_match('/^(any|related|relevant|equivalent)/i', $field)) return $field;
        }
        return null;
    }

    private function educationFieldMatches(array $education, string $field): ?string {
        $aliases = ['cs' => 'computer science', 'it' => 'information technology', 'is' => 'information systems', 'hrm' => 'hotel and restaurant management', 'ba' => 'business administration'];
        $wanted = [];
        foreach (preg_split('/\s*(?:\/|,|\bor\b|&|\band\b)\s*/i', mb_strtolower($field)) as $part) {
            $part = trim($part);
            if ($part === '') continue;
            $wanted[] = $aliases[$part] ?? $part;
        }
        foreach ($education as $e) {
            $have = mb_strtolower(($e['degree'] ?? '') . ' ' . ($e['field_of_study'] ?? ''));
            foreach ($wanted as $w) {
                $wTokens = array_filter(explode(' ', $w), fn($t) => mb_strlen($t) > 2);
                if ($wTokens && count(array_filter($wTokens, fn($t) => str_contains($have, $this->stem($t)))) === count($wTokens)) {
                    return $e['field_of_study'] ?: ($e['degree'] ?? $w);
                }
            }
            // Computing degrees are interchangeable for "CS/IT" style requirements.
            if (preg_grep('/computer|information/', $wanted) && preg_match('/computer|information tech|information sys|\bit\b|software/', $have)) {
                return $e['field_of_study'] ?: $e['degree'];
            }
        }
        return null;
    }

    private function locationScore(string $resumeLocation, string $jobLocation): float {
        $jobParts = array_filter(array_map('trim', preg_split('/,/', $jobLocation)));
        if (preg_match('/remote|work from home|anywhere/', $jobLocation)) return 1.0;
        $city = $jobParts[0] ?? '';
        if ($city !== '' && str_contains($resumeLocation, preg_replace('/\s*city$/', '', $city))) return 1.0;
        foreach (array_slice($jobParts, 1) as $part) {
            if ($part !== '' && str_contains($resumeLocation, $part)) return 0.7;
        }
        return 0.3;
    }

    // ─── Text similarity ───────────────────────────────────────────────────

    private function tokens(string $text): array {
        $text = mb_strtolower(strip_tags($text));
        $words = preg_split('/[^\p{L}\p{N}+#]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $out = [];
        foreach ($words as $w) {
            if (mb_strlen($w) < 3 || in_array($w, self::STOPWORDS, true) || is_numeric($w)) continue;
            $out[] = $this->stem($w);
        }
        return $out;
    }

    private function stem(string $w): string {
        foreach (['ational' => 'ate', 'ization' => 'ize', 'ations' => 'ate', 'ation' => 'ate', 'ments' => '', 'ment' => '', 'ings' => '', 'ing' => '', 'ers' => 'er', 'ies' => 'y', 'ed' => '', 'es' => '', 's' => ''] as $suffix => $rep) {
            if (mb_strlen($w) > mb_strlen($suffix) + 3 && str_ends_with($w, $suffix)) {
                return mb_substr($w, 0, -mb_strlen($suffix)) . $rep;
            }
        }
        return $w;
    }

    private function idf(array $documents): array {
        $df = [];
        foreach ($documents as $doc) {
            foreach (array_unique($doc) as $t) $df[$t] = ($df[$t] ?? 0) + 1;
        }
        $n = count($documents);
        return array_map(fn($d) => log(($n + 1) / ($d + 0.5)), $df);
    }

    private function vector(array $tokens, array $idf): array {
        $tf = array_count_values($tokens);
        $v = [];
        foreach ($tf as $t => $c) $v[$t] = (1 + log($c)) * ($idf[$t] ?? 0);
        return $v;
    }

    private function cosine(array $a, array $b): float {
        $dot = 0.0;
        foreach ($a as $t => $w) if (isset($b[$t])) $dot += $w * $b[$t];
        $na = sqrt(array_sum(array_map(fn($x) => $x * $x, $a)));
        $nb = sqrt(array_sum(array_map(fn($x) => $x * $x, $b)));
        return ($na > 0 && $nb > 0) ? $dot / ($na * $nb) : 0.0;
    }

    // ─── Formatting ────────────────────────────────────────────────────────

    private function listText(array $items): string {
        $items = array_values(array_unique($items));
        if (count($items) <= 1) return implode('', $items);
        if (count($items) > 6) return implode(', ', array_slice($items, 0, 6)) . ' and ' . (count($items) - 6) . ' more';
        return implode(', ', array_slice($items, 0, -1)) . ' and ' . end($items);
    }

    private function num(float $n): string {
        return rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.');
    }

    private function summary(string $text): string {
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        return mb_strlen($text) > 220 ? rtrim(mb_substr($text, 0, 217)) . '...' : $text;
    }
}
