<?php
require_once __DIR__ . '/../services/JobMatchingService.php';

$service = (new ReflectionClass(JobMatchingService::class))->newInstanceWithoutConstructor();
$scoreMethod = new ReflectionMethod(JobMatchingService::class, 'scoreJob');
$scoreMethod->setAccessible(true);
$educationMethod = new ReflectionMethod(JobMatchingService::class, 'educationScore');
$educationMethod->setAccessible(true);

$user = ['education_level' => 'college', 'employment_status' => 'student'];
$job = ['education_required' => 'high_school', 'experience_level' => 'entry', 'job_type' => 'parttime'];
$matchedScore = $scoreMethod->invoke($service, $user, $job, [10, 20], [20], [10, 20]);
if ($matchedScore !== 92.5) {
    throw new RuntimeException('Expected a 92.5 score for matched skills/training and student part-time fit; got ' . $matchedScore);
}

$job['job_type'] = 'part_time';
$aliasScore = $scoreMethod->invoke($service, $user, $job, [10, 20], [20], [10, 20]);
if ($aliasScore !== $matchedScore) {
    throw new RuntimeException('Underscored job type did not normalize to the database enum value.');
}

$job['job_type'] = 'fulltime';
$fulltimeScore = $scoreMethod->invoke($service, $user, $job, [10, 20], [20], [10, 20]);
if ($fulltimeScore >= $matchedScore) {
    throw new RuntimeException('Student part-time preference did not rank above full-time.');
}

$education = $educationMethod->invoke($service, 'college', 'high_school');
if ((float)$education !== 1.0) {
    throw new RuntimeException('Stored high_school education enum was not parsed correctly.');
}

$job['job_type'] = 'parttime';
$skillFreeScore = $scoreMethod->invoke($service, $user, $job, [10, 20], [20], []);
if ($skillFreeScore !== 72.5) {
    throw new RuntimeException('Jobs without tagged skills should receive neutral skill evidence; got ' . $skillFreeScore);
}

$eligibleMethod = new ReflectionMethod(JobMatchingService::class, 'isEligibleJob');
$eligibleMethod->setAccessible(true);
$eligibleJob = ['approval_status' => 'approved', 'archived' => 0, 'deadline' => date('Y-m-d', strtotime('-1 day'))];
if (!$eligibleMethod->invoke($service, $eligibleJob)) {
    throw new RuntimeException('Approved non-archived jobs must remain recommendable regardless of deadline.');
}
$archivedJob = ['approval_status' => 'approved', 'archived' => 1];
if ($eligibleMethod->invoke($service, $archivedJob)) {
    throw new RuntimeException('Archived jobs must not be recommended.');
}

echo "JobMatchingService scoring tests passed.\n";
