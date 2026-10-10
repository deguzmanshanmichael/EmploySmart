<?php
require_once __DIR__ . '/SkillNormalizer.php';
require_once __DIR__ . '/ResumeTextExtractor.php';

/**
 * Template-independent resume parser.
 *
 * Works from content and context instead of fixed positions: headings are recognised
 * from a broad synonym dictionary (any order, any case, letter-spaced or with colons),
 * and text outside recognised sections is classified by what it contains (degrees,
 * date ranges, job titles, contact details). Every extracted value carries a confidence
 * level and the evidence it came from; nothing is inferred that is not in the text.
 */
class ResumeParser {

    private const SECTION_KEYWORDS = [
        'personal' => ['personal information', 'personal data', 'personal details', 'personal profile', 'personal background', 'contact information', 'contact details', 'contact info', 'contact me', 'contacts', 'contact'],
        'objective' => ['career objective', 'job objective', 'objective', 'objectives', 'professional summary', 'career summary', 'executive summary', 'summary of qualifications', 'qualifications summary', 'summary', 'professional profile', 'career profile', 'profile', 'about me', 'about', 'personal statement', 'career goal', 'career goals', 'introduction', 'overview', 'who i am'],
        'education' => ['educational background', 'educational attainment', 'educational history', 'educational qualifications', 'education and training', 'education & training', 'academic background', 'academic history', 'academic qualifications', 'academic profile', 'academic record', 'scholastic record', 'scholastic background', 'schools attended', 'education', 'academics', 'studies', 'tertiary education'],
        'experience' => ['work experience', 'working experience', 'work experiences', 'professional experience', 'employment history', 'employment record', 'employment background', 'employment', 'work history', 'career history', 'job history', 'relevant experience', 'related experience', 'work background', 'professional background', 'positions held', 'experience', 'experiences', 'internship experience', 'internships', 'internship', 'on-the-job training', 'on the job training', 'ojt', 'practicum', 'career experience', 'professional history'],
        'skills' => ['technical skills', 'key skills', 'core skills', 'core competencies', 'competencies', 'areas of expertise', 'area of expertise', 'expertise', 'skills and abilities', 'skills & abilities', 'skills and competencies', 'skill set', 'skillset', 'skills', 'skill', 'abilities', 'proficiencies', 'technical proficiency', 'computer skills', 'soft skills', 'hard skills', 'strengths', 'special skills', 'other skills', 'tools and technologies', 'technologies', 'tech stack', 'qualifications', 'key qualifications', 'capabilities', 'knowledge'],
        'certifications' => ['licenses and certifications', 'licenses & certifications', 'certifications and licenses', 'certifications', 'certification', 'certificates', 'licenses', 'license', 'licensure', 'eligibility', 'eligibilities', 'civil service eligibility', 'credentials', 'accreditations', 'professional licenses', 'national certificates'],
        'trainings' => ['trainings attended', 'trainings and seminars', 'seminars and trainings', 'seminars attended', 'seminars and workshops', 'trainings', 'training', 'seminars', 'seminar', 'workshops', 'conferences', 'courses', 'courses taken', 'continuing education', 'professional development', 'relevant coursework', 'coursework'],
        'projects' => ['projects', 'project', 'academic projects', 'personal projects', 'capstone project', 'capstone', 'thesis', 'research', 'portfolio'],
        'awards' => ['awards and honors', 'honors and awards', 'awards', 'award', 'honors', 'achievements', 'accomplishments', 'recognition', 'recognitions', 'distinctions', 'scholarships'],
        'languages' => ['languages', 'language', 'language proficiency', 'languages spoken', 'dialects'],
        'volunteer' => ['volunteer experience', 'volunteer work', 'volunteering', 'volunteer', 'community involvement', 'community service', 'civic involvement', 'extracurricular activities', 'extra-curricular activities', 'extracurricular', 'organizations', 'affiliations', 'memberships', 'organizational affiliations', 'leadership experience', 'activities'],
        'references' => ['character references', 'references', 'reference', 'referees'],
        'interests' => ['interests', 'hobbies', 'hobbies and interests'],
    ];

    private const TITLE_WORDS = ['developer', 'engineer', 'programmer', 'analyst', 'manager', 'supervisor', 'assistant', 'associate', 'clerk', 'cashier', 'staff', 'crew', 'officer', 'specialist', 'coordinator', 'technician', 'operator', 'driver', 'nurse', 'teacher', 'instructor', 'tutor', 'professor', 'accountant', 'bookkeeper', 'encoder', 'secretary', 'receptionist', 'agent', 'representative', 'consultant', 'designer', 'intern', 'trainee', 'apprentice', 'helper', 'laborer', 'labourer', 'carpenter', 'mason', 'electrician', 'plumber', 'welder', 'mechanic', 'cook', 'chef', 'baker', 'barista', 'server', 'waiter', 'waitress', 'bartender', 'housekeeper', 'caregiver', 'nanny', 'guard', 'merchandiser', 'promodiser', 'salesman', 'salesperson', 'saleslady', 'sales lady', 'inspector', 'administrator', 'head', 'lead', 'director', 'president', 'owner', 'proprietor', 'researcher', 'scientist', 'writer', 'editor', 'photographer', 'farmer', 'tailor', 'seamstress', 'dressmaker', 'cleaner', 'janitor', 'utility', 'messenger', 'rider', 'courier', 'dispatcher', 'storekeeper', 'stockman', 'warehouseman', 'checker', 'bagger', 'packer', 'foreman', 'aide', 'midwife', 'pharmacist', 'therapist', 'physician', 'dentist', 'architect', 'draftsman', 'surveyor', 'auditor', 'teller', 'collector', 'steward', 'executive', 'volunteer', 'freelancer', 'tester', 'support', 'admin', 'attendant', 'custodian', 'vendor', 'machinist', 'fabricator', 'painter', 'gardener', 'landscaper', 'stylist', 'cosmetologist', 'therapist', 'lineman', 'installer', 'estimator', 'planner', 'buyer', 'purchaser', 'controller', 'treasurer', 'auditor', 'paralegal', 'counselor', 'librarian', 'chief', 'leader', 'qa'];

    private const COMPANY_WORDS = ['inc', 'inc.', 'corp', 'corp.', 'corporation', 'company', 'co.', 'ltd', 'ltd.', 'llc', 'enterprises', 'enterprise', 'store', 'restaurant', 'hotel', 'resort', 'hospital', 'clinic', 'bank', 'group', 'solutions', 'services', 'systems', 'agency', 'office', 'department', 'municipal', 'municipality', 'government', 'school', 'university', 'college', 'trading', 'mart', 'mall', 'foods', 'industries', 'industry', 'international', 'technologies', 'technology', 'consulting', 'cooperative', 'foundation', 'association', 'center', 'centre', 'firm', 'factory', 'farm', 'supermarket', 'pharmacy', 'bakery', 'cafe', 'café', 'shop', 'sm', 'jollibee', 'mcdonald', 'puregold', 'robinsons', 'lgu', 'peso', 'dole', 'tesda', 'deped', 'barangay', 'philippines', 'phils', 'corporation'];

    private const PROVINCES = ['abra', 'agusan del norte', 'agusan del sur', 'aklan', 'albay', 'antique', 'apayao', 'aurora', 'basilan', 'bataan', 'batanes', 'batangas', 'benguet', 'biliran', 'bohol', 'bukidnon', 'bulacan', 'cagayan', 'camarines norte', 'camarines sur', 'camiguin', 'capiz', 'catanduanes', 'cavite', 'cebu', 'cotabato', 'davao de oro', 'davao del norte', 'davao del sur', 'davao occidental', 'davao oriental', 'dinagat islands', 'eastern samar', 'guimaras', 'ifugao', 'ilocos norte', 'ilocos sur', 'iloilo', 'isabela', 'kalinga', 'la union', 'laguna', 'lanao del norte', 'lanao del sur', 'leyte', 'maguindanao', 'marinduque', 'masbate', 'metro manila', 'misamis occidental', 'misamis oriental', 'mountain province', 'negros occidental', 'negros oriental', 'northern samar', 'nueva ecija', 'nueva vizcaya', 'occidental mindoro', 'oriental mindoro', 'palawan', 'pampanga', 'pangasinan', 'quezon', 'quirino', 'rizal', 'romblon', 'samar', 'sarangani', 'siquijor', 'sorsogon', 'south cotabato', 'southern leyte', 'sultan kudarat', 'sulu', 'surigao del norte', 'surigao del sur', 'tarlac', 'tawi-tawi', 'zambales', 'zamboanga del norte', 'zamboanga del sur', 'zamboanga sibugay', 'ncr'];

    private const LANGUAGES = ['english', 'filipino', 'tagalog', 'ilocano', 'ilokano', 'kapampangan', 'pampango', 'cebuano', 'bisaya', 'visayan', 'hiligaynon', 'ilonggo', 'waray', 'bicolano', 'bikol', 'pangasinan', 'pangasinense', 'chavacano', 'spanish', 'japanese', 'korean', 'mandarin', 'chinese', 'cantonese', 'french', 'german', 'arabic', 'italian', 'portuguese', 'russian', 'hindi', 'indonesian', 'malay', 'thai', 'vietnamese'];

    private const MONTHS = 'jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:t(?:ember)?)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?';

    private array $lines = [];
    private array $warnings = [];
    private array $accountHints;

    /** @param array $accountHints optional ['first_name'=>..,'last_name'=>..,'email'=>..] used only to raise confidence */
    public function __construct(array $accountHints = []) {
        $this->accountHints = $accountHints;
    }

