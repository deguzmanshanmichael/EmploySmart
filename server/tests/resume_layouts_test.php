<?php
/**
 * Resume layout regression tests (no database needed).
 * Real-world template styles — Canva/Chrome PDFs, Word PDFs and DOCX files with layout
 * tables, text boxes and page headers — all with fictional people.
 * Run: php server/tests/resume_layouts_test.php   (DOCX cases need the zip extension)
 */
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../services/resume/ResumeProcessor.php';

putenv('RESUME_USE_PDFTOTEXT=0'); // the pure-PHP path used on shared hosting
putenv('RESUME_AI_ENABLED=false');

$failures = 0;
$passes = 0;
function check(bool $ok, string $label, $detail = null) {
    global $failures, $passes;
    if ($ok) { $passes++; echo "  ok   $label\n"; return; }
    $failures++;
    echo "  FAIL $label" . ($detail !== null ? ' :: ' . json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : '') . "\n";
}

/** Text that is not a skill: dates, years, durations, ratings, contact details, levels. */
function junkReason(string $skill, string $person): ?string {
    if (!mb_check_encoding($skill, 'UTF-8')) return 'invalid UTF-8';
    if (preg_match('/\b(19|20)\d{2}\b/', $skill)) return 'year';
    if (preg_match('/^[\d\s.,:\/\-–%+()]+$/u', $skill)) return 'number';
    if (preg_match('/\d+\s*\+?\s*(?:years?|yrs?|months?)\b/i', $skill)) return 'duration';
    if (preg_match('/\d+\s*%|[★☆●○■□]/u', $skill)) return 'rating';
    if (preg_match('/@|https?:|www\./i', $skill)) return 'contact detail';
    if (preg_match('/^(advanced|intermediate|beginner|basic|expert|native|fluent)$/i', $skill)) return 'level';
    if (substr_count($skill, '(') !== substr_count($skill, ')')) return 'cut-off bracket';
    if (mb_strtolower($skill) === mb_strtolower($person)) return "the person's name";
    return null;
}

$cases = [
    'canva_sidebar.pdf' => ['Canva-style sidebar (Chrome PDF)', 'Maria L. Santos', 'maria.santos.demo@example.com', '+63 917 555 0142',
        ['Customer Service', 'Cash Handling', 'Inventory Management', 'MS Excel', 'Visual Merchandising'], [['Sales Associate', 'SM Supermarket'], ['Cashier', 'Puregold Price Club Inc.']], ['college']],
    'timeline_years.pdf' => ['Timeline with year column and emoji icons', 'Ana Cruz', 'ana.cruz.demo@example.com', '0998 555 0123',
        ['Bookkeeping', 'QuickBooks', 'MS Excel', 'Payroll Processing'], [['Bookkeeper', 'Golden Grains Trading'], ['Accounting Clerk', 'Nueva Ecija Electric Cooperative']], ['college']],
    'europass_labels.pdf' => ['Europass label column', 'Grace T. Bautista', 'grace.bautista.demo@example.com', '+63 939 555 0155',
        ['Patient care', 'wound care', 'infection control', 'basic life support', 'Microsoft Office'], [['Staff Nurse', 'Dr. Paulino J. Garcia Memorial Research and Medical Center'], ['Clinic Nurse', 'Holy Rosary Clinic']], ['college']],
    'linkedin_export.pdf' => ['LinkedIn export', 'Jose Ramos', 'jose.ramos.demo@example.com', '0917 555 0177',
        ['PHP', 'MySQL', 'Laravel'], [['Web Developer', 'Acme Software Solutions Inc.'], ['Junior Programmer', 'Bright Ideas Technologies']], ['college']],
    'headings_with_years.pdf' => ['Headings containing years', 'Mark Anthony Villanueva', 'markanthony.villanueva.demo@example.com', '0908 555 0187',
        ['Payroll', 'SAP', 'Bookkeeping', 'MS Excel', 'Government Remittances'], [['Payroll Officer', 'Northern Rice Mills Inc.'], ['Payroll Clerk', 'Gapan Hardware Corporation']], ['college']],
    'skills_ratings.pdf' => ['Skill percentages and star ratings', 'Kristine Lopez', 'kristine.lopez.demo@example.com', '0917 555 0109',
        ['Adobe Photoshop', 'Illustrator', 'Typography', 'Canva', 'Figma', 'Video Editing'], [['Graphic Designer', 'Bright Ideas Studio'], ['Layout Artist', 'Nueva Ecija Daily News']], ['college']],
    'ho_it_developer.pdf' => ['Technical skills with sub-labels', 'Alexander R. Cruz', 'alex.cruz.demo@example.com', '+63 917 555 0170',
        ['JavaScript', 'PHP', 'React', 'Laravel', 'MySQL', 'PostgreSQL', 'Git'], [['Senior Web Developer', 'PixelForge Technologies Inc.'], ['Web Developer', 'Codewave Solutions']], ['college']],
    'docx_table_layout.word.pdf' => ['Word two-column table (PDF)', 'Liza M. Villanueva', 'liza.villanueva.demo@example.com', '0917 555 0161',
        ['Microsoft Excel', 'Bookkeeping', 'Payroll Processing', 'BIR Tax Filing'], [['Accounting Staff', 'Golden Harvest Trading Corp.'], ['Accounting Clerk', 'Nueva Ecija Electric Cooperative']], ['college']],
    'docx_table_layout.docx' => ['Word two-column table (DOCX)', 'Liza M. Villanueva', 'liza.villanueva.demo@example.com', '0917 555 0161',
        ['Microsoft Excel', 'Bookkeeping', 'Payroll Processing', 'BIR Tax Filing'], [['Accounting Staff', 'Golden Harvest Trading Corp.'], ['Accounting Clerk', 'Nueva Ecija Electric Cooperative']], ['college']],
    'docx_textbox_sidebar.docx' => ['Word text-box sidebar (DOCX)', 'Noel B. Castillo', 'noel.castillo.demo@example.com', '0926 555 0133',
        ['Welding (SMAW)', 'Metal Fabrication', 'Blueprint Reading', 'Occupational Safety'], [['Welder', 'Steelworks Fabrication Inc.'], ['Welder Helper', 'Nueva Ecija Metal Works']], ['vocational', 'high_school']],
    'docx_header_contact.docx' => ['Name and contact in the page header (DOCX)', 'Paolo S. Reyes', 'paolo.reyes.demo@example.com', '0917 555 0164',
        ['Customer Service', 'Cash Handling', 'MS Excel', 'POS Operation', 'Inventory', 'Teamwork'], [['Sales Associate', 'SM Supermarket'], ['Promodiser', 'Universal Robina Corporation']], ['college']],
    'ho_docx_biodata_tabs.docx' => ['Bio-data with tab-aligned labels and tables (DOCX)', 'Rommel S. Garcia', 'rommel.garcia.demo@example.com', '0947 555 0150',
        ['Machine operation', 'Preventive maintenance', 'Forklift operation', 'Basic welding'], [['Machine Operator', 'Northern Rice Mills Inc.'], ['Factory Worker', 'Muñoz Agro Industrial Corp.']], ['high_school', 'vocational']],
];

