<?php
/**
 * Resume extraction and parsing tests (no database needed).
 * Run: php server/tests/resume_parsing_test.php
 */
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../services/resume/ResumeProcessor.php';
require_once __DIR__ . '/fixtures/ResumeFixtures.php';

putenv('RESUME_USE_PDFTOTEXT=0'); // exercise the pure-PHP path used on shared hosting
putenv('RESUME_AI_ENABLED=false');

$failures = 0;
$passes = 0;
function check(bool $ok, string $label, $detail = null) {
    global $failures, $passes;
    if ($ok) { $passes++; echo "  ok   $label\n"; return; }
    $failures++;
    echo "  FAIL $label" . ($detail !== null ? ' :: ' . json_encode($detail, JSON_UNESCAPED_UNICODE) : '') . "\n";
}
function val(array $parsed, string $key) { return $parsed['personal'][$key]['value'] ?? null; }
function skillNames(array $parsed): array { return array_map(fn($s) => $s['name'], $parsed['skills']); }
function tmpPdf(string $bytes): string {
    $path = tempnam(sys_get_temp_dir(), 'rsm') . '.pdf';
    file_put_contents($path, $bytes);
    return $path;
}
$catalog = [['id' => 1, 'skill_name' => 'Electrical Work'], ['id' => 2, 'skill_name' => 'Computer Skills'], ['id' => 3, 'skill_name' => 'Driving'], ['id' => 4, 'skill_name' => 'Customer Service'], ['id' => 5, 'skill_name' => 'Sales']];
$processor = new ResumeProcessor();

echo "Philippine resume with unconventional headings\n";
$p = $processor->parseText(ResumeFixtures::PH_UNUSUAL_HEADINGS, [], $catalog);
check(val($p, 'full_name') === 'Juan A. Dela Cruz', 'all-caps name normalised', val($p, 'full_name'));
check(val($p, 'first_name') === 'Juan' && val($p, 'middle_name') === 'A.' && val($p, 'last_name') === 'Dela Cruz', 'Filipino surname particle split', [val($p, 'first_name'), val($p, 'middle_name'), val($p, 'last_name')]);
check(val($p, 'email') === 'juan.delacruz.test@example.com', 'email');
check(val($p, 'phone') === '0917 123 4567', 'labelled PH mobile number', val($p, 'phone'));
check(val($p, 'city') === 'Cabanatuan City' && val($p, 'province') === 'Nueva Ecija', 'city/province from address', [val($p, 'city'), val($p, 'province')]);
check(val($p, 'birth_date') === '1996-03-05' && val($p, 'sex') === 'male' && val($p, 'civil_status') === 'single', 'labelled personal data');
check(str_starts_with((string)($p['objective']['value'] ?? ''), 'To obtain a position as an Electrician'), '"Career goal" recognised as objective');
check(count($p['education']) === 2 && $p['education'][0]['level'] === 'college' && $p['education'][0]['institution'] === 'Nueva Ecija University of Science and Technology', '"Scholastic record" education entries', $p['education']);
check(($p['highest_education_level']['value'] ?? null) === 'college', 'highest completed education');
check(count($p['experience']) === 2, '"Work background" has 2 jobs', array_map(fn($e) => [$e['title'], $e['company']], $p['experience']));
check(($p['experience'][0]['title'] ?? '') === 'Electrician' && ($p['experience'][0]['company'] ?? '') === 'Metro Builders Corp.' && $p['experience'][0]['is_current'] === true, 'pipe-separated job header');
check(($p['experience'][1]['title'] ?? '') === 'Electrician Helper' && ($p['experience'][1]['start_date'] ?? '') === '2018-03' && ($p['experience'][1]['end_date'] ?? '') === '2019-05', 'comma-separated job header with month dates', $p['experience'][1] ?? null);
check(count($p['experience'][0]['achievements'] ?? []) === 1, 'quantified achievement detected');
$names = skillNames($p);
$lower = array_map('mb_strtolower', $names);
check(count(array_filter($lower, fn($n) => $n === 'electrical wiring')) === 1, 'duplicate skill "Electrical wiring" merged', $names);
check(in_array('AutoCAD', $names, true) && in_array('MS Excel', $names, true), 'listed skills kept as written', $names);
$wiring = array_values(array_filter($p['skills'], fn($s) => $s['name'] === 'Electrical Wiring'))[0] ?? null;
check(($wiring['catalog_skill_name'] ?? null) === 'Electrical Work', 'synonym mapped to catalog skill', $wiring);
$excel = array_values(array_filter($p['skills'], fn($s) => $s['name'] === 'MS Excel'))[0] ?? null;
check(($excel['catalog_skill_name'] ?? null) === 'Computer Skills' && ($excel['canonical'] ?? null) === 'Microsoft Excel', 'abbreviation normalised and rolled up', $excel);
$certs = array_map(fn($c) => $c['name'], $p['certifications']);
check(in_array('Registered Electrical Engineer (PRC License)', $certs, true) && in_array('Civil Service Professional Eligibility', $certs, true), '"Eligibility" section → licenses', $certs);
check(count(array_filter($certs, fn($c) => str_contains($c, 'NC II'))) === 1, 'NC II found outside certification section', $certs);
check(count($p['trainings']) === 2, '"Seminars attended" → trainings');
check(array_column($p['languages'], 'name') === ['English', 'Filipino'] || array_column($p['languages'], 'name') === ['Filipino', 'English'], 'labelled languages', $p['languages']);

