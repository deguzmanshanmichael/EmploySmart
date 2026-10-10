<?php
/**
 * Resume matching rules (no database needed).
 * Run: php server/tests/resume_matching_test.php
 */
require_once __DIR__ . '/../services/resume/ResumeMatchingService.php';

$failures = 0; $passes = 0;
function check(bool $ok, string $label, $detail = null) {
    global $failures, $passes;
    if ($ok) { $passes++; echo "  ok   $label\n"; return; }
    $failures++;
    echo "  FAIL $label" . ($detail !== null ? ' :: ' . json_encode($detail, JSON_UNESCAPED_UNICODE) : '') . "\n";
}

$service = (new ReflectionClass(ResumeMatchingService::class))->newInstanceWithoutConstructor();
$profileOf = (new ReflectionMethod(ResumeMatchingService::class, 'resumeProfile'));
$profileOf->setAccessible(true);
$score = new ReflectionMethod(ResumeMatchingService::class, 'scoreJob');
$score->setAccessible(true);
$job = fn(array $o) => $o + ['id' => 1, 'title' => '', 'description' => '', 'requirements' => '', 'location' => '', 'industry' => '', 'skills' => [], 'job_type' => 'fulltime', 'experience_level' => 'entry', 'education_required' => '', 'company_name' => 'Test Employer', 'salary_range' => null, 'vacancies' => 1, 'deadline' => null, 'created_at' => '2026-01-01'];

$electrician = $profileOf->invoke($service, [
    'skills' => [['name' => 'Electrical Wiring'], ['name' => 'Driving'], ['name' => 'Troubleshooting']],
    'experience' => [['title' => 'Electrician', 'company' => 'Metro Builders Corp.', 'start_date' => '2019-06', 'is_current' => true, 'responsibilities' => ['Installed electrical wiring']]],
    'total_experience_months' => 60, 'education_level' => 'college',
    'education' => [['degree' => 'Bachelor of Science in Electrical Engineering', 'field_of_study' => 'Electrical Engineering']],
    'certifications' => [['name' => 'Registered Electrical Engineer (PRC License)']], 'city' => 'Cabanatuan City', 'province' => 'Nueva Ecija',
]);

echo "Credentials\n";
$driver = $score->invoke($service, $job(['title' => 'Professional Driver', 'requirements' => "Professional Driver's License (PDL), 2+ years driving experience", 'location' => 'Cabanatuan City, Nueva Ecija']), $electrician, 0.1);
$gaps = array_column(array_filter($driver['missing_qualifications'] ?? [], fn($g) => $g['type'] === 'credential'), 'label');
check($driver !== null && $gaps && !str_contains($driver['explanation'], 'PRC License'), "an engineering license does not satisfy a driver's license", $driver);
check(($driver['match_score'] ?? 100) <= 60, 'missing mandatory license caps the estimate', $driver['match_score'] ?? null);

$nurse = $score->invoke($service, $job(['title' => 'Registered Nurse', 'requirements' => 'Bachelor\'s degree in Nursing, Licensed Registered Nurse, 1+ years experience']), $electrician, 0.05);
check($nurse === null || (($nurse['match_score'] <= 50) && !str_contains($nurse['explanation'], 'License')), 'a PRC license in another field does not satisfy "Licensed Registered Nurse"', $nurse);

$engineerJob = $score->invoke($service, $job(['title' => 'Electrical Engineer', 'requirements' => 'Bachelor\'s degree in Electrical Engineering, Licensed Electrical Engineer, 2+ years experience in electrical installation']), $electrician, 0.3);
check($engineerJob && str_contains($engineerJob['explanation'], 'Registered Electrical Engineer (PRC License)') && !array_filter($engineerJob['missing_qualifications'], fn($g) => $g['type'] === 'credential'), 'a license in the same field is recognised', $engineerJob);
check($engineerJob && str_contains($engineerJob['explanation'], 'Electrical Engineering match the field'), 'field of study matched', $engineerJob['explanation'] ?? null);

echo "Evidence and honesty\n";
$empty = $profileOf->invoke($service, ['skills' => [], 'experience' => [], 'education' => [], 'certifications' => []]);
$none = $score->invoke($service, $job(['title' => 'Software Architect', 'requirements' => 'Bachelor\'s degree in Computer Science, 5+ years experience, Java, SQL']), $empty, 0.0);
check($none === null, 'no evidence → not recommended');
$cashier = $profileOf->invoke($service, ['skills' => [['name' => 'Cashiering'], ['name' => 'Customer Service']], 'experience' => [['title' => 'Cashier', 'company' => 'QuickMart']], 'total_experience_months' => null, 'education_level' => 'senior_high', 'education' => [], 'certifications' => []]);
$sales = $score->invoke($service, $job(['title' => 'Sales Associate', 'requirements' => 'High school diploma, 1+ years sales experience, customer service skills', 'experience_level' => 'entry']), $cashier, 0.2);
check($sales && in_array('Customer Service', $sales['matched_skills'], true), 'direct skill match', $sales['matched_skills'] ?? null);
check($sales && array_filter($sales['missing_qualifications'], fn($g) => $g['type'] === 'experience' && str_contains($g['detail'], 'do not show how long')), 'years not claimed when resume dates are missing', $sales['missing_qualifications'] ?? null);
check($sales && str_contains($sales['explanation'], 'Cashier at QuickMart is in a related field to this Sales Associate role'), 'partial title relationship is not overstated', $sales['explanation'] ?? null);
$collegeJob = $score->invoke($service, $job(['title' => 'Cashier', 'requirements' => 'College graduate, cash handling', 'education_required' => 'college']), $cashier, 0.2);
check($collegeJob === null || array_filter($collegeJob['missing_qualifications'], fn($g) => $g['type'] === 'education'), 'education below requirement is listed as a gap', $collegeJob);
check($collegeJob === null || !str_contains($collegeJob['explanation'], 'meets the college'), 'never claims an unmet education requirement');

echo "\n$passes passed, $failures failed\n";
exit($failures > 0 ? 1 : 0);
