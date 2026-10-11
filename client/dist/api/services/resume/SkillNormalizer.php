<?php
/**
 * Skill normalization shared by resume parsing and resume-driven matching.
 *
 * Each canonical skill has aliases (abbreviations, synonyms, alternate spellings),
 * a category, an optional "family" used for transferable-skill credit, and the
 * broader EmploySmart catalog skill(s) it rolls up to (skills.skill_name).
 */
class SkillNormalizer {

    // canonical => [category, family, aliases[], catalog[]]
    private const TAXONOMY = [
        // ── Computer / office ────────────────────────────────────────────────
        'Microsoft Office' => ['computer', 'office', ['ms office', 'microsoft office suite', 'ms office suite', 'office 365', 'microsoft 365', 'm365'], ['Computer Skills']],
        'Microsoft Word' => ['computer', 'office', ['ms word', 'word processing'], ['Computer Skills']],
        'Microsoft Excel' => ['computer', 'office', ['ms excel', 'excel', 'spreadsheets', 'spreadsheet', 'vlookup', 'pivot tables', 'pivot table'], ['Computer Skills']],
        'Microsoft PowerPoint' => ['computer', 'office', ['ms powerpoint', 'powerpoint', 'power point', 'presentation software'], ['Computer Skills']],
        'Google Workspace' => ['computer', 'office', ['google docs', 'google sheets', 'google suite', 'g suite', 'gsuite', 'google slides'], ['Computer Skills']],
        'Data Entry' => ['computer', 'office', ['data encoding', 'encoding', 'data encoder', 'typing', 'fast typing', 'keyboarding'], ['Computer Skills']],
        'Computer Literacy' => ['computer', 'office', ['computer literate', 'basic computer', 'computer skills', 'computer operation', 'internet navigation', 'email management'], ['Computer Skills']],
        'Computer Troubleshooting' => ['technical', 'it_support', ['troubleshooting', 'hardware troubleshooting', 'computer repair', 'pc troubleshooting', 'computer hardware servicing', 'computer systems servicing', 'css nc ii', 'pc assembly', 'technical support', 'it support', 'help desk', 'helpdesk', 'desktop support'], ['Computer Skills']],
        'Networking' => ['technical', 'it_support', ['computer networking', 'network administration', 'lan', 'wan', 'tcp/ip', 'cabling', 'network configuration', 'ccna'], ['Computer Skills']],
        'Records Management' => ['industry', 'admin', ['filing', 'document management', 'records keeping', 'record keeping', 'recordkeeping'], []],
        'Office Administration' => ['industry', 'admin', ['clerical', 'clerical work', 'administrative support', 'office management', 'administrative tasks', 'secretarial', 'scheduling', 'calendar management'], []],

        // ── Software / IT ────────────────────────────────────────────────────
        'HTML' => ['technical', 'web', ['html5', 'html 5'], ['Computer Skills']],
        'CSS' => ['technical', 'web', ['css3', 'scss', 'sass', 'tailwind', 'tailwind css', 'bootstrap'], ['Computer Skills']],
        'JavaScript' => ['technical', 'web', ['js', 'java script', 'ecmascript', 'es6', 'jquery', 'ajax'], ['Computer Skills']],
        'TypeScript' => ['technical', 'web', ['ts'], ['Computer Skills']],
        'React' => ['technical', 'web', ['react.js', 'reactjs', 'react js', 'redux', 'react native', 'next.js', 'nextjs'], ['Computer Skills']],
        'Angular' => ['technical', 'web', ['angularjs', 'angular.js'], ['Computer Skills']],
        'Vue.js' => ['technical', 'web', ['vue', 'vuejs', 'nuxt'], ['Computer Skills']],
        'Node.js' => ['technical', 'web', ['node', 'nodejs', 'node js', 'express.js', 'expressjs'], ['Computer Skills']],
        'PHP' => ['technical', 'web', ['laravel', 'codeigniter', 'symfony'], ['Computer Skills']],
        'Python' => ['technical', 'programming', ['django', 'flask', 'pandas', 'numpy'], ['Computer Skills']],
        'Java' => ['technical', 'programming', ['spring boot', 'spring framework', 'j2ee'], ['Computer Skills']],
        'C#' => ['technical', 'programming', ['c sharp', 'csharp', '.net', 'asp.net', 'dotnet'], ['Computer Skills']],
        'C++' => ['technical', 'programming', ['cpp'], ['Computer Skills']],
        'Mobile App Development' => ['technical', 'programming', ['android development', 'ios development', 'flutter', 'kotlin', 'swift', 'mobile development'], ['Computer Skills']],
        'SQL' => ['technical', 'data', ['mysql', 'postgresql', 'postgres', 'sql server', 'mssql', 'oracle database', 'sqlite', 'mariadb', 'database management', 'database design', 'databases', 'database'], ['Computer Skills']],
        'Data Analysis' => ['technical', 'data', ['data analytics', 'statistical analysis', 'statistics', 'data visualization', 'power bi', 'tableau', 'reporting and analysis'], ['Computer Skills']],
        'Git' => ['technical', 'programming', ['github', 'gitlab', 'version control'], ['Computer Skills']],
        'Software Testing' => ['technical', 'programming', ['quality assurance testing', 'qa testing', 'manual testing', 'automated testing', 'test automation', 'unit testing', 'selenium', 'test cases'], ['Computer Skills']],
        'Web Development' => ['technical', 'web', ['web design', 'website development', 'front-end development', 'frontend development', 'front end development', 'back-end development', 'backend development', 'full stack', 'full-stack', 'wordpress'], ['Computer Skills']],
        'Software Development' => ['technical', 'programming', ['programming', 'software engineering', 'coding', 'application development'], ['Computer Skills']],
        'Graphic Design' => ['technical', 'creative', ['adobe photoshop', 'photoshop', 'adobe illustrator', 'illustrator', 'canva', 'layout design', 'graphics design', 'coreldraw', 'corel draw', 'figma', 'sketch', 'ui design', 'ux design', 'ui/ux'], ['Computer Skills']],
        'Video Editing' => ['technical', 'creative', ['adobe premiere', 'premiere pro', 'video production', 'capcut', 'final cut'], ['Computer Skills']],
        'Social Media Management' => ['industry', 'marketing', ['social media', 'social media marketing', 'facebook ads', 'content creation', 'community management'], ['Computer Skills']],
        'Digital Marketing' => ['industry', 'marketing', ['seo', 'search engine optimization', 'online marketing', 'email marketing', 'sem', 'google ads'], ['Computer Skills', 'Sales']],
        'Marketing' => ['industry', 'marketing', ['market research', 'brand management', 'advertising', 'campaign management'], ['Sales']],
        'AutoCAD' => ['technical', 'drafting', ['cad', 'computer-aided design', 'drafting', 'technical drawing', 'sketchup', 'revit'], ['Computer Skills']],

        // ── Business / finance ───────────────────────────────────────────────
        'Bookkeeping' => ['industry', 'finance', ['book keeping', 'bookkeeper', 'journal entries', 'ledger', 'general ledger', 'quickbooks', 'peachtree', 'xero'], ['Bookkeeping']],
        'Accounting' => ['industry', 'finance', ['accountancy', 'financial accounting', 'financial statements', 'accounts payable', 'accounts receivable', 'bank reconciliation', 'reconciliation', 'tax filing', 'payroll', 'payroll processing', 'auditing', 'audit'], ['Bookkeeping']],
        'Cash Handling' => ['industry', 'retail', ['cashiering', 'cashier', 'handling cash', 'cash management', 'cash count', 'cash register', 'money handling', 'petty cash'], ['Customer Service', 'Sales']],
        'POS Operation' => ['industry', 'retail', ['pos', 'point of sale', 'pos system', 'point-of-sale'], ['Customer Service', 'Sales']],
        'Sales' => ['industry', 'retail', ['selling', 'sales and marketing', 'direct sales', 'retail sales', 'upselling', 'cross-selling', 'sales management', 'closing sales', 'sales targets', 'lead generation', 'account management'], ['Sales']],
        'Merchandising' => ['industry', 'retail', ['visual merchandising', 'product display', 'merchandiser', 'promodiser', 'shelf stocking', 'planogram'], ['Sales']],
        'Customer Service' => ['communication', 'service', ['customer support', 'customer care', 'client service', 'client relations', 'customer relations', 'customer satisfaction', 'handling customer complaints', 'complaint handling', 'front desk', 'guest relations', 'customer assistance', 'call center', 'bpo', 'customer-facing'], ['Customer Service']],
        'Inventory Management' => ['industry', 'logistics', ['inventory control', 'stock control', 'inventory', 'stock management', 'stocks monitoring', 'stock monitoring', 'warehousing', 'warehouse operations', 'warehouse management', 'stock handling', 'stockroom', 'order processing', 'receiving and dispatching'], ['Inventory Management']],
        'Logistics' => ['industry', 'logistics', ['supply chain', 'procurement', 'purchasing', 'shipping and receiving', 'distribution', 'dispatching'], ['Inventory Management']],
        'Forklift Operation' => ['industry', 'logistics', ['forklift', 'forklift operator', 'pallet jack'], ['Driving']],
        'Project Management' => ['leadership', 'management', ['project coordination', 'project planning', 'project scheduling', 'agile', 'scrum', 'pmp', 'operations management'], ['Project Coordination']],
        'Business Administration' => ['industry', 'management', ['business management', 'entrepreneurship', 'business planning', 'small business management'], ['Project Coordination']],
        'Human Resources' => ['industry', 'admin', ['hr', 'recruitment', 'onboarding', 'talent acquisition', 'employee relations', 'hris'], []],

        // ── Trades / construction ───────────────────────────────────────────
        'Carpentry' => ['industry', 'construction', ['carpenter', 'woodworking', 'furniture making', 'cabinet making', 'formworks'], ['Carpentry']],
        'Masonry' => ['industry', 'construction', ['mason', 'bricklaying', 'brick laying', 'concrete works', 'tile setting', 'tiling', 'plastering'], ['Masonry']],
        'Electrical Installation' => ['industry', 'construction', ['electrical work', 'electrical wiring', 'wiring', 'electrician', 'electrical installation and maintenance', 'eim', 'electrical maintenance', 'electrical systems'], ['Electrical Work']],
        'Plumbing' => ['industry', 'construction', ['plumber', 'pipe fitting', 'pipefitting', 'pipe installation'], ['Plumbing']],
        'Welding' => ['industry', 'construction', ['welder', 'smaw', 'shielded metal arc welding', 'gmaw', 'mig welding', 'tig welding', 'metal fabrication', 'fabrication'], ['Welding']],
        'Painting' => ['industry', 'construction', ['painter', 'house painting', 'spray painting'], ['Painting']],
        'Construction' => ['industry', 'construction', ['construction work', 'site work', 'building construction', 'construction safety', 'heavy equipment'], ['Masonry', 'Carpentry']],
        'Automotive Repair' => ['industry', 'mechanical', ['auto mechanic', 'automotive servicing', 'automotive', 'car repair', 'motorcycle repair', 'motorcycle servicing', 'engine repair', 'vehicle maintenance'], ['Mechanics']],
        'Machine Operation' => ['industry', 'mechanical', ['machine operator', 'machinery operation', 'cnc', 'lathe', 'production line', 'assembly line', 'equipment operation'], ['Mechanics']],
        'Preventive Maintenance' => ['industry', 'mechanical', ['equipment maintenance', 'facility maintenance', 'repair and maintenance', 'mechanical maintenance'], ['Mechanics']],
        'Driving' => ['industry', 'transport', ['driver', "driver's license", 'drivers license', 'professional driver', 'defensive driving', 'delivery driving', 'motorcycle riding', 'delivery rider', 'can drive', 'drive motorcycle', 'drive a motorcycle', 'motorcycle driving', 'car driving'], ['Driving']],

        // ── Food / hospitality ───────────────────────────────────────────────
        'Cooking' => ['industry', 'food', ['cook', 'culinary', 'culinary arts', 'food preparation', 'commercial cooking', 'kitchen operations', 'line cook', 'meal preparation'], ['Cooking']],
        'Baking' => ['industry', 'food', ['baker', 'pastry', 'pastry making', 'bread and pastry', 'cake decorating'], ['Baking']],
        'Food Safety' => ['industry', 'food', ['food handling', 'food hygiene', 'sanitation', 'haccp', 'food safety and sanitation'], ['Food Processing', 'Cooking']],
        'Food Processing' => ['industry', 'food', ['food preservation', 'food production', 'food packaging'], ['Food Processing']],
        'Food and Beverage Service' => ['industry', 'food', ['food service', 'f&b service', 'waiter', 'waitress', 'table service', 'restaurant service', 'barista', 'bartending', 'bartender', 'coffee making'], ['Hospitality', 'Customer Service']],
        'Housekeeping' => ['industry', 'hospitality', ['housekeeper', 'room attendant', 'cleaning', 'janitorial', 'laundry'], ['Hospitality']],
        'Hospitality' => ['industry', 'hospitality', ['hotel operations', 'hotel and restaurant management', 'hrm', 'tourism', 'front office', 'reservations', 'events management'], ['Hospitality']],

        // ── Health / care / education ───────────────────────────────────────
        'Patient Care' => ['industry', 'health', ['nursing care', 'bedside care', 'vital signs', 'patient assessment', 'medication administration', 'nursing', 'clinical skills', 'wound care'], []],
        'First Aid' => ['industry', 'health', ['basic life support', 'bls', 'cpr', 'first aid and cpr', 'occupational first aid'], []],
        'Caregiving' => ['industry', 'health', ['caregiver', 'elderly care', 'child care', 'childcare', 'nanny', 'home care'], ['Hospitality']],
        'Laboratory Skills' => ['industry', 'health', ['laboratory', 'lab testing', 'specimen collection', 'phlebotomy', 'medical technology'], []],
        'Teaching' => ['industry', 'education', ['teacher', 'tutoring', 'tutor', 'lesson planning', 'classroom management', 'curriculum development', 'training delivery'], []],

        // ── Agriculture / livelihood ────────────────────────────────────────
        'Agriculture' => ['industry', 'agri', ['agricultural', 'agribusiness', 'crop production', 'crop management', 'agronomy', 'rice production', 'organic agriculture'], ['Agriculture']],
        'Farming' => ['industry', 'agri', ['farmer', 'farm work', 'farm management', 'organic farming', 'vegetable production', 'harvesting', 'planting'], ['Farming', 'Agriculture']],
        'Animal Husbandry' => ['industry', 'agri', ['livestock', 'poultry', 'poultry raising', 'swine raising', 'hog raising', 'animal care', 'animal production'], ['Animal Husbandry']],
        'Gardening' => ['industry', 'agri', ['gardener', 'landscaping', 'horticulture', 'urban farming'], ['Gardening']],
        'Beekeeping' => ['industry', 'agri', ['apiculture', 'honey production'], ['Beekeeping']],
        'Sewing' => ['industry', 'garments', ['sewer', 'dressmaking', 'dressmaker', 'garment making', 'seamstress', 'sewing machine operation'], ['Sewing']],
        'Tailoring' => ['industry', 'garments', ['tailor', 'pattern making', 'alterations'], ['Tailoring']],

        // ── Security / safety ───────────────────────────────────────────────
        'Security Services' => ['industry', 'security', ['security guard', 'security officer', 'cctv monitoring', 'access control', 'patrolling'], []],
        'Occupational Safety' => ['industry', 'safety', ['osh', 'occupational safety and health', 'workplace safety', 'safety compliance', 'hse', 'bosh'], []],
        'Quality Control' => ['industry', 'manufacturing', ['quality assurance', 'qa/qc', 'qc', 'quality inspection', 'inspection', 'iso 9001', '5s', 'six sigma', 'lean manufacturing'], []],

        // ── Communication / soft ────────────────────────────────────────────
        'Communication' => ['communication', 'soft', ['communication skills', 'verbal communication', 'written communication', 'oral communication', 'interpersonal communication', 'clear communication', 'effective communication'], []],
        'English Communication' => ['communication', 'soft', ['english proficiency', 'fluent in english', 'good command of english', 'english speaking', 'business english'], []],
        'Public Speaking' => ['communication', 'soft', ['presentation skills', 'presentations', 'emcee'], []],
        'Interpersonal Skills' => ['soft', 'soft', ['people skills', 'relationship building', 'rapport building', 'interpersonal'], []],
        'Teamwork' => ['soft', 'soft', ['team player', 'collaboration', 'team work', 'working in a team'], []],
        'Leadership' => ['leadership', 'management', ['team leadership', 'team lead', 'people management', 'supervision', 'supervisory skills', 'team management', 'staff supervision', 'mentoring', 'coaching'], []],
        'Problem Solving' => ['soft', 'soft', ['problem-solving', 'analytical skills', 'analytical thinking', 'critical thinking', 'troubleshooting skills', 'decision making', 'decision-making'], []],
        'Time Management' => ['soft', 'soft', ['punctuality', 'punctual', 'prioritization', 'multitasking', 'multi-tasking', 'ability to work under pressure', 'works well under pressure'], []],
        'Attention to Detail' => ['soft', 'soft', ['detail-oriented', 'detail oriented', 'keen eye for detail'], []],
        'Organizational Skills' => ['soft', 'soft', ['highly organized', 'organization skills', 'planning and organizing'], []],
        'Adaptability' => ['soft', 'soft', ['flexibility', 'adaptable', 'quick learner', 'fast learner', 'willing to learn'], []],
        'Negotiation' => ['communication', 'soft', ['negotiation skills', 'persuasion'], ['Sales']],
        'Basic Math' => ['technical', 'soft', ['basic mathematics', 'numeracy', 'math skills', 'computation', 'arithmetic'], []],
    ];