$processor = new ResumeProcessor();
foreach ($cases as $file => [$label, $name, $email, $phone, $skills, $jobs, $levels]) {
    echo "$label\n";
    $path = __DIR__ . '/fixtures/layouts/' . $file;
    $extension = pathinfo($file, PATHINFO_EXTENSION);
    if ($extension === 'docx' && !class_exists('ZipArchive')) {
        echo "  skip (the PHP zip extension is not enabled here; run with -d extension=zip)\n";
        continue;
    }
    $text = (new ResumeTextExtractor(10))->extract($path, $extension)['text'];
    $p = $processor->parseText($text, [], [['id' => 1, 'skill_name' => 'Computer Skills']]);
    $v = fn($k) => $p['personal'][$k]['value'] ?? null;
    check($v('full_name') === $name, 'name', $v('full_name'));
    check($v('email') === $email, 'email', $v('email'));
    check(preg_replace('/\D/', '', (string)$v('phone')) === preg_replace('/\D/', '', $phone), 'phone', $v('phone'));
    $found = array_map(fn($s) => mb_strtolower($s['name']), $p['skills']);
    $missing = array_values(array_filter($skills, fn($s) => !in_array(mb_strtolower($s), $found, true)));
    check(!$missing, 'listed skills found', $missing);
    $junk = [];
    foreach ($p['skills'] as $s) {
        if (($why = junkReason($s['name'], $name)) !== null) $junk[] = $s['name'] . " ($why)";
    }
    check(!$junk, 'no dates, numbers, ratings or names among the skills', $junk);
    $gotJobs = array_map(fn($e) => [$e['title'], $e['company']], $p['experience']);
    $jobsOk = count($gotJobs) === count($jobs);
    foreach ($jobs as $i => [$title, $company]) {
        $jobsOk = $jobsOk && mb_strtolower((string)$gotJobs[$i][0]) === mb_strtolower($title) && str_starts_with(mb_strtolower((string)$gotJobs[$i][1]), mb_strtolower($company));
    }
    check($jobsOk, 'work experience entries', $gotJobs);
    $gotLevels = array_map(fn($e) => $e['level'], $p['education']);
    check($gotLevels === $levels, 'education levels', $gotLevels);
}

echo "\nStored skill lists\n";
$cleaned = SkillNormalizer::cleanList(['2019', 'March 2019 - Present', '5 years', 'Advanced', '95%', 'Driving (8 years)', 'MS Excel - Advanced', 'Canva ★★★★★', 'QuickBooks', 'Bookkeeping', 'bookkeeping', ['name' => '2024'], ['name' => 'Welding (SMAW)']]);
$names = array_map(fn($s) => is_array($s) ? $s['name'] : $s, $cleaned);
check($names === ['Driving', 'MS Excel', 'Canva', 'QuickBooks', 'Bookkeeping', 'Welding (SMAW)'], 'years, durations, levels and ratings removed; distinct skills kept', $names);

echo "\n$passes passed, $failures failed\n";
exit($failures ? 1 : 0);