    public function parse(string $text): array {
        $this->warnings = [];
        $this->lines = $this->prepareLines($text);
        $sections = $this->segment($this->lines);
        $fullText = implode("\n", $this->lines);

        $personal = $this->extractPersonal($sections, $fullText);
        $education = $this->extractEducation($sections);
        $experience = $this->extractExperience($sections);
        $certifications = $this->extractCertifications($sections, $fullText);
        $trainings = $this->extractListSection($sections['trainings'] ?? [], 'training');
        $skills = $this->extractSkills($sections, $experience, $certifications, $trainings);
        $languages = $this->extractLanguages($sections, $fullText);

        $highest = $this->highestEducation($education);
        $totalMonths = $this->totalExperienceMonths($experience);

        return [
            'personal' => $personal,
            'objective' => $this->extractObjective($sections),
            'education' => $education,
            'highest_education_level' => $highest,
            'experience' => $experience,
            'total_experience_months' => $totalMonths,
            'employment_status_hint' => $this->employmentHint($experience),
            'skills' => $skills,
            'certifications' => $certifications,
            'trainings' => $trainings,
            'projects' => $this->extractListSection($sections['projects'] ?? [], 'project'),
            'awards' => $this->extractListSection($sections['awards'] ?? [], 'award'),
            'languages' => $languages,
            'volunteer' => $this->extractListSection($sections['volunteer'] ?? [], 'activity'),
            'sections_detected' => array_values(array_filter(array_keys($sections), fn($k) => $k !== 'header' && !empty($sections[$k]))),
            'warnings' => array_values(array_unique($this->warnings)),
        ];
    }

    // ─── Line preparation & segmentation ───────────────────────────────────

    private function prepareLines(string $text): array {
        $text = ResumeTextExtractor::cleanText($text);
        $out = [];
        foreach (preg_split("/\n/u", $text) as $line) {
            $line = trim($line);
            // Normalise bullet glyphs to a single marker.
            $line = preg_replace('/^(?:[•●▪◦‣➢➤►▶✓✔☑❖◆◇■□\-*·o»]|\d+[.)])\s+/u', '• ', $line);
            $line = preg_replace('/^[•●▪◦‣➢➤►▶✓✔❖◆■·]\s*/u', '• ', $line);
            // Letter-spaced headings: "E D U C A T I O N" → "EDUCATION".
            if (preg_match('/^(?:\p{L} ){3,}\p{L}$/u', $line)) {
                $line = str_replace(' ', '', $line);
            }
            // Inline bullets ("• a • b") become separate lines.
            if (substr_count($line, ' • ') >= 1) {
                foreach (preg_split('/\s+•\s+/u', $line) as $i => $part) {
                    if (trim($part) !== '') $out[] = ($i === 0 ? '' : '• ') . trim($part);
                }
                continue;
            }
            $out[] = $line;
        }
        return $out;
    }

    /** Identify the section a heading-like line introduces, or null if it is not a heading. */
    public function headingSection(string $line): ?array {
        $raw = trim(preg_replace('/^[•#=*_\-\s]+|[#=*_\-\s]+$/u', '', $line));
        if ($raw === '' || mb_strlen($raw) > 60) return null;
        $remainder = '';
        if (preg_match('/^([^:|]{2,45})[:|]\s*(.*)$/u', $raw, $m)) {
            $raw = trim($m[1]);
            $remainder = trim($m[2]);
        } elseif (preg_match('/^(\p{Lu}[\p{L}]+(?:\s(?:and|&|\p{Lu}[\p{L}]+))?)\s+(\S.*,.*)$/u', $raw, $m) && $this->headingSection($m[1]) !== null) {
            // "Skills HTML, CSS, JavaScript": capitalised heading word followed by a list.
            $raw = $m[1];
            $remainder = trim($m[2]);
        } elseif (preg_match('/[.!?]$/u', $raw) || preg_match('/\d{4}/', $raw)) {
            return null;
        }
        $norm = mb_strtolower(preg_replace('/[^\p{L}&\s\-]/u', ' ', $raw), 'UTF-8');
        $norm = trim(preg_replace('/\s+/', ' ', $norm));
        $words = $norm === '' ? 0 : count(explode(' ', $norm));
        if ($words === 0 || $words > 6) return null;

        $best = null;
        $bestLen = 0;
        $bestKeyword = '';
        foreach (self::SECTION_KEYWORDS as $section => $keywords) {
            foreach ($keywords as $keyword) {
                if ($norm === $keyword || (str_starts_with($norm, $keyword . ' ') && $words <= 5) || (str_ends_with($norm, ' ' . $keyword) && $words <= 4) || ($words <= 5 && str_contains($norm, ' ' . $keyword . ' '))) {
                    // Prefer the most specific keyword ("work experience" over "experience").
                    $len = mb_strlen($keyword) + ($norm === $keyword ? 100 : 0);
                    if ($len > $bestLen) { $best = $section; $bestLen = $len; $bestKeyword = $keyword; }
                }
            }
        }
        if ($best === null) return null;

        // Single generic words are only headings when they look like headings
        // (upper/title case, short line), to avoid treating prose as structure.
        $looksLikeHeading = $remainder !== '' || $raw === mb_strtoupper($raw, 'UTF-8') || preg_match('/^(\p{Lu}[\p{L}&\'\-]*\s*)+$/u', $raw) || $words <= 3;
        if (!$looksLikeHeading) return null;

        // In credential sections a qualified heading ("Driving license", "PRC License") names the item itself.
        $labelIsItem = $best === 'certifications' && $norm !== $bestKeyword && $words >= 2;
        return ['section' => $best, 'remainder' => $remainder, 'label' => $raw, 'label_is_item' => $labelIsItem];
    }

    private function segment(array $lines): array {
        $sections = ['header' => []];
        $current = 'header';
        $pendingLabel = null;
        foreach ($lines as $line) {
            if ($line === ResumeTextExtractor::COLUMN_BREAK) {
                // A new layout column starts; its leading lines are not part of the previous section.
                $current = 'header';
                $sections['header'][] = '';
                $pendingLabel = null;
                continue;
            }
            if ($line === '') {
                $sections[$current][] = '';
                continue;
            }
            $heading = $this->headingSection($line);
            if ($heading !== null) {
                $current = $heading['section'];
                $sections[$current] = $sections[$current] ?? [];
                // Separate repeated sections (e.g. two "Experience" blocks in different columns).
                if (!empty($sections[$current])) $sections[$current][] = '';
                if ($heading['remainder'] !== '') {
                    $sections[$current][] = $heading['label_is_item'] ? $heading['label'] . ': ' . $heading['remainder'] : $heading['remainder'];
                } elseif ($heading['label_is_item']) {
                    // e.g. "Driving license" followed by "Professional" on the next line.
                    $pendingLabel = $heading['label'];
                }
                continue;
            }
            if ($pendingLabel !== null) {
                $line = mb_strlen($line) <= 40 ? $pendingLabel . ': ' . $line : $pendingLabel;
                $pendingLabel = null;
            }
            $sections[$current][] = $line;
        }

        // Content-based rescue: lines in the header/unknown area that clearly describe
        // education or employment are moved to the right section.
        $rescued = [];
        foreach ($sections['header'] as $line) {
            if ($line !== '' && empty($sections['education']) && $this->educationLevelOf($line) !== null && $this->institutionOf($line) !== null) {
                $sections['education'][] = $line;
                continue;
            }
            $rescued[] = $line;
        }
        $sections['header'] = $rescued;
        return $sections;
    }

    // ─── Personal information ──────────────────────────────────────────────