echo "Table-based layout\n";
$t = $processor->parseText(ResumeFixtures::TABLE_TEXT, [], $catalog);
check(val($t, 'full_name') === 'Rosa Mendoza', 'name from table cell', val($t, 'full_name'));
check(val($t, 'location') === 'San Jose City, Nueva Ecija', 'address from table cell', val($t, 'location'));
check(count($t['experience']) === 2 && $t['experience'][1]['is_current'] && $t['experience'][0]['company'] === 'Bright Apps Solutions', 'experience rows', array_map(fn($e) => [$e['title'], $e['company']], $t['experience']));
check(($t['education'][0]['field_of_study'] ?? null) === 'Information Technology', 'degree field from table row', $t['education'][0] ?? null);
check(count(array_intersect(['HTML/CSS', 'JavaScript', 'PHP', 'MySQL', 'Networking'], skillNames($t))) >= 4, 'pipe-separated skills', skillNames($t));

echo "Conflicting dates\n";
$c = $processor->parseText(ResumeFixtures::CONFLICTING_DATES, [], $catalog);
check(!empty($c['experience'][0]['warnings']), 'end-before-start flagged on entry', $c['experience'][0] ?? null);
check($c['experience'][0]['duration_months'] === null && $c['total_experience_months'] === null, 'invalid period not counted as experience');
check(count(array_filter($c['warnings'], fn($w) => str_contains($w, 'earlier than its start'))) === 1, 'user-facing warning', $c['warnings']);
check(($c['education'][0]['level'] ?? null) === 'senior_high', 'senior high strand recognised');

echo "Limited qualifications / missing information\n";
$l = $processor->parseText(ResumeFixtures::LIMITED, [], $catalog);
check(!isset($l['personal']['email']), 'no email invented');
check($l['experience'] === [] && $l['certifications'] === [], 'no experience or certificates invented');
check(($l['highest_education_level']['value'] ?? null) === 'high_school', 'high school level');
check(in_array('Hardworking', skillNames($l), true), 'soft skills kept');