    // Words commonly found in soft-skill phrases that are not in the taxonomy.
    private const SOFT_HINTS = ['communicat', 'team', 'leader', 'organiz', 'detail', 'problem', 'time manage', 'adapt', 'flexib', 'interpersonal', 'critical', 'creativ', 'hardworking', 'hard-working', 'punctual', 'reliab', 'honest', 'patien', 'motivat', 'initiative', 'responsib', 'work ethic', 'positive attitude', 'customer'];

    private static $aliasIndex = null;

    public static function normalize(string $text): string {
        $text = mb_strtolower($text, 'UTF-8');
        $text = str_replace(['&', '’', '‘', '`'], [' and ', "'", "'", "'"], $text);
        // Keep characters that are meaningful inside skill names (c++, c#, node.js, tcp/ip, ui/ux).
        $text = preg_replace('/[^\p{L}\p{N}+#.\/\'\-]+/u', ' ', $text);
        $text = preg_replace('/(?<![\p{L}\p{N}])[.\-\/]+|[.\-\/]+(?![\p{L}\p{N}])/u', ' ', $text);
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    private static function index(): array {
        if (self::$aliasIndex !== null) {
            return self::$aliasIndex;
        }
        $index = [];
        foreach (self::TAXONOMY as $canonical => [$category, $family, $aliases, $catalog]) {
            foreach (array_merge([$canonical], $aliases) as $alias) {
                $key = self::normalize($alias);
                if ($key !== '' && !isset($index[$key])) {
                    $index[$key] = $canonical;
                }
            }
        }
        // Longest aliases first so "microsoft office suite" wins over "office".
        uksort($index, static fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        self::$aliasIndex = $index;
        return $index;
    }

    public static function info(string $canonical): ?array {
        if (!isset(self::TAXONOMY[$canonical])) {
            return null;
        }
        [$category, $family, $aliases, $catalog] = self::TAXONOMY[$canonical];
        return ['canonical' => $canonical, 'category' => $category, 'family' => $family, 'catalog' => $catalog];
    }

    /** Resolve a single skill phrase (e.g. a skills-section item) to its canonical name. */
    public static function canonicalize(string $phrase): ?string {
        $key = self::normalize($phrase);
        if ($key === '') return null;
        $index = self::index();
        if (isset($index[$key])) return $index[$key];
        // Tolerate decorations such as "Excel (Advanced)" or "Proficient in MS Word".
        $stripped = preg_replace('/\b(advanced|intermediate|basic|beginner|expert|proficient in|proficiency in|knowledge of|knowledgeable in|skilled in|experience in|familiar with|working knowledge of|good|excellent|strong)\b/u', ' ', $key);
        $stripped = trim(preg_replace('/\s+/', ' ', $stripped));
        if ($stripped !== '' && isset($index[$stripped])) return $index[$stripped];
        return null;
    }

    /**
     * Find every taxonomy skill mentioned in free text.
     * @return array<string, string> canonical => matched alias as written
     */
    public static function findInText(string $text): array {
        $haystack = ' ' . self::normalize($text) . ' ';
        $found = [];
        foreach (self::index() as $alias => $canonical) {
            if (isset($found[$canonical])) continue;
            // Very short aliases (js, ts, qc, hr, ...) are only trusted as exact skill items, not inside prose.
            if (mb_strlen($alias) <= 3 && !in_array($alias, ['sql', 'php', 'css', 'pos', 'seo', 'cad', 'cpr', 'bls', 'cnc'], true)) continue;
            $pattern = '/(?<![\p{L}\p{N}+#])' . preg_quote($alias, '/') . '(?![\p{L}\p{N}+#])/u';
            if (preg_match($pattern, $haystack)) {
                $found[$canonical] = $alias;
            }
        }
        return $found;
    }

    public static function categorize(string $skill): string {
        $canonical = self::canonicalize($skill);
        if ($canonical !== null) return self::TAXONOMY[$canonical][0];
        $lower = mb_strtolower($skill, 'UTF-8');
        foreach (self::SOFT_HINTS as $hint) {
            if (str_contains($lower, $hint)) {
                if (str_contains($lower, 'lead') || str_contains($lower, 'manag') || str_contains($lower, 'supervis')) return 'leadership';
                if (str_contains($lower, 'communicat') || str_contains($lower, 'customer')) return 'communication';
                return 'soft';
            }
        }
        return 'other';
    }

    /** Two skills are "related" (transferable) when they share a family but are not identical. */
    public static function related(string $a, string $b): bool {
        $ca = self::canonicalize($a) ?? $a;
        $cb = self::canonicalize($b) ?? $b;
        if ($ca === $cb) return false;
        $fa = self::TAXONOMY[$ca][1] ?? null;
        $fb = self::TAXONOMY[$cb][1] ?? null;
        if ($fa === null || $fb === null || $fa === 'soft' || $fb === 'soft') return false;
        if ($fa === $fb) return true;
        // Catalog roll-ups also indicate transferable experience (e.g. Cash Handling -> Sales).
        $cata = self::TAXONOMY[$ca][3] ?? [];
        $catb = self::TAXONOMY[$cb][3] ?? [];
        return (bool)array_intersect($cata, $catb) && !in_array('Computer Skills', array_intersect($cata, $catb), true);
    }

    /**
     * Map a skill to an existing catalog skill row.
     * @param array<int, array{id:int, skill_name:string}> $catalogRows
     * @return array{id:int, skill_name:string, match:string}|null
     */
    public static function mapToCatalog(string $skill, array $catalogRows): ?array {
        $key = self::normalize($skill);
        $canonical = self::canonicalize($skill);
        $byName = [];
        foreach ($catalogRows as $row) {
            $byName[self::normalize((string)$row['skill_name'])] = $row;
        }
        if (isset($byName[$key])) {
            return ['id' => (int)$byName[$key]['id'], 'skill_name' => $byName[$key]['skill_name'], 'match' => 'exact'];
        }
        if ($canonical !== null) {
            $ck = self::normalize($canonical);
            if (isset($byName[$ck])) {
                return ['id' => (int)$byName[$ck]['id'], 'skill_name' => $byName[$ck]['skill_name'], 'match' => 'synonym'];
            }
            // An alias of the canonical skill may itself be the catalog name ("Electrical Work").
            foreach (self::TAXONOMY[$canonical][2] as $alias) {
                $ak = self::normalize($alias);
                if (isset($byName[$ak])) {
                    return ['id' => (int)$byName[$ak]['id'], 'skill_name' => $byName[$ak]['skill_name'], 'match' => 'synonym'];
                }
            }
            foreach (self::TAXONOMY[$canonical][3] as $catalogName) {
                $nk = self::normalize($catalogName);
                if (isset($byName[$nk])) {
                    return ['id' => (int)$byName[$nk]['id'], 'skill_name' => $byName[$nk]['skill_name'], 'match' => 'broader'];
                }
            }
        }
        return null;
    }

    private const PROFICIENCY = 'beginner|basic|elementary|novice|intermediate|advanced|expert|proficient|competent|fluent|native|conversational|good|very good|excellent|strong|average|fair|high|medium|low|master|working knowledge|limited';
    private const MONTH_WORDS = 'jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|june?|july?|aug(?:ust)?|sep(?:t(?:ember)?)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?';

    /**
     * Tidy a skill as written in a resume: remove rating symbols, percentages, durations, years
     * and proficiency labels ("Driving (8 years)" → "Driving", "Excel – Advanced" → "Excel",
     * "Photoshop 95%" → "Photoshop"). Returns null when nothing usable remains.
     */
    public static function cleanName(string $item): ?string {
        $s = trim(preg_replace('/\s+/u', ' ', $item));
        $months = self::MONTH_WORDS;
        $s = preg_replace('/[\x{2605}\x{2606}\x{2729}-\x{2730}\x{25A0}-\x{25FF}\x{2B24}\x{26AA}\x{26AB}\x{2022}\x{00B7}]+/u', ' ', $s); // ★☆●○■□ ratings
        $s = preg_replace('/[(\[]?\s*\b\d{1,3}(?:\.\d+)?\s*%\s*[)\]]?|[(\[]?\s*\b\d+(?:\.\d+)?\s*(?:\/|out of)\s*\d+\s*[)\]]?/iu', ' ', $s);
        $s = preg_replace('/[(\[]?\s*(?:over\s+|more than\s+|about\s+|almost\s+)?\d+(?:\.\d+)?\s*\+?\s*(?:years?|yrs?\.?|months?|mos?\.?)(?:\s+(?:of\s+)?(?:experience|exp\.?))?\s*[)\]]?/iu', ' ', $s);
        $s = preg_replace('/[(\[]?\s*(?:since\s+)?(?:(?:' . $months . ')\.?\s*)?(?:19|20)\d{2}\s*(?:(?:-|–|—|to)\s*(?:(?:(?:' . $months . ')\.?\s*)?(?:19|20)\d{2}|present|current|now|date))?\s*[)\]]?/iu', ' ', $s);
        $s = preg_replace('/\s*[(\[]\s*(?:' . self::PROFICIENCY . ')(?:\s+level)?\s*[)\]]/iu', ' ', $s);
        $s = preg_replace('/\s*[-–—:|]\s*(?:' . self::PROFICIENCY . ')(?:\s+level)?\s*$/iu', '', $s);
        // "Proficient in MS Word", "Knowledge of bookkeeping"
        $s = preg_replace('/^(?:(?:very\s+)?(?:good|strong|excellent|basic|advanced|working|solid|extensive)\s+)?(?:knowledge|proficiency|skills?|experience|expertise|background)\s+(?:of|in|with|on)\s+/iu', '', $s);
        $s = preg_replace('/^(?:highly\s+)?(?:proficient|skilled|knowledgeable|experienced|familiar|competent|adept|well[- ]versed|trained)\s+(?:in|with|at|on)\s+/iu', '', $s);
        $s = preg_replace('/[(\[]\s*[)\]]/u', ' ', $s);
        $s = self::trimEdges(trim(preg_replace('/\s+/u', ' ', $s)));
        return $s === '' ? null : $s;
    }

    /** Multibyte-safe trim of separators, plus brackets left unbalanced by splitting. */
    public static function trimEdges(string $s): string {
        $s = preg_replace('/^[\s\-–—•·:;,.\/|*+]+|[\s\-–—•·:;,\/|*]+$/u', '', $s) ?? $s;
        $s = rtrim($s, '.');
        if (substr_count($s, '(') < substr_count($s, ')')) $s = preg_replace('/^\(|\)$/u', '', $s) ?? $s;
        if (substr_count($s, '(') > substr_count($s, ')')) $s = preg_replace('/\s*\([^)]*$/u', '', $s) ?? $s;
        return trim($s);
    }

    /**
     * Whether a (cleaned) item can be a skill: not a date or year, number, rating, proficiency
     * word, contact detail, heading or sentence.
     */
    public static function isPlausible(string $name): bool {
        $s = trim($name);
        $len = mb_strlen($s, 'UTF-8');
        if ($len > 60 || ($len < 2 && !in_array($s, ['C', 'R'], true))) return false;
        if (!preg_match('/\p{L}/u', $s)) return false;
        if (preg_match('/\b(?:19|20)\d{2}\b/', $s)) return false;
        if (preg_match('/^(?:' . self::MONTH_WORDS . ')\.?$|^(?:present|current|to date|now|ongoing)$/iu', $s)) return false;
        if (preg_match('/@|https?:|www\.|\.(?:com|net|org|ph)\b/i', $s)) return false;
        if (preg_match('/\d{3,}/', $s) && self::canonicalize($s) === null) return false; // IDs and phone fragments, not "Office 365"
        if (preg_match('/^(?:' . self::PROFICIENCY . ')(?:\s+level)?$/iu', $s)) return false;
        $words = preg_split('/\s+/u', $s);
        if (count($words) > 6 || (preg_match('/[.!?]$/u', $s) && count($words) > 3)) return false;
        $generic = ['skills', 'skill', 'tools', 'software', 'others', 'other', 'etc', 'n/a', 'na', 'none', 'languages', 'language', 'technical', 'soft skills', 'hard skills',
            'interests', 'hobbies', 'level', 'proficiency', 'years', 'year', 'rating', 'and', 'or', 'with', 'in', 'of', 'the', 'native', 'mother tongue', 'computer skills:'];
        return !in_array(mb_strtolower($s, 'UTF-8'), $generic, true);
    }

    /** The tidied skill name, or null when the text is not a usable skill (a year, rating, sentence...). */
    public static function acceptable(?string $name): ?string {
        $clean = self::cleanName((string)$name);
        return $clean !== null && self::isPlausible($clean) ? $clean : null;
    }

    /**
     * Clean a stored skill list (strings or ['name' => ...] rows): invalid entries are dropped,
     * names tidied, and identical names merged. Distinct skills of one family are kept apart.
     */
    public static function cleanList(array $skills): array {
        $out = [];
        $seen = [];
        foreach ($skills as $skill) {
            $name = self::acceptable(is_array($skill) ? ($skill['name'] ?? null) : (is_string($skill) ? $skill : null));
            if ($name === null) continue;
            $key = self::normalize($name);
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $out[] = is_array($skill) ? ['name' => $name] + $skill : $name;
        }
        return $out;
    }

    /**
     * Split a run-together list ("Customer Service Cash Handling MS Excel") into the known
     * skills it is made of, keeping the words as written. Returns null unless the text is
     * clearly a list of known skills.
     */
    public static function splitKnownList(string $text): ?array {
        $tokens = preg_split('/\s+/u', trim($text));
        $n = count($tokens);
        if ($n < 3) return null;
        $normalized = array_map(fn($t) => self::normalize($t), $tokens);
        $index = self::index();
        $parts = [];
        $pending = [];
        $matched = 0;
        $covered = 0;
        for ($i = 0; $i < $n;) {
            $length = 0;
            for ($len = min(5, $n - $i); $len >= 1; $len--) {
                $phrase = trim(implode(' ', array_slice($normalized, $i, $len)));
                if ($phrase !== '' && isset($index[$phrase]) && (mb_strlen($phrase) > 3 || in_array($phrase, ['sql', 'php', 'css', 'pos', 'seo', 'cad', 'cpr', 'bls', 'cnc', 'git', 'c++', 'c#'], true))) { $length = $len; break; }
            }
            if ($length === 0) { $pending[] = $tokens[$i++]; continue; }
            if ($pending) { $parts[] = implode(' ', $pending); $pending = []; }
            $parts[] = implode(' ', array_slice($tokens, $i, $length));
            $matched++;
            $covered += $length;
            $i += $length;
        }
        if ($pending) $parts[] = implode(' ', $pending);
        return ($matched >= 2 && $covered / $n >= 0.7) ? $parts : null;
    }

    /** Canonical form used for comparing skills (canonical name or the normalized phrase). */
    public static function key(string $skill): string {
        $canonical = self::canonicalize($skill);
        return self::normalize($canonical ?? $skill);
    }
}