    private function extractPersonal(array $sections, string $fullText): array {
        $result = [];
        $topLines = array_values(array_filter(array_merge($sections['header'], $sections['personal'] ?? []), fn($l) => $l !== ''));

        // Email (also "name @ gmail.com", "name[at]gmail[dot]com")
        $emailText = preg_replace(['/\s*(?:\[at\]|\(at\)|\s@\s|\s@|@\s)\s*/i', '/\s*(?:\[dot\]|\(dot\))\s*/i'], ['@', '.'], $fullText);
        if (preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9\-]+(?:\.[A-Z0-9\-]+)*\.[A-Z]{2,}/i', $emailText, $m)) {
            $emails = array_values(array_unique(array_map(fn($e) => rtrim(strtolower($e), '.'), $m[0])));
            $valid = array_values(array_filter($emails, fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
            if ($valid) {
                $result['email'] = $this->field($valid[0], count($valid) > 1 ? 'medium' : 'high', 'Email address found in resume');
                if (count($valid) > 1) $result['email']['alternatives'] = array_slice($valid, 1, 3);
            }
        }

        // Phone numbers (mobile, landline, international); a second number becomes the alternate phone.
        $phones = $this->extractPhones($fullText);
        if ($phones) {
            $result['phone'] = $this->field($phones[0]['value'], $phones[0]['confidence'], $phones[0]['evidence']);
            if (isset($phones[1])) {
                $result['alternate_phone'] = $this->field($phones[1]['value'], $phones[1]['confidence'] === 'high' ? 'medium' : $phones[1]['confidence'], $phones[1]['evidence']);
            }
        }

        // Name
        $name = $this->detectName($topLines, $result['email']['value'] ?? '');
        if ($name !== null) {
            $result['full_name'] = $name;
            $parts = $this->splitName($name['value']);
            foreach ($parts as $k => $v) {
                if ($v !== '') $result[$k] = $this->field($v, $name['confidence'], 'Derived from the name at the top of the resume');
            }
        }

        // Location / address
        $address = $this->extractAddress($topLines, $this->lines, $result['full_name']['value'] ?? '');
        if ($address !== null) {
            $result['location'] = $this->field($address['value'], $address['confidence'], $address['evidence']);
            [$city, $province, $zip] = $this->splitLocation($address['value']);
            $partConfidence = $address['confidence'] === 'high' ? 'medium' : 'low';
            if ($city) $result['city'] = $this->field($city, $this->isKnownPlace($city) ? 'medium' : $partConfidence, 'Derived from the address');
            if ($province) $result['province'] = $this->field($province, 'medium', 'Recognised province name in the address');
            if ($zip) $result['zip_code'] = $this->field($zip, $partConfidence, 'ZIP code in the address');
        }

        // Explicitly labelled personal data (common in Philippine resumes).
        $birth = $this->labelled($fullText, ['date of birth', 'birth date', 'birthdate', 'birthday', 'date of birth/age', 'born on', 'born', 'dob', 'd.o.b.'], true);
        if ($birth !== null && ($date = $this->parseFullDate($birth)) !== null) {
            $result['birth_date'] = $this->field($date, 'high', 'Labelled birth date');
        }
        $sex = $this->labelled($fullText, ['sex', 'gender']);
        if ($sex !== null && preg_match('/^\s*(male|female)\b/i', $sex, $m)) {
            $result['sex'] = $this->field(strtolower($m[1]), 'high', 'Labelled sex/gender');
        }
        $civil = $this->labelled($fullText, ['civil status', 'marital status', 'status']);
        if ($civil !== null && preg_match('/^\s*(single|married|widowed|widow|widower|separated)\b/i', $civil, $m)) {
            $value = strtolower($m[1]);
            $result['civil_status'] = $this->field(in_array($value, ['widow', 'widower'], true) ? 'widowed' : $value, 'high', 'Labelled civil status');
        }
        return $result;
    }

    private function detectName(array $topLines, string $email): ?array {
        $labelled = $this->labelled(implode("\n", $topLines), ['full name', 'name']);
        if ($labelled !== null && $this->isNameLike($labelled)) {
            return $this->field($this->tidyName($labelled), 'high', 'Labelled name');
        }
        $emailTokens = $email !== '' ? preg_split('/[^a-z]+/', strtolower(strstr($email, '@', true))) : [];
        $hintFirst = mb_strtolower((string)($this->accountHints['first_name'] ?? ''));
        $hintLast = mb_strtolower((string)($this->accountHints['last_name'] ?? ''));
        $best = null;
        $bestScore = 0;
        foreach (array_slice($topLines, 0, 12) as $i => $line) {
            // "Hello, I'm Cia Rodriguez" / "My name is ..." introductions.
            $line = preg_replace('/^(?:(?:hello|hi|hey)[,!.]?\s*)?(?:i[\'´’`]?m|i am|my name is|this is)\s+/iu', '', $line);
            foreach (preg_split('/\s*[|·]\s*/u', $line) as $candidate) {
                $candidate = trim($candidate, " ,");
                if (!$this->isNameLike($candidate)) continue;
                $lower = mb_strtolower($candidate);
                $score = 10 - $i;
                if ($candidate === mb_strtoupper($candidate) || preg_match('/^(\p{Lu}[\p{Ll}\'.\-]*\s+)+\p{Lu}[\p{Ll}\'.\-]*$/u', $candidate)) $score += 3;
                foreach ($emailTokens as $t) {
                    if (strlen($t) >= 3 && str_contains($this->ascii($lower), $t)) $score += 4;
                }
                if ($hintLast !== '' && str_contains($lower, $hintLast)) $score += 5;
                if ($hintFirst !== '' && str_contains($lower, $hintFirst)) $score += 3;
                if ($score > $bestScore) { $bestScore = $score; $best = $candidate; }
            }
        }
        if ($best === null) return null;
        $confidence = $bestScore >= 14 ? 'high' : ($bestScore >= 9 ? 'medium' : 'low');
        return $this->field($this->tidyName($best), $confidence, 'Name-like line near the top of the resume');
    }

    private function isNameLike(string $text): bool {
        $text = trim($text);
        if (mb_strlen($text) < 4 || mb_strlen($text) > 50) return false;
        if (preg_match('/[\d@:\/]|https?|www\./i', $text)) return false;
        $words = preg_split('/\s+/u', $text);
        if (count($words) < 2 || count($words) > 5) return false;
        if (!preg_match('/^[\p{L}][\p{L}\s.\'\-,]+$/u', $text)) return false;
        $lower = mb_strtolower($text);
        if ($this->headingSection($text) !== null) return false;
        foreach (self::TITLE_WORDS as $w) {
            if (preg_match('/\b' . preg_quote($w, '/') . 's?\b/u', $lower)) return false;
        }
        foreach (['resume', 'curriculum', 'vitae', 'street', 'city', 'province', 'barangay', 'university', 'college', 'school', 'philippines', 'nueva', 'manila', 'responsible', 'hardworking', 'skills'] as $w) {
            if (str_contains($lower, $w)) return false;
        }
        // Every word should start with a capital letter (allowing particles like "dela", "de", "y").
        foreach ($words as $w) {
            if (!preg_match('/^\p{Lu}/u', $w) && !in_array(mb_strtolower($w), ['de', 'dela', 'del', 'delos', 'la', 'los', 'y', 'van', 'von', 'da', 'dos', 'di'], true)) return false;
        }
        return true;
    }

    private function tidyName(string $name): string {
        $name = trim(preg_replace('/\s+/u', ' ', $name), " ,");
        if ($name === mb_strtoupper($name, 'UTF-8')) {
            $name = mb_convert_case(mb_strtolower($name, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
            $name = preg_replace_callback('/\b(Ii|Iii|Iv|Jr|Sr)\b/u', fn($m) => in_array($m[1], ['Jr', 'Sr'], true) ? $m[1] : mb_strtoupper($m[1]), $name);
        }
        return $name;
    }

    /** Split "Juan A. Dela Cruz Jr." into first/middle/last/suffix (Filipino surname particles aware). */
    public function splitName(string $name): array {
        $out = ['first_name' => '', 'middle_name' => '', 'last_name' => '', 'suffix' => ''];
        $name = trim($name);
        if (str_contains($name, ',')) {
            // "Dela Cruz, Juan A."
            [$last, $rest] = array_map('trim', explode(',', $name, 2));
            $restWords = preg_split('/\s+/u', $rest);
            $out['last_name'] = $last;
            $out['first_name'] = array_shift($restWords) ?? '';
            $out['middle_name'] = implode(' ', $restWords);
            return $out;
        }
        $words = preg_split('/\s+/u', $name);
        if (preg_match('/^(jr|sr|ii|iii|iv|v)\.?$/i', end($words))) {
            $out['suffix'] = array_pop($words);
        }
        if (count($words) < 2) { $out['first_name'] = implode(' ', $words); return $out; }
        $particles = ['de', 'dela', 'del', 'delos', 'de la', 'de los', 'san', 'santa', 'sta.', 'sto.', 'van', 'von', 'da', 'dos', 'di', 'la', 'los'];
        $lastStart = count($words) - 1;
        while ($lastStart > 1 && in_array(mb_strtolower($words[$lastStart - 1]), $particles, true)) {
            $lastStart--;
        }
        $out['last_name'] = implode(' ', array_slice($words, $lastStart));
        $before = array_slice($words, 0, $lastStart);
        if (count($before) >= 2 && preg_match('/^\p{Lu}\.?$/u', end($before))) {
            $out['middle_name'] = array_pop($before);
        }
        $out['first_name'] = implode(' ', $before);
        return $out;
    }

    /**
     * Find phone numbers in any common Philippine or international format.
     * @return array<int, array{value:string, confidence:string, evidence:string}>
     */
    private function extractPhones(string $text): array {
        $label = '(?:mobile|cell(?:ular)?|cellphone|cp|phone|tel(?:ephone)?|landline|contact|viber|whats\s*app|telefax|fax|hotline)';
        $reject = '/(?:sss|tin|gsis|philhealth|pag-?ibig|hdmf|umid|prc|license|licence|passport|id|zip|postal|account|acct|reference|ref)\s*(?:no\.?|number|#)?\s*[:\-#]?\s*$/i';
        $found = [];
        foreach (preg_split("/\n/u", $text) as $lineNo => $line) {
            if (!preg_match_all('/(?<![\w@.])(\(?\+?\(?\d[\d\s().\-]{5,24}\d)(?![\w@])/u', $line, $m, PREG_OFFSET_CAPTURE)) continue;
            foreach ($m[1] as [$raw, $offset]) {
                $before = mb_substr(substr($line, 0, $offset), -30);
                if (preg_match($reject, $before)) continue;
                $labelled = (bool)preg_match('/' . $label . '[^\d\n]{0,18}$/iu', $before);
                // A candidate may hold two numbers ("0917 123 4567 0918 765 4321"); split on wide gaps.
                foreach (preg_split('/\s{2,}|\s+(?=\+)/', trim($raw)) as $piece) {
                    $kind = $this->phoneKind($piece, $labelled);
                    if ($kind === null) continue;
                    $digits = preg_replace('/\D/', '', $piece);
                    $key = preg_replace('/^(?:63|0)/', '', $digits);
                    if (isset($found[$key])) continue;
                    $value = trim(preg_replace('/\s+/', ' ', $piece), " .-");
                    $found[$key] = [
                        'value' => $value,
                        'confidence' => $labelled || $kind === 'mobile' ? 'high' : 'medium',
                        'evidence' => ($labelled ? 'Labelled ' : '') . ['mobile' => 'mobile number', 'landline' => 'landline number', 'international' => 'phone number', 'local' => 'local phone number'][$kind] . ' found in resume',
                        'rank' => ($labelled ? 0 : 10) + ['mobile' => 0, 'landline' => 2, 'international' => 1, 'local' => 3][$kind] + $lineNo / 1000,
                    ];
                }
            }
        }
        uasort($found, fn($a, $b) => $a['rank'] <=> $b['rank']);
        return array_values(array_map(fn($p) => array_diff_key($p, ['rank' => 1]), $found));
    }

    private function phoneKind(string $raw, bool $labelled): ?string {
        $raw = trim($raw);
        $digits = preg_replace('/\D/', '', $raw);
        // Years, year ranges and dates are not phone numbers.
        if (preg_match('/^\(?(?:19|20)\d{2}\)?\s*[-–.\/]?\s*\(?(?:(?:19|20)\d{2})?\)?$/', $raw)) return null;
        if (preg_match('/^\d{1,2}[\/.\-]\d{1,2}[\/.\-](?:19|20)?\d{2}$/', $raw)) return null;
        if (preg_match('/^(?:63|0)?9\d{9}$/', $digits) && (str_starts_with($digits, '9') ? strlen($digits) === 10 : true)) return 'mobile';
        if (preg_match('/^0[2-8]\d{7,9}$/', $digits) || preg_match('/^63[2-8]\d{7,9}$/', $digits)) {
            return (preg_match('/[\s().\-]/', $raw) || $labelled) ? 'landline' : null;
        }
        if (str_starts_with($raw, '+') || str_starts_with($raw, '(+')) {
            return strlen($digits) >= 10 && strlen($digits) <= 15 ? 'international' : null;
        }
        if ($labelled && strlen($digits) >= 10 && strlen($digits) <= 15) return 'international';
        // North-American style "890-555-0401" / "(415) 555-0132".
        if (preg_match('/^\(?\d{3}\)?[\s.\-]\d{3}[\s.\-]\d{4}$/', $raw)) return 'international';
        if ($labelled && preg_match('/^\d{3}[\s\-.]?\d{4}$/', $raw)) return 'local';
        return null;
    }

    private const PLACES = ['manila', 'quezon city', 'makati', 'pasig', 'taguig', 'mandaluyong', 'marikina', 'caloocan', 'valenzuela', 'malabon', 'navotas', 'parañaque', 'paranaque', 'las piñas', 'las pinas', 'muntinlupa', 'pasay', 'san juan', 'pateros',
        'cabanatuan', 'gapan', 'palayan', 'san jose', 'muñoz', 'munoz', 'science city of muñoz', 'aliaga', 'bongabon', 'cabiao', 'carranglan', 'cuyapo', 'gabaldon', 'general mamerto natividad', 'general tinio', 'guimba', 'jaen', 'laur', 'licab', 'llanera', 'lupao', 'nampicuan', 'pantabangan', 'peñaranda', 'penaranda', 'san antonio', 'san isidro', 'san leonardo', 'santa rosa', 'santo domingo', 'sto. domingo', 'sta. rosa', 'talavera', 'talugtug', 'zaragoza', 'quezon', 'rizal',
        'san fernando', 'angeles', 'mabalacat', 'tarlac city', 'malolos', 'meycauayan', 'san jose del monte', 'olongapo', 'balanga', 'baliwag', 'baliuag', 'plaridel', 'bocaue', 'marilao', 'guiguinto', 'santa maria', 'apalit', 'guagua', 'concepcion', 'paniqui', 'capas', 'dagupan', 'urdaneta', 'baguio', 'san carlos', 'alaminos', 'antipolo', 'cainta', 'taytay', 'calamba', 'santa rosa', 'biñan', 'binan', 'san pedro', 'cabuyao', 'san pablo', 'dasmariñas', 'dasmarinas', 'bacoor', 'imus', 'general trias', 'tagaytay', 'batangas city', 'lipa', 'tanauan', 'lucena', 'naga', 'legazpi', 'iloilo city', 'bacolod', 'cebu city', 'mandaue', 'lapu-lapu', 'davao city', 'cagayan de oro', 'general santos', 'zamboanga city', 'iligan', 'butuan', 'tacloban', 'puerto princesa', 'cauayan', 'santiago', 'tuguegarao', 'ilagan', 'vigan', 'laoag', 'san fernando city'];

    /** Known places, longest first so "Science City of Muñoz" wins over "Muñoz". */
    private function placesByLength(): array {
        static $sorted = null;
        if ($sorted === null) {
            $sorted = array_values(array_unique(self::PLACES));
            usort($sorted, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        }
        return $sorted;
    }

    private function isKnownPlace(string $text): bool {
        $lower = mb_strtolower(trim(preg_replace('/\bcity\s+of\s+|\s+city$/iu', '', $text)));
        return in_array($lower, self::PLACES, true) || in_array($lower . ' city', self::PLACES, true);
    }

    /** Score how much a piece of text looks like a home address (0 = not an address). */
    private function addressScore(string $text): int {
        $lower = mb_strtolower($text);
        if (mb_strlen($text) < 4 || mb_strlen($text) > 180 || str_contains($lower, '@') || preg_match('#https?://|www\.#', $lower)) return 0;
        if ($this->educationLevelOf($text) !== null || $this->institutionOf($text) !== null) return 0;
        if ($this->headingSection($text) !== null) return 0;
        if (preg_match('/[.!?]$/u', $text) && str_word_count($text) > 8) return 0; // a sentence
        $score = 0;
        foreach (self::PROVINCES as $province) {
            if (preg_match('/\b' . preg_quote($province, '/') . '\b/u', $lower)) { $score += 3; break; }
        }
        foreach (self::PLACES as $place) {
            if (preg_match('/(?<![\p{L}])' . preg_quote($place, '/') . '(?![\p{L}])/u', $lower)) { $score += 3; break; }
        }
        if (preg_match('/\b(brgy|bgy|barangay|purok|sitio|zone|poblacion|street|st\.|avenue|ave\.?|road|rd\.|highway|hwy|subdivision|subd\.?|village|vill\.|compound|cmpd|blk|block|lot|phase|bldg|building|unit|apt|apartment|townhouse|homes|heights|extension|ext\.)\b/u', $lower)
            || preg_match('/\d+\w*\s+[\p{L}\s]{2,30}\b(st|rd|ave|blvd|boulevard|lane|ln|drive|way|court|ct)\b\.?(?:,|$)/u', $lower)) $score += 2;
        if (preg_match('/\b(city|municipality|province|philippines|usa|u\.s\.a|united states|canada|united kingdom|uk|singapore|australia|japan|uae|dubai|saudi arabia|qatar|hong kong|taiwan|new zealand)\b/u', $lower)) $score += 1;
        if (str_contains($text, ',')) $score += 1;
        if (preg_match('/^(?:#|no\.?\s*)?\d+[a-z]?\b/iu', trim($text))) $score += 1;
        // Job titles and employers are not addresses.
        if ($this->companyScore($text) > 1 || ($this->titleScore($text) > 0 && $score < 4)) $score -= 3;
        return $score;
    }

    private function cleanAddress(string $text): string {
        $text = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', ' ', $text);
        foreach ($this->extractPhones($text) as $p) $text = str_replace($p['value'], ' ', $text);
        $text = preg_replace('/^[^\p{L}\p{N}#]+/u', '', $text); // leading icons/bullets
        $text = preg_replace('/\s*\|\s*/u', ', ', $text);
        $text = preg_replace('/\s+,/u', ',', preg_replace('/\s+/u', ' ', $text));
        return trim($text, " ,|-·:;");
    }

    /**
     * Address: a labelled value (same line or following lines), otherwise the most
     * address-like line near the top of the resume or in the personal data section.
     */
    private function extractAddress(array $topLines, array $allLines, string $name): ?array {
        $labels = 'home address|present address|permanent address|current address|residential address|residence address|mailing address|provincial address|city address|complete address|address|residence|location|lives in|based in';
        $all = array_values(array_filter($allLines, fn($l) => $l !== '' && $l !== ResumeTextExtractor::COLUMN_BREAK));
        $sources = [[$topLines, true], [$all, false]];
        foreach ($sources as [$lines, $isTop]) {
            foreach ($lines as $i => $line) {
                $plain = preg_replace('/^[•\s]+/u', '', $line);
                if (!preg_match('/^[^\p{L}\p{N}]{0,3}(' . $labels . ')\s*(?:[:\-–|]|\s{2,})\s*(.*)$/iu', $plain, $m)
                    && !preg_match('/^[^\p{L}\p{N}]{0,3}(' . $labels . ')\s*$/iu', $plain, $m)) {
                    // "Present Address #45 Mabini St." (table cell spacing lost during extraction)
                    if (!preg_match('/^[^\p{L}\p{N}]{0,3}(' . $labels . ')\s+(\S.*)$/iu', $plain, $m)) continue;
                    if (!str_contains(trim($m[1]), ' ') && $this->addressScore($m[2]) < 3) continue;
                }
                $label = mb_strtolower($m[1]);
                // "Location:" outside the top area usually belongs to a job entry.
                if (!$isTop && in_array($label, ['location', 'based in'], true)) continue;
                $value = trim($m[2] ?? '');
                $j = $i;
                // Value on the next line(s), or wrapped onto the next line after a comma.
                while (isset($lines[$j + 1]) && $j < $i + 3) {
                    $next = trim(preg_replace('/^[•\s]+/u', '', $lines[$j + 1]));
                    $continues = $value === '' || str_ends_with($value, ',') || ($j < $i + 2 && $this->addressScore($next) >= 3 && $this->extractPhones($next) === []);
                    if (!$continues || $next === '' || preg_match('/^[\p{L}\s\/]{2,30}\s*:/u', $next) || $this->headingSection($next) !== null || str_contains($next, '@')) break;
                    $value = trim($value === '' ? $next : rtrim($value, ',') . ', ' . $next);
                    $j++;
                }
                $value = $this->cleanAddress($value);
                if (mb_strlen($value) >= 3 && mb_strlen($value) <= 200 && preg_match('/\p{L}/u', $value)) {
                    return ['value' => $value, 'confidence' => 'high', 'evidence' => 'Labelled ' . $label . ' in resume'];
                }
            }
        }

        $best = null; $bestScore = 0;
        foreach (array_slice($topLines, 0, 40) as $i => $line) {
            $segments = preg_split('/\s*(?:\||·|•|\s{3,})\s*/u', $line);
            foreach ($segments as $segment) {
                $segment = $this->cleanAddress($segment);
                if ($segment === '' || ($name !== '' && mb_strtolower($segment) === mb_strtolower($name))) continue;
                $score = $this->addressScore($segment);
                // Two-line addresses: "Blk 5 Lot 12, Phase 2," + "San Jose City, Nueva Ecija".
                $next = isset($topLines[$i + 1]) ? $this->cleanAddress($topLines[$i + 1]) : '';
                if ($score >= 2 && $next !== '' && count($segments) === 1 && (str_ends_with(trim($line), ',') || ($this->addressScore($next) >= 3 && $score < 4 && !preg_match('/\b(19|20)\d{2}\b/', $next)))) {
                    $joined = rtrim($segment, ',') . ', ' . $next;
                    if ($this->addressScore($joined) > $score) { $segment = $joined; $score = $this->addressScore($joined); }
                }
                $score -= intdiv($i, 15); // later lines are less likely to be the home address
                if ($score > $bestScore) { $bestScore = $score; $best = $segment; }
            }
        }
        if ($best === null || $bestScore < 3) return null;
        return ['value' => mb_substr($best, 0, 200), 'confidence' => $bestScore >= 5 ? 'medium' : 'low', 'evidence' => 'Address-like line near the top of the resume'];
    }

    /** @return array{0:?string,1:?string,2:?string} city, province, ZIP code */
    private function splitLocation(string $location): array {
        $lower = mb_strtolower($location);
        $province = null;
        foreach (self::PROVINCES as $p) {
            if (preg_match('/\b' . preg_quote($p, '/') . '\b/u', $lower)) {
                $province = $p === 'ncr' ? 'Metro Manila' : mb_convert_case($p, MB_CASE_TITLE, 'UTF-8');
                break;
            }
        }
        // ZIP: the last 4-digit number, never the house number at the start; year-like values need a province.
        $zip = null;
        if (preg_match('/(?<![\d\-])(\d{4})(?![\d\-])[^\d]*$/u', $location, $zm, PREG_OFFSET_CAPTURE) && $zm[1][1] > 0) {
            $candidate = $zm[1][0];
            if (!preg_match('/^(19|20)\d{2}$/', $candidate) || $province !== null || preg_match('/philippines/i', $location)) $zip = $candidate;
        }
        $parts = array_values(array_filter(array_map(fn($p) => trim(preg_replace('/\b\d{4}\b/', '', $p)), preg_split('/,|\s+-\s+/', $location)), fn($p) => $p !== ''));
        $city = null;
        foreach ($parts as $part) {
            if (preg_match('/\bcity\b/i', $part) && !preg_match('/\d|city hall|city proper|\b(brgy|barangay|purok|st\.|street|subd|blk|lot)\b/i', $part) && mb_strlen($part) <= 30) { $city = $part; break; }
        }
        if ($city === null) {
            foreach ($parts as $part) {
                if ($this->isKnownPlace($part) && mb_strtolower($part) !== mb_strtolower((string)$province)) { $city = $part; break; }
            }
        }
        if ($city === null) {
            // A municipality written inside a longer part ("Brgy. Sto. Rosario Talavera").
            foreach ($this->placesByLength() as $place) {
                if (preg_match('/(?<![\p{L}])' . preg_quote($place, '/') . '(?![\p{L}])/u', $lower, $pm, PREG_OFFSET_CAPTURE) && $place !== mb_strtolower((string)$province) && !preg_match('/\b(brgy|barangay)\.?\s+' . preg_quote($place, '/') . '/u', $lower)) {
                    // Same position in the original text keeps the resume's spelling and capitalisation.
                    $city = substr($location, $pm[0][1], strlen($pm[0][0]));
                    if (preg_match('/^\s+city\b/iu', substr($location, $pm[0][1] + strlen($pm[0][0])), $cm)) $city .= $cm[0];
                    break;
                }
            }
        }
        if ($city === null && $province !== null) {
            foreach ($parts as $i => $part) {
                if (mb_strtolower($part) === mb_strtolower($province) && $i > 0 && !preg_match('/\d|brgy|barangay|purok|street|st\.|blk|lot|phase|subd/i', $parts[$i - 1])) {
                    $city = $parts[$i - 1];
                    break;
                }
            }
        }
        if ($city !== null && !preg_match('/^\p{L}[\p{L}\s.\-ñÑ]+$/u', $city)) $city = null;
        if ($city !== null && $province !== null && mb_strtolower($city) === mb_strtolower($province)) $city = null;
        return [$city, $province, $zip];
    }

    // ─── Objective ──────────────────────────────────────────────────────────

    private function extractObjective(array $sections): ?array {
        $lines = array_filter($sections['objective'] ?? [], fn($l) => $l !== '');
        if (!$lines) return null;
        $text = trim(preg_replace('/\s+/u', ' ', implode(' ', array_map(fn($l) => preg_replace('/^•\s*/u', '', $l), $lines))));
        if (mb_strlen($text) < 15) return null;
        return $this->field(mb_substr($text, 0, 1500), 'high', 'Text under the objective/summary heading');
    }

    // ─── Education ─────────────────────────────────────────────────────────

    public function educationLevelOf(string $text): ?string {
        $t = mb_strtolower($text);
        if (preg_match('/\b(ph\.?\s?d|doctor(?:ate)? (?:of|in)|master(?:\'?s)?(?: degree)? (?:of|in)|\bmba\b|\bm\.?a\.?\s+in\b|\bm\.?s\.?\s+in\b|graduate studies|post-?graduate)\b/u', $t)) return 'postgraduate';
        if (preg_match('/\b(bachelor|baccalaureate|b\.?s\.?\s+in|b\.?s\.?\s+[a-z]|bs[a-z]{1,5}\b|a\.?b\.?\s+in|\bab\s+[a-z]|bachelor\'?s|college degree|associate(?:\'s)? (?:degree|in)|undergraduate|college level|tertiary)\b/u', $t)) return 'college';
        if (preg_match('/\b(vocational|tesda|national certificate|nc\s?(i{1,3}|iv)\b|technical[- ]vocational|tech-?voc|diploma(?: in| of)?|certificate in|short course)\b/u', $t)) return 'vocational';
        if (preg_match('/\b(senior high|shs\b|grade 1[12]\b|stem\b|abm\b|humss\b|tvl\b|gas strand|k-?12)\b/u', $t)) return 'senior_high';
        if (preg_match('/\b(high school|secondary|junior high|jhs\b)\b/u', $t)) return 'high_school';
        if (preg_match('/\b(elementary|primary school|grade school|primary education)\b/u', $t)) return 'elementary';
        return null;
    }

    private function institutionOf(string $text): ?string {
        foreach (preg_split('/\s*(?:\||,(?=\s*\p{Lu})|\s-\s|\s–\s)\s*/u', $text) as $segment) {
            if (preg_match('/\b(university|universidad|college|colegio|institute|institution|school|academy|polytechnic|seminary|tesda|training center|training centre|learning center|high school|elementary)\b/i', $segment)
                && !preg_match('/^(bachelor|master|doctor|b\.?s|bs[a-z]|a\.?b)\b/i', $segment)) {
                return trim($segment, " ,.");
            }
        }
        return null;
    }

    private function extractEducation(array $sections): array {
        $lines = $sections['education'] ?? [];
        $entries = [];
        $current = null;
        foreach ($lines as $line) {
            if ($line === '') { if ($current) { $entries[] = $current; $current = null; } continue; }
            $clean = preg_replace('/^•\s*/u', '', $line);
            $level = $this->educationLevelOf($clean);
            $institution = $this->institutionOf($clean);
            // A school line ("... National High School") followed by its level line ("Secondary") is one entry.
            $completesSchool = $current !== null && $current['degree'] === null && $institution === null && $current['start_date'] === null && $current['end_date'] === null;
            $startsNew = $current !== null && (
                ($level !== null && $current['_level'] !== null && !$completesSchool && !$this->sameEducationLine($current, $clean)) ||
                ($institution !== null && $current['institution'] !== null && $level === null && !preg_match('/\d{4}/', $clean) && $current['_has_degree_line'])
            );
            if ($startsNew) { $entries[] = $current; $current = null; }
            if ($current === null) {
                $current = ['degree' => null, 'field_of_study' => null, 'institution' => null, 'start_date' => null, 'end_date' => null, 'status' => null, 'honors' => null, 'level' => null, '_level' => null, '_has_degree_line' => false, 'raw' => []];
            }
            $current['raw'][] = $clean;
            if ($level !== null && ($current['_level'] === null || $current['degree'] === null)) {
                $current['_level'] = $current['_level'] ?? $level;
                $current['level'] = $current['level'] ?? $level;
                $current['_has_degree_line'] = true;
                $current['degree'] = $this->degreeText($clean, $institution);
                $current['field_of_study'] = $current['degree'] !== null ? $this->fieldOfStudy($current['degree']) : null;
            }
            if ($institution !== null && $current['institution'] === null) $current['institution'] = $institution;
            [$start, $end, $isCurrent] = $this->dateRange($clean);
            if ($start !== null || $end !== null) {
                $current['start_date'] = $current['start_date'] ?? $start;
                $current['end_date'] = $current['end_date'] ?? $end;
                if ($isCurrent) $current['status'] = 'ongoing';
            }
            $lower = mb_strtolower($clean);
            if (preg_match('/\b(undergraduate|undergrad|units earned|level\b|currently enrolled|on-?going|ongoing|candidate|expected|incomplete|\d(?:st|nd|rd|th) year)\b/u', $lower)) $current['status'] = 'incomplete';
            if (preg_match('/\b(cum laude|magna cum laude|summa cum laude|with honors?|with high honors?|valedictorian|salutatorian|dean\'?s list|best in|academic excellence)\b/u', $lower, $hm)) {
                $current['honors'] = $current['honors'] ? $current['honors'] . '; ' . $hm[1] : $hm[1];
            }
        }
        if ($current) $entries[] = $current;

        $result = [];
        $seen = [];
        $year = (int)date('Y');
        foreach ($entries as $e) {
            if ($e['_level'] === null && $e['institution'] === null) continue;
            if ($e['status'] === null) {
                $endYear = $e['end_date'] ? (int)substr($e['end_date'], 0, 4) : null;
                $e['status'] = $endYear !== null ? ($endYear > $year ? 'expected' : 'completed') : null;
            }
            if ($e['start_date'] && $e['end_date'] && $e['start_date'] > $e['end_date']) {
                $this->warnings[] = 'An education entry has a start date after its end date; please check the dates.';
                $e['date_conflict'] = true;
            }
            $key = mb_strtolower(($e['degree'] ?? '') . '|' . ($e['institution'] ?? ''));
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $e['confidence'] = ($e['_level'] !== null && $e['institution'] !== null) ? 'high' : 'medium';
            $e['raw'] = implode("\n", array_slice($e['raw'], 0, 6));
            unset($e['_level'], $e['_has_degree_line']);
            $result[] = $e;
        }
        return array_slice($result, 0, 10);
    }

    private function sameEducationLine(array $current, string $line): bool {
        // A degree line followed by a detail line of the same level (e.g. "Major in ...") is one entry.
        return preg_match('/^(major|minor|specializ|concentration|track|strand)/i', $line) === 1;
    }

    private function degreeText(string $line, ?string $institution): ?string {
        $text = $line;
        if ($institution) $text = str_replace($institution, '', $text);
        $text = preg_replace('/\(?\b(' . self::MONTHS . ')?\.?\s*(19|20)\d{2}\b\)?|present|current|to date/iu', '', $text);
        $text = trim(preg_replace('/\s*[|,\-–]\s*$|^\s*[|,\-–]\s*/u', '', preg_replace('/\s+/u', ' ', $text)));
        $text = trim($text, " ,|-");
        return $text !== '' ? mb_substr($text, 0, 150) : null;
    }

    private function fieldOfStudy(string $degree): ?string {
        if (preg_match('/\b(?:in|of)\s+((?!arts\b|science\b)[\p{L}&,\s\-]{3,80})$/iu', $degree, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/\b(?:science|arts)\s+in\s+([\p{L}&,\s\-]{3,80})/iu', $degree, $m)) return trim($m[1]);
        $abbr = ['bsit' => 'Information Technology', 'bscs' => 'Computer Science', 'bsis' => 'Information Systems', 'bsba' => 'Business Administration', 'bsa' => 'Accountancy', 'bsn' => 'Nursing', 'bsed' => 'Secondary Education', 'beed' => 'Elementary Education', 'bshm' => 'Hospitality Management', 'bshrm' => 'Hotel and Restaurant Management', 'bstm' => 'Tourism Management', 'bscrim' => 'Criminology', 'bsce' => 'Civil Engineering', 'bsee' => 'Electrical Engineering', 'bsme' => 'Mechanical Engineering', 'bsece' => 'Electronics Engineering', 'bscpe' => 'Computer Engineering', 'bsagri' => 'Agriculture', 'bsmt' => 'Medical Technology', 'bspsych' => 'Psychology', 'bsoa' => 'Office Administration', 'stem' => 'STEM', 'abm' => 'ABM', 'humss' => 'HUMSS', 'tvl' => 'TVL', 'gas' => 'GAS'];
        $lower = mb_strtolower($degree);
        foreach ($abbr as $k => $v) {
            if (preg_match('/\b' . $k . '\b/u', $lower)) return $v;
        }
        return null;
    }

    private function highestEducation(array $education): ?array {
        $rank = ['elementary' => 1, 'high_school' => 2, 'senior_high' => 3, 'vocational' => 4, 'college' => 5, 'postgraduate' => 6];
        $best = null;
        foreach ($education as $e) {
            if (!$e['level']) continue;
            $completed = in_array($e['status'], ['completed', null], true);
            $effective = $e['level'];
            if (!$completed && $e['level'] !== 'elementary') {
                // Unfinished studies do not count as the completed level.
                continue;
            }
            if ($best === null || $rank[$effective] > $rank[$best['value']]) {
                $best = $this->field($effective, $e['status'] === 'completed' ? 'high' : 'medium', ($e['degree'] ?? $e['institution'] ?? 'Education entry') . ($e['status'] === null ? ' (completion not stated)' : ''));
            }
        }
        if ($best === null) {
            foreach ($education as $e) {
                if ($e['level'] && ($best === null || $rank[$e['level']] > $rank[$best['value']])) {
                    $best = $this->field($e['level'], 'low', ($e['degree'] ?? 'Education entry') . ' (not completed or completion unclear)');
                }
            }
        }
        return $best;
    }

    // ─── Experience ────────────────────────────────────────────────────────

    private function extractExperience(array $sections): array {
        $lines = $sections['experience'] ?? [];
        $blocks = [];
        $current = null;
        foreach ($lines as $i => $line) {
            if ($line === '') {
                if ($current && $current['body']) { $blocks[] = $current; $current = null; }
                continue;
            }
            // Unbulleted lists ("Company / Title / Dates" repeated without blank lines):
            // after an entry's dates, a short line followed closely by another date starts a new entry.
            if ($current !== null && $this->headerHasDate($current['header']) && !str_starts_with($line, '• ')
                && count(preg_split('/\s+/u', $line)) <= 8 && $this->dateRange($line)[0] === null && $this->dateRange($line)[1] === null
                && $this->dateWithin(array_slice($lines, $i + 1, 3)) && (!$current['body'] || $this->titleScore($line) > 0 || $this->companyScore($line) > 0)) {
                $blocks[] = $current;
                $current = ['header' => [$line], 'body' => []];
                continue;
            }
            $isBullet = str_starts_with($line, '• ');
            $hasDate = $this->dateRange($line)[0] !== null || $this->dateRange($line)[1] !== null;
            $sentenceLike = preg_match('/:$|\b(is|are|was|were|my|our|include|includes|included|responsible)\b/iu', $line)
                || (preg_match_all('/\p{L}{2,}/u', $line) > 12 && !$hasDate && !str_contains($line, ' | '));
            $isHeader = !$isBullet && !$sentenceLike && (mb_strlen($line) <= 110) && !preg_match('/[.;]$/u', $line) && ($hasDate || $this->titleScore($line) > 0 || $this->companyScore($line) > 0 || preg_match('/\s[|@]\s|\sat\s/u', $line));
            if ($current === null) {
                $current = ['header' => [], 'body' => []];
            } elseif ($isHeader && $current['body'] && $this->blockLooksComplete($current)) {
                $blocks[] = $current;
                $current = ['header' => [], 'body' => []];
            } elseif ($isHeader && !$current['body'] && $hasDate && $this->headerHasDate($current['header']) && count($current['header']) >= 2) {
                $blocks[] = $current;
                $current = ['header' => [], 'body' => []];
            }
            if (!$current['body'] && !$isBullet && !$sentenceLike && ($isHeader || count($current['header']) < 3) && mb_strlen($line) <= 110) {
                $current['header'][] = $line;
            } else {
                $current['body'][] = $line;
            }
        }
        if ($current && ($current['header'] || $current['body'])) $blocks[] = $current;

        $entries = [];
        $seen = [];
        foreach ($blocks as $block) {
            $entry = $this->buildExperienceEntry($block);
            if ($entry === null) continue;
            $key = mb_strtolower(($entry['title'] ?? '') . '|' . ($entry['company'] ?? '') . '|' . ($entry['start_date'] ?? ''));
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $entries[] = $entry;
        }
        return array_slice($entries, 0, 20);
    }

    private function dateWithin(array $lines): bool {
        foreach ($lines as $l) {
            if ($l === '' ) continue;
            if (preg_match('/(?:19|20)\d{2}\s*(?:-|to|–)\s*(?:(?:\p{L}+\.?\s*)?(?:19|20)\d{2}|present|current|now|date)/iu', $l)) return true;
        }
        return false;
    }

    private function headerHasDate(array $header): bool {
        foreach ($header as $h) {
            [$s, $e] = $this->dateRange($h);
            if ($s !== null || $e !== null) return true;
        }
        return false;
    }

    private function blockLooksComplete(array $block): bool {
        return count($block['header']) > 0 || count($block['body']) > 1;
    }

    private function buildExperienceEntry(array $block): ?array {
        $title = null; $company = null; $location = null; $start = null; $end = null; $isCurrent = false;
        $headerText = implode(' | ', $block['header']);
        foreach ($block['header'] as $line) {
            [$s, $e, $cur] = $this->dateRange($line);
            if ($s !== null || $e !== null) {
                $start = $start ?? $s; $end = $end ?? $e; $isCurrent = $isCurrent || $cur;
            }
            $segments = preg_split('/\s*(?:\||·|\s[-–]\s|,(?=\s)|\s@\s|\sat\s)\s*/u', $this->stripDates($line));
            foreach ($segments as $seg) {
                $seg = $this->trimSegment($seg);
                if ($seg === '' || mb_strlen($seg) < 2) continue;
                $ts = $this->titleScore($seg);
                $cs = $this->companyScore($seg);
                if ($location === null && $this->isLocationSegment($seg)) { $location = $seg; continue; }
                if ($title === null && $ts > 0 && $ts >= $cs) { $title = $seg; continue; }
                if ($company === null && $cs > 0) { $company = $seg; continue; }
            }
        }
        // Positional fallback ("Title, Employer" is the most common order) when keywords did not decide.
        if ($title === null || $company === null) {
            $plain = [];
            foreach ($block['header'] as $line) {
                foreach (preg_split('/\s*(?:\||·|\s[-–]\s|,(?=\s)|\s@\s|\sat\s)\s*/u', $this->stripDates($line)) as $seg) {
                    $seg = $this->trimSegment($seg);
                    if ($seg !== '' && mb_strlen($seg) >= 2 && $seg !== $title && $seg !== $company && $seg !== $location && !$this->isLocationSegment($seg)) $plain[] = $seg;
                }
            }
            if ($title === null && $plain) $title = array_shift($plain);
            if ($company === null && $plain) $company = array_shift($plain);
        }

        $responsibilities = [];
        $achievements = [];
        $buffer = '';
        foreach ($block['body'] as $line) {
            $isBullet = str_starts_with($line, '• ');
            $text = preg_replace('/^•\s*/u', '', $line);
            if ($isBullet || $buffer === '' || preg_match('/[.;:]$/u', $buffer) || preg_match('/^\p{Lu}/u', $text) && mb_strlen($buffer) > 60 && preg_match('/[.!]$/u', $buffer)) {
                if ($buffer !== '') $responsibilities[] = $buffer;
                $buffer = $text;
            } else {
                $buffer .= ' ' . $text; // wrapped continuation line
            }
        }
        if ($buffer !== '') $responsibilities[] = $buffer;
        $responsibilities = array_values(array_unique(array_filter(array_map(fn($r) => trim($r), $responsibilities), fn($r) => mb_strlen($r) >= 3)));
        foreach ($responsibilities as $r) {
            if (preg_match('/\d+\s?%|\b(increased|reduced|improved|achieved|awarded|recognized|recognised|exceeded|saved|grew|top performer|employee of the|promoted|record)\b/iu', $r)) {
                $achievements[] = $r;
            }
        }

        if ($title === null && $company === null && !$responsibilities) return null;
        if ($title === null && $company === null && $start === null) {
            // Prose without any role, employer or dates is not an employment entry.
            return null;
        }

        $warnings = [];
        $months = null;
        if ($start !== null) {
            $endForCalc = $isCurrent ? date('Y-m') : ($end ?? null);
            if ($endForCalc !== null) {
                if ($endForCalc < $start) {
                    $warnings[] = 'End date is earlier than start date.';
                    $this->warnings[] = 'An employment entry has an end date earlier than its start date; please check the dates.';
                } else {
                    [$sy, $sm] = array_map('intval', explode('-', $start . '-01'));
                    [$ey, $em] = array_map('intval', explode('-', $endForCalc . '-01'));
                    $months = max(1, ($ey - $sy) * 12 + ($em - $sm) + 1);
                }
            }
            if ($start > date('Y-m', strtotime('+6 months'))) {
                $warnings[] = 'Start date is in the future.';
                $this->warnings[] = 'An employment entry starts in the future; please check the dates.';
            }
        }

        $confidence = ($this->titleScore((string)$title) > 0 && $start !== null) ? 'high' : (($title !== null && ($company !== null || $start !== null)) ? 'medium' : 'low');
        return [
            'title' => $title ? mb_substr($title, 0, 150) : null,
            'company' => $company ? mb_substr($company, 0, 150) : null,
            'location' => $location,
            'start_date' => $start,
            'end_date' => $isCurrent ? null : $end,
            'is_current' => $isCurrent,
            'duration_months' => $months,
            'responsibilities' => array_slice(array_map(fn($r) => mb_substr($r, 0, 400), $responsibilities), 0, 15),
            'achievements' => array_slice(array_map(fn($r) => mb_substr($r, 0, 400), $achievements), 0, 10),
            'confidence' => $confidence,
            'warnings' => $warnings,
            'raw' => mb_substr($headerText, 0, 300),
        ];
    }

    /** Trim separators but keep the period of abbreviations such as "Corp." or "Inc.". */
    private function trimSegment(string $seg): string {
        $seg = trim($seg, " ,-|()");
        if (str_ends_with($seg, '.') && !preg_match('/\b(corp|inc|co|ltd|phils|bros|jr|sr|st|ave|assn|dept|intl)\.$/iu', $seg)) {
            $seg = rtrim($seg, '.');
        }
        return trim($seg);
    }

    private function titleScore(string $text): int {
        $lower = mb_strtolower($text);
        if (mb_strlen($lower) > 70) return 0;
        $score = 0;
        foreach (self::TITLE_WORDS as $w) {
            if (preg_match('/\b' . preg_quote($w, '/') . 's?\b/u', $lower)) $score++;
        }
        return $score;
    }

    private function companyScore(string $text): int {
        $lower = mb_strtolower($text);
        $score = 0;
        foreach (self::COMPANY_WORDS as $w) {
            if (preg_match('/(?<![\p{L}])' . preg_quote($w, '/') . '(?![\p{L}])/u', $lower)) $score++;
        }
        return $score;
    }

    private function isLocationSegment(string $seg): bool {
        $lower = mb_strtolower(trim($seg));
        if (in_array($lower, self::PROVINCES, true)) return true;
        if (preg_match('/^[\p{L}\s.]+ city$/u', $lower) && $this->companyScore($seg) === 0) return true;
        return in_array($lower, ['philippines', 'remote', 'work from home', 'wfh', 'metro manila', 'manila', 'quezon city', 'makati', 'cabanatuan', 'palayan', 'gapan', 'san jose', 'muñoz', 'science city of muñoz', 'new york', 'usa', 'singapore', 'dubai', 'hong kong'], true);
    }

    private function employmentHint(array $experience): ?array {
        foreach ($experience as $e) {
            if (!empty($e['is_current'])) {
                return $this->field('employed', 'medium', 'Current position: ' . trim(($e['title'] ?? '') . ' ' . ($e['company'] ? 'at ' . $e['company'] : '')));
            }
        }
        return null;
    }

    public function totalExperienceMonths(array $experience): ?int {
        // Merge overlapping periods so concurrent jobs are not double counted.
        $periods = [];
        foreach ($experience as $e) {
            if (!$e['start_date'] || !empty($e['warnings'])) continue;
            $end = $e['is_current'] ? date('Y-m') : $e['end_date'];
            if (!$end) continue;
            $periods[] = [$e['start_date'], $end];
        }
        if (!$periods) return null;
        usort($periods, fn($a, $b) => strcmp($a[0], $b[0]));
        $merged = [];
        foreach ($periods as $p) {
            $last = count($merged) - 1;
            if ($last >= 0 && $p[0] <= $merged[$last][1]) {
                $merged[$last][1] = max($merged[$last][1], $p[1]);
            } else {
                $merged[] = $p;
            }
        }
        $months = 0;
        foreach ($merged as [$s, $e]) {
            [$sy, $sm] = array_map('intval', explode('-', $s));
            [$ey, $em] = array_map('intval', explode('-', $e));
            $months += max(1, ($ey - $sy) * 12 + ($em - $sm) + 1);
        }
        return $months;
    }

    // ─── Dates ─────────────────────────────────────────────────────────────

    /** @return array{0:?string,1:?string,2:bool} start (Y-m), end (Y-m), isCurrent */
    public function dateRange(string $text): array {
        $month = '(?:(' . self::MONTHS . ')\.?\s*,?\s*)?';
        $num = '(?:(\d{1,2})[\/\-.])?';
        $year = '((?:19|20)\d{2})';
        $point = "(?:{$month}{$num}{$year})";
        $current = '(present|current(?:ly)?|now|to date|date|ongoing|on-going)';
        $re = "/{$point}\s*(?:-|to|until|–|—|~)\s*(?:{$point}|{$current})/iu";
        if (preg_match($re, $text, $m)) {
            $start = $this->ym($m[1] ?? '', $m[2] ?? '', $m[3] ?? '');
            $isCurrent = !empty($m[7]);
            $end = $isCurrent ? null : $this->ym($m[4] ?? '', $m[5] ?? '', $m[6] ?? '');
            return [$start, $end, $isCurrent];
        }
        if (preg_match("/{$point}\s*(?:-|to|–)\s*{$current}/iu", $text, $m)) {
            return [$this->ym($m[1] ?? '', $m[2] ?? '', $m[3] ?? ''), null, true];
        }
        // A single year/month-year (e.g. graduation "2012", "May 2020").
        if (preg_match("/(?<![\d\/])(?:(" . self::MONTHS . ")\.?\s*,?\s*)?((?:19|20)\d{2})(?![\d\/])/iu", $text, $m)) {
            $y = (int)$m[2];
            if ($y >= 1950 && $y <= (int)date('Y') + 6) {
                return [null, $this->ym($m[1] ?? '', '', $m[2]), false];
            }
        }
        return [null, null, false];
    }

    private function ym(string $monthName, string $monthNum, string $year): ?string {
        if ($year === '') return null;
        $y = (int)$year;
        if ($y < 1950 || $y > (int)date('Y') + 6) return null;
        $m = 1;
        if ($monthName !== '') {
            $map = ['jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12];
            $m = $map[mb_strtolower(mb_substr($monthName, 0, 3))] ?? 1;
        } elseif ($monthNum !== '' && (int)$monthNum >= 1 && (int)$monthNum <= 12) {
            $m = (int)$monthNum;
        }
        return sprintf('%04d-%02d', $y, $m);
    }

    private function stripDates(string $text): string {
        $text = preg_replace('/\(?(?:(?:' . self::MONTHS . ')\.?\s*,?\s*)?(?:\d{1,2}[\/\-.])?(?:19|20)\d{2}\s*(?:-|to|until|–|—|~)\s*(?:(?:(?:' . self::MONTHS . ')\.?\s*,?\s*)?(?:\d{1,2}[\/\-.])?(?:19|20)\d{2}|present|current(?:ly)?|now|to date|date|ongoing)\)?/iu', ' ', $text);
        $text = preg_replace('/\(?(?:(?:' . self::MONTHS . ')\.?\s*,?\s*)?(?:19|20)\d{2}\)?/iu', ' ', $text);
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    private function parseFullDate(string $text): ?string {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        $text = preg_replace('/\b(\d{1,2})(?:st|nd|rd|th)\b(?:\s+of)?/i', '$1', $text);
        $date = null;
        if (preg_match('/^((' . self::MONTHS . ')\.?\s+\d{1,2},?\s+(19|20)\d{2}|\d{1,2}\s+(' . self::MONTHS . ')\.?,?\s+(19|20)\d{2})/iu', $text, $m)) {
            $ts = strtotime(str_replace([',', '.'], ['', ''], $m[1]));
            if ($ts !== false) $date = date('Y-m-d', $ts);
        } elseif (preg_match('/^((?:19|20)\d{2})[\/\-.](\d{1,2})[\/\-.](\d{1,2})\b/', $text, $m)) {
            $date = checkdate((int)$m[2], (int)$m[3], (int)$m[1]) ? sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]) : null;
        } elseif (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.]((?:19|20)\d{2})\b/', $text, $m)) {
            // Philippine resumes normally write month first; a first number above 12 must be the day.
            [$a, $b, $y] = [(int)$m[1], (int)$m[2], (int)$m[3]];
            [$month, $day] = $a > 12 ? [$b, $a] : [$a, $b];
            $date = checkdate($month, $day, $y) ? sprintf('%04d-%02d-%02d', $y, $month, $day) : null;
        }
        if ($date === null) return null;
        $age = (int)date('Y') - (int)substr($date, 0, 4);
        return ($age >= 14 && $age <= 90) ? $date : null;
    }

    // ─── Skills ────────────────────────────────────────────────────────────

    private function extractSkills(array $sections, array $experience, array $certifications, array $trainings): array {
        $skills = [];
        $add = function (string $name, string $confidence, string $source, string $evidence) use (&$skills) {
            $name = trim($name, " \t-–•:;,.()");
            if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 60) return;
            $canonical = SkillNormalizer::canonicalize($name);
            $key = SkillNormalizer::key($name);
            $rank = ['high' => 3, 'medium' => 2, 'low' => 1];
            if (isset($skills[$key])) {
                $skills[$key]['duplicates']++;
                if (!in_array($source, $skills[$key]['sources'], true)) $skills[$key]['sources'][] = $source;
                if ($rank[$confidence] > $rank[$skills[$key]['confidence']]) $skills[$key]['confidence'] = $confidence;
                return;
            }
            $skills[$key] = [
                // Listed skills keep the jobseeker's wording; mentions use the canonical name.
                'name' => $source === 'skills_section' ? $name : ($canonical ?? $name),
                'as_written' => $name,
                'canonical' => $canonical,
                'category' => SkillNormalizer::categorize($canonical ?? $name),
                'confidence' => $confidence,
                'sources' => [$source],
                'evidence' => mb_substr($evidence, 0, 200),
                'duplicates' => 0,
            ];
        };

        // 1) Items listed under skill headings.
        foreach ($sections['skills'] ?? [] as $line) {
            if ($line === '') continue;
            $text = preg_replace('/^•\s*/u', '', $line);
            // "Technical: HTML, CSS" → drop the sub-label.
            $text = preg_replace('/^[\p{L}\s&\/]{2,30}:\s*/u', '', $text);
            $separators = str_contains($text, ',') ? '/\s*(?:[,;|•·●▪]|\s\/\s|\s-\s|\band\b(?=[^,]*$))\s*/u' : '/\s*(?:[;|•·●▪]|\s\/\s|\s-\s)\s*/u';
            $items = preg_split($separators, $text);
            foreach ($items as $item) {
                $item = trim($item);
                if ($item === '') continue;
                if ($this->isLanguageItem($item)) continue; // handled by extractLanguages()
                $words = count(preg_split('/\s+/u', $item));
                if ($words <= 5 && !preg_match('/[.!?]$/u', $item)) {
                    $add($item, 'high', 'skills_section', $line);
                } else {
                    // Sentence-like entries: keep only recognised skills mentioned inside.
                    foreach (SkillNormalizer::findInText($item) as $canonical => $alias) {
                        $add($canonical, 'high', 'skills_section', $line);
                    }
                }
            }
        }

        // 2) Skills mentioned in experience, certifications and trainings.
        foreach ($experience as $e) {
            $text = implode(' ', array_merge([(string)$e['title']], $e['responsibilities']));
            foreach (SkillNormalizer::findInText($text) as $canonical => $alias) {
                $add($canonical, 'medium', 'experience', ($e['title'] ?? 'Experience') . ': mentions "' . $alias . '"');
            }
        }
        foreach (array_merge($certifications, $trainings) as $c) {
            foreach (SkillNormalizer::findInText((string)$c['name']) as $canonical => $alias) {
                $add($canonical, 'medium', 'certification_or_training', (string)$c['name']);
            }
        }

        // 3) Mentions elsewhere (summary, projects) are low confidence and left unselected by default.
        $other = implode("\n", array_merge($sections['objective'] ?? [], $sections['projects'] ?? [], $sections['awards'] ?? [], $sections['volunteer'] ?? [], $sections['header'] ?? []));
        foreach (SkillNormalizer::findInText($other) as $canonical => $alias) {
            $add($canonical, 'low', 'other_text', 'Mentioned: "' . $alias . '"');
        }

        $list = array_values($skills);
        usort($list, fn($a, $b) => [$b['confidence'] === 'high', $b['confidence'] === 'medium'] <=> [$a['confidence'] === 'high', $a['confidence'] === 'medium']);
        return array_slice($list, 0, 80);
    }

    // ─── Certifications, licenses, list sections ──────────────────────────

    private function extractCertifications(array $sections, string $fullText): array {
        $items = $this->extractListSection($sections['certifications'] ?? [], 'certification');
        $names = array_map(fn($i) => mb_strtolower($i['name']), $items);

        // Credentials stated elsewhere (e.g. under Education or Trainings) are still credentials.
        $patterns = [
            '/[^\n]*\b(?:NC\s?(?:I{1,3}|IV)|National Certificate\s*(?:I{1,3}|IV)?)\b[^\n]*/u',
            '/[^\n]*\bcivil service\b[^\n]*\b(?:eligib|professional|sub-?professional|passer)[^\n]*/iu',
            '/[^\n]*\b(?:licensed|license[d]?\s+(?:professional|teacher|nurse|engineer|electrician|driver)|PRC\b|board passer|licensure exam(?:ination)?)[^\n]*/u',
            '/[^\n]*\b(?:professional|non-professional)\s+driver\'?s?\s+licen[cs]e[^\n]*/iu',
            '/[^\n]*\bcertified\s+[\p{L}][^\n]{2,60}/u',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $fullText, $m)) {
                foreach ($m[0] as $line) {
                    $name = trim(preg_replace('/^•\s*/u', '', $line));
                    if (mb_strlen($name) > 160 || mb_strlen($name) < 4) continue;
                    if (in_array(mb_strtolower($name), $names, true)) continue;
                    $names[] = mb_strtolower($name);
                    $items[] = $this->listItem($name, 'certification', 'medium');
                }
            }
        }
        foreach ($items as &$item) {
            $lower = mb_strtolower($item['name']);
            $item['type'] = preg_match('/licen[cs]e|licensed|prc|board|registered/u', $lower) ? 'license'
                : (preg_match('/civil service|eligib/u', $lower) ? 'eligibility' : 'certification');
            $item['issuer'] = null;
            foreach (['TESDA', 'PRC', 'CSC', 'Civil Service Commission', 'LTO', 'DOLE', 'Microsoft', 'Google', 'Cisco', 'CompTIA', 'AWS', 'Oracle', 'Red Cross', 'DOH', 'OSHC'] as $issuer) {
                if (stripos($item['name'], $issuer) !== false) { $item['issuer'] = $issuer; break; }
            }
        }
        unset($item);
        return array_slice($items, 0, 30);
    }

    private function extractListSection(array $lines, string $kind): array {
        $items = [];
        $buffer = '';
        foreach ($lines as $line) {
            if ($line === '') { if ($buffer !== '') { $items[] = $buffer; $buffer = ''; } continue; }
            $isBullet = str_starts_with($line, '• ');
            $text = preg_replace('/^•\s*/u', '', $line);
            $continuation = $buffer !== '' && !$isBullet && (preg_match('/^[\p{Ll}(]/u', $text) || preg_match('/[,&\-]$|\b(and|of|in|for|the)$/iu', $buffer));
            // A line that is only a date belongs to the previous item.
            $dateOnly = $buffer !== '' && preg_match('/^[\s(]*(?:(?:' . self::MONTHS . ')\.?\s*\d{0,2},?\s*)?(?:19|20)\d{2}[\s)]*(?:-\s*(?:(?:' . self::MONTHS . ')\.?\s*)?(?:(?:19|20)\d{2}|present))?[\s)]*$/iu', $text);
            if ($continuation || $dateOnly) {
                $buffer .= ($dateOnly ? ' | ' : ' ') . $text;
            } else {
                if ($buffer !== '') $items[] = $buffer;
                $buffer = $text;
            }
        }
        if ($buffer !== '') $items[] = $buffer;

        $result = [];
        $seen = [];
        foreach ($items as $item) {
            $item = trim($item);
            if (mb_strlen($item) < 3) continue;
            $key = mb_strtolower(preg_replace('/\W+/u', '', $item));
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $result[] = $this->listItem($item, $kind, 'high');
        }
        return array_slice($result, 0, 30);
    }

    private function listItem(string $text, string $kind, string $confidence): array {
        [$start, $end] = $this->dateRange($text);
        $name = trim($this->stripDates($text), " |,-–");
        if ($name === '') $name = $text;
        return ['name' => mb_substr($name, 0, 250), 'date' => $end ?? $start, 'kind' => $kind, 'confidence' => $confidence, 'raw' => mb_substr($text, 0, 300)];
    }

    private function isLanguageItem(string $item): bool {
        $lower = mb_strtolower(trim($item));
        foreach (self::LANGUAGES as $language) {
            if (preg_match('/^' . preg_quote($language, '/') . '\b\s*(?:[:\-–(]|$|\s(?:native|fluent|proficient|conversational|intermediate|basic|beginner|advanced))/u', $lower)) return true;
        }
        return in_array($lower, ['native', 'fluent'], true);
    }

    private function extractLanguages(array $sections, string $fullText): array {
        $source = implode("\n", $sections['languages'] ?? []);
        $confidence = 'high';
        if (trim($source) === '') {
            // Languages written with a proficiency anywhere (e.g. "English: Native / French: Fluent" under Skills).
            $langs = implode('|', array_map(fn($l) => preg_quote($l, '/'), self::LANGUAGES));
            if (preg_match_all('/\b(' . $langs . ')\s*[:\-–(]\s*(native|fluent|proficient|conversational|intermediate|basic|beginner|advanced|mother tongue)\b/iu', $fullText, $mm, PREG_SET_ORDER)) {
                $found = [];
                foreach ($mm as $m) {
                    $name = mb_convert_case(mb_strtolower($m[1]), MB_CASE_TITLE, 'UTF-8');
                    $found[$name] = ['name' => $name, 'proficiency' => mb_strtolower($m[2]), 'confidence' => 'high'];
                }
                return array_values($found);
            }
        }
        if (trim($source) === '') {
            // Only accept languages stated in a "Languages:" style label elsewhere.
            $label = $this->labelled($fullText, ['languages spoken', 'languages', 'language', 'dialects', 'dialect']);
            if ($label === null) return [];
            $source = $label;
            $confidence = 'medium';
        }
        $found = [];
        $lower = mb_strtolower($source);
        foreach (self::LANGUAGES as $language) {
            if (preg_match('/\b' . preg_quote($language, '/') . '\b/u', $lower)) {
                $proficiency = null;
                if (preg_match('/\b' . preg_quote($language, '/') . '\b[^\n,;]{0,25}?\b(native|fluent|proficient|conversational|intermediate|basic|beginner|advanced|mother tongue)\b/u', $lower, $m)) {
                    $proficiency = $m[1];
                }
                $found[] = ['name' => mb_convert_case($language, MB_CASE_TITLE, 'UTF-8'), 'proficiency' => $proficiency, 'confidence' => $confidence];
            }
        }
        return $found;
    }

    // ─── Helpers ───────────────────────────────────────────────────────────

    private function labelled(string $text, array $labels, bool $allowSpace = false): ?string {
        $sep = $allowSpace ? '(?:[:\-–|]|\s)' : '(?:[:\-–|]|\s{2,})';
        foreach ($labels as $label) {
            $l = preg_quote($label, '/');
            if (preg_match('/(?:^|\n|\|)[^\S\n]*[•]?[^\S\n]*' . $l . '[^\S\n]*' . $sep . '[^\S\n]*([^\n|]{2,200})/iu', $text, $m)) {
                $value = trim($m[1], " \t:-–");
                if ($value !== '') return $value;
            }
            // Label alone on its line, value on the next line.
            if (preg_match('/(?:^|\n)[^\S\n]*[•]?[^\S\n]*' . $l . '[^\S\n]*:?[^\S\n]*\n[^\S\n]*([^\n|]{2,200})/iu', $text, $m)) {
                $value = trim($m[1]);
                if ($value !== '' && $this->headingSection($value) === null && !preg_match('/^[\p{L}\s\/]{2,30}\s*:/u', $value)) return $value;
            }
        }
        return null;
    }

    private function field($value, string $confidence, string $evidence): array {
        return ['value' => $value, 'confidence' => $confidence, 'evidence' => mb_substr($evidence, 0, 200)];
    }

    private function ascii(string $s): string {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        return $t === false ? $s : strtolower($t);
    }
}