echo "Embedded instructions are treated as data\n";
$i = $processor->parseText(ResumeFixtures::PROMPT_INJECTION, [], $catalog);
check($i['certifications'] === [] && $i['experience'] === [] && $i['total_experience_months'] === null, 'instructions do not create credentials or experience', [$i['certifications'], $i['experience']]);
$ai = new ResumeAiExtractor();
$ground = new ReflectionMethod(ResumeAiExtractor::class, 'ground');
$ground->setAccessible(true);
$grounded = $ground->invoke($ai, [
    'full_name' => 'Ana Lim', 'email' => 'ana.lim.test@example.com', 'phone' => '0917 000 0000', 'location' => null, 'objective' => null,
    'education' => [['degree' => 'Bachelor of Science in Nursing', 'field_of_study' => 'Nursing', 'institution' => 'Manila University', 'start_date' => null, 'end_date' => '2015', 'status' => 'completed']],
    'experience' => [['title' => 'Staff Nurse', 'company' => 'City Hospital', 'location' => null, 'start_date' => '2015', 'end_date' => null, 'is_current' => true, 'responsibilities' => []]],
    'skills' => ['Customer Service', 'Patient Care'], 'certifications' => [['name' => 'Licensed Registered Nurse', 'issuer' => 'PRC', 'date' => null]],
    'trainings' => [], 'awards' => [], 'languages' => [], 'projects' => [], 'volunteer' => [],
], ResumeFixtures::PROMPT_INJECTION);
check($grounded['phone'] === null && $grounded['education'] === [] && $grounded['experience'] === [], 'AI values absent from the resume are discarded', $grounded);
check($grounded['skills'] === ['Customer Service'], 'only skills present in the text survive', $grounded['skills']);
check($grounded['certifications'][0]['name'] === 'Licensed Registered Nurse', 'quoted text inside the resume is still "found" — confirmation step and matching rules must handle it');

echo "PDF extraction\n";
$extractor = new ResumeTextExtractor(10);
$single = tmpPdf(ResumeFixtures::singleColumnPdf(['Carlo Bautista', 'carlo.bautista.test@example.com', '', 'Work Experience', 'Sales Associate, Mega Mall Inc., 2019 - 2021', '- Assisted customers and handled cash register', '', 'Skills', 'Sales, Customer Service, Cash Handling']));
$r = $extractor->extract($single, 'pdf');
$sp = $processor->parseText($r['text'], [], $catalog);
check(!$r['needs_ocr'] && $r['method'] === 'pdfparser', 'text PDF read without OCR', $r['method']);
check(val($sp, 'full_name') === 'Carlo Bautista' && count($sp['experience']) === 1, 'single-column PDF parsed', [val($sp, 'full_name'), $sp['experience']]);

$two = tmpPdf(ResumeFixtures::twoColumnFlippedPdf(
    ['Liza Garcia', 'Administrative Assistant', '', 'Professional Experience', 'Administrative Assistant', 'Golden Harvest Trading Corp.', 'January 2021 - Present', '- Encoded sales reports in MS Excel', '- Managed filing and records', '', 'Education', 'Bachelor of Science in Office Administration', 'Wesleyan University Philippines', '2016 - 2020'],
    ['Contact', 'liza.garcia.test@example.com', '0918 222 3344', 'Gapan City, Nueva Ecija', '', 'Skills', 'MS Office', 'Data Entry', 'Records Management', 'Customer Service']
));
$r2 = $extractor->extract($two, 'pdf');
$lines = array_values(array_filter(array_map('trim', explode("\n", $r2['text']))));
$posName = array_search('Liza Garcia', $lines, true);
$posTitle = array_search('Administrative Assistant', $lines, true);
check($posName !== false && $posTitle !== false && $posName < $posTitle, 'flipped coordinate system read top-to-bottom', array_slice($lines, 0, 6));
check(str_contains($r2['text'], ResumeTextExtractor::COLUMN_BREAK), 'two columns detected');
$tp = $processor->parseText($r2['text'], [], $catalog);
check(val($tp, 'email') === 'liza.garcia.test@example.com' && val($tp, 'city') === 'Gapan City', 'sidebar contact details', [val($tp, 'email'), val($tp, 'city')]);
check(($tp['experience'][0]['company'] ?? null) === 'Golden Harvest Trading Corp.' && $tp['experience'][0]['is_current'], 'main column experience', $tp['experience']);
check(in_array('Data Entry', skillNames($tp), true) && in_array('MS Office', skillNames($tp), true), 'sidebar skills', skillNames($tp));
check(!in_array('Liza Garcia', skillNames($tp), true), 'column text does not leak into another section');

$table = tmpPdf(ResumeFixtures::tablePdf([['Name', 'Noel Ramos'], ['Email', 'noel.ramos.test@example.com'], ['Position Desired', 'Warehouse Staff']]));
$r3 = $extractor->extract($table, 'pdf');
check(str_contains($r3['text'], 'Email | noel.ramos.test@example.com'), 'table cells kept on one row', $r3['text']);

$scan = tmpPdf(ResumeFixtures::scannedPdf());
$r4 = $extractor->extract($scan, 'pdf');
check($r4['needs_ocr'] === true && $r4['text'] === '', 'scanned PDF flagged for OCR');

$errors = [
    'corrupted' => ResumeFixtures::singleColumnPdf(['x']) === '' ? '' : substr(ResumeFixtures::singleColumnPdf(['Some text']), 0, 120),
    'not a pdf' => "PK\x03\x04 this is a zip file renamed to pdf",
];
foreach ($errors as $label => $bytes) {
    $path = tmpPdf($bytes);
    try {
        $extractor->extract($path, 'pdf');
        check(false, "$label rejected");
    } catch (ResumeExtractionException $e) {
        check(in_array($e->reason, ['corrupted', 'unavailable'], true), "$label rejected with clear message", $e->getMessage());
    }
}
$long = tmpPdf(ResumeFixtures::manyPagesPdf(11));
try {
    $extractor->extract($long, 'pdf');
    check(false, 'page limit enforced');
} catch (ResumeExtractionException $e) {
    check($e->reason === 'too_many_pages', 'page limit enforced', $e->getMessage());
}
$empty = tmpPdf('');
try { $extractor->extract($empty, 'pdf'); check(false, 'empty file rejected'); } catch (ResumeExtractionException $e) { check($e->reason === 'empty', 'empty file rejected'); }

echo "Skill normalization\n";
check(SkillNormalizer::canonicalize('MS Excel (Advanced)') === 'Microsoft Excel', 'decorated alias');
check(SkillNormalizer::canonicalize('ReactJS') === 'React', 'framework alias');
check(SkillNormalizer::related('Cashiering', 'Sales') && !SkillNormalizer::related('Teamwork', 'Sales'), 'transferable vs soft skills');
check(!isset(SkillNormalizer::findInText('Following instructions from the hosting team')['Teaching']), 'no false positives from prose');

echo "Skills are never dates, numbers or ratings\n";
$s = $processor->parseText(<<<TXT
Ana Reyes
SKILLS
Bookkeeping | 5 years
MS Excel | Advanced
Adobe Photoshop 95%
Canva ★★★★★
Driving (8 years)
Welding (SMAW)
2019
March 2019 - Present
Computer literate (MS Word, Excel, PowerPoint)
QuickBooks, Illustrator, Figma
Project Management
Basic knowledge in C++ and Java
TXT, [], $catalog);
$names = skillNames($s);
$bad = array_values(array_filter($names, fn($n) => preg_match('/\b(19|20)\d{2}\b|\d\s*%|\d+\s*years?|[★●]|^(advanced|present)$/iu', $n) || substr_count($n, '(') !== substr_count($n, ')')));
check(!$bad, 'no years, durations, percentages, ratings or cut-off brackets', $bad);
foreach (['Bookkeeping', 'MS Excel', 'Adobe Photoshop', 'Canva', 'Driving', 'Welding (SMAW)', 'Computer literate', 'MS Word', 'PowerPoint', 'QuickBooks', 'Illustrator', 'Figma', 'Project Management', 'C++', 'Java'] as $want) {
    check(in_array($want, $names, true), "keeps \"$want\"", $names);
}
check(empty($s['projects']), '"Project Management" is a skill, not a Projects heading', $s['projects']);

echo "Headings with dates, compound and look-alike headings\n";
$h = $processor->parseText(<<<TXT
Mark Anthony Villanueva
markanthony.villanueva@example.com
Skills & Interests
Technical: Payroll, SAP
Interests: Basketball, Chess
PROFESSIONAL EXPERIENCE (2015 - 2024)
Payroll Officer, Northern Rice Mills Inc.
March 2019 - March 2024
Software Developer
Bright Ideas Technologies
June 2015 - February 2019
TRAININGS
Food Safety Seminar - DOH, 2021
Leadership Training - 2022
TECHNICAL SKILLS
Languages: Java, PHP
TXT, [], $catalog);
check(val($h, 'full_name') === 'Mark Anthony Villanueva', 'surname containing "nueva" is a name', val($h, 'full_name'));
check(count($h['experience']) === 2 && $h['experience'][1]['company'] === 'Bright Ideas Technologies', 'dated heading recognised; "...Technologies" employer is not a heading', array_map(fn($e) => [$e['title'], $e['company']], $h['experience']));
check(in_array('Payroll', skillNames($h), true) && !in_array('Basketball', skillNames($h), true), '"Skills & Interests" is skills; interests are not skills', skillNames($h));
check(count($h['trainings']) === 2, 'training items ending in "Seminar"/"Training" stay items', array_column($h['trainings'], 'name'));
check(in_array('Java', skillNames($h), true) && !in_array('Java', array_column($h['languages'], 'name'), true), '"Languages: Java, PHP" are technical skills', [skillNames($h), $h['languages']]);

echo "Forms, tables and narrow columns\n";
$f = $processor->parseText(<<<TXT
MARIA CLARA D. REYES, RN
Birthday | : | August 3, 1990
Sex | : | Female
mariaclara.reyes@example.co
m
Brgy. Kapitan Pepe, Cabanatuan City
EDUCATIONAL BACKGROUND
Level | School | Course | Year
Tertiary | Wesleyan University Philippines | Bachelor of Science in Nursing | 2013 - 2017
Secondary | Talavera National High School | 2009 - 2013
WORK EXPERIENCE
Position | Company / Agency | Inclusive Dates
Staff Nurse | Nueva Ecija Medical Center | Feb 2020 - Present
Nursing Attendant | Premiere Medical Center | 2017 - 2019
ON-THE-JOB TRAINING
IT Support Intern | Gapan City Hall | February - May 2023
LANGUAGES
Mother tongue(s) | Filipino
Other language(s) | English
TXT, [], $catalog);
check(val($f, 'full_name') === 'Maria Clara D. Reyes' && val($f, 'last_name') === 'Reyes', 'credentials after the name removed', [val($f, 'full_name'), val($f, 'last_name')]);
check(val($f, 'birth_date') === '1990-08-03' && val($f, 'sex') === 'female', 'tab-aligned "Label | : | value" rows', [val($f, 'birth_date'), val($f, 'sex')]);
check(val($f, 'email') === 'mariaclara.reyes@example.com', 'email wrapped onto the next line rejoined', val($f, 'email'));
check(array_column($f['education'], 'level') === ['college', 'high_school'] && $f['education'][0]['degree'] === 'Bachelor of Science in Nursing', 'education table rows (header row ignored)', $f['education']);
check(count($f['experience']) === 3 && $f['experience'][0]['company'] === 'Nueva Ecija Medical Center' && $f['experience'][1]['title'] === 'Nursing Attendant', 'one job per table row', array_map(fn($e) => [$e['title'], $e['company']], $f['experience']));
check(($f['experience'][2]['start_date'] ?? null) === '2023-02' && ($f['experience'][2]['end_date'] ?? null) === '2023-05', '"February - May 2023" range', $f['experience'][2] ?? null);
check(array_column($f['languages'], 'name') === ['English', 'Filipino'] || array_column($f['languages'], 'name') === ['Filipino', 'English'], 'Europass mother tongue / other languages', $f['languages']);

echo "\n$passes passed, $failures failed\n";
exit($failures > 0 ? 1 : 0);
