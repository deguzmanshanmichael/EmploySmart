<?php
/**
 * End-to-end test of the resume workflow over HTTP against a real database.
 *
 * It creates its own temporary users, employer and job postings, runs the full
 * upload → review → confirm → recommend → delete flow, and removes everything it
 * created afterwards. Never run it against production.
 *
 *   php -S 127.0.0.1:8099 server/index.php          (in one terminal, repository root)
 *   php server/tests/resume_api_integration_test.php [http://127.0.0.1:8099/api]
 */
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../services/resume/ResumeProcessor.php';
require_once __DIR__ . '/fixtures/ResumeFixtures.php';

if (strtolower((string)env('APP_ENV', 'development')) === 'production') {
    fwrite(STDERR, "Refusing to run against APP_ENV=production\n");
    exit(2);
}
$base = rtrim($argv[1] ?? 'http://127.0.0.1:8099/api', '/');
$db = getDB();
$failures = 0; $passes = 0;
function check(bool $ok, string $label, $detail = null) {
    global $failures, $passes;
    if ($ok) { $passes++; echo "  ok   $label\n"; return; }
    $failures++;
    echo "  FAIL $label" . ($detail !== null ? ' :: ' . substr(json_encode($detail, JSON_UNESCAPED_UNICODE), 0, 600) : '') . "\n";
}
function http(string $method, string $url, ?string $token, $body = null, array $file = null): array {
    $ch = curl_init($url);
    $headers = ['X-Requested-With: XMLHttpRequest'];
    if ($token) $headers[] = 'Authorization: Bearer ' . $token;
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 120]);
    if ($file) {
        $path = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($path, $file['bytes']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['resume' => new CURLFile($path, $file['mime'], $file['name'])]);
    } elseif ($body !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $type = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    return ['status' => $status, 'json' => json_decode((string)$raw, true), 'raw' => $raw, 'type' => $type];
}
function q(string $sql, string $types = '', ...$params) {
    global $db;
    $stmt = $db->prepare($sql);
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : $db->insert_id;
}

$tag = 'rsmtest' . bin2hex(random_bytes(3));
$created = ['users' => [], 'jobs' => [], 'employers' => []];
$hash = password_hash('Test-Password-123', PASSWORD_BCRYPT);
$mkUser = function (string $role, string $first, string $last) use ($tag, $hash, &$created) {
    $email = "$tag.$first@example.com";
    $id = q("INSERT INTO users (first_name, last_name, sex, email, password, role, is_verified) VALUES (?,?,?,?,?,?,0)", 'ssssss', $first, $last, 'female', $email, $hash, $role);
    $created['users'][] = $id;
    return [$id, generateJWT(['sub' => $id, 'email' => $email, 'role' => $role, 'name' => "$first $last"])];
};

try {
    [$uid, $token] = $mkUser('jobseeker', 'Liza', 'Garcia');
    [$otherId, $otherToken] = $mkUser('jobseeker', 'Other', 'Seeker');
    [$empUserId, $empToken] = $mkUser('employer', 'Owner', 'Employer');
    $employerId = q("INSERT INTO employers (user_id, company_name, industry, verification_status) VALUES (?,?,?, 'approved')", 'iss', $empUserId, "$tag Trading Corp", 'Retail');
    $created['employers'][] = $employerId;
    $future = date('Y-m-d', strtotime('+30 days'));
    $past = date('Y-m-d', strtotime('-3 days'));
    $mkJob = function (string $title, string $req, string $desc, string $edu, string $status, int $archived, ?string $deadline, string $location = 'Gapan City, Nueva Ecija') use ($employerId, &$created) {
        $id = q("INSERT INTO jobs (employer_id, title, description, requirements, location, job_type, experience_level, education_required, approval_status, archived, deadline) VALUES (?,?,?,?,?,'fulltime','entry',?,?,?,?)",
            'issssssis', $employerId, $title, $desc, $req, $location, $edu, $status, $archived, $deadline);
        $created['jobs'][] = $id;
        return $id;
    };
    $jobOpen = $mkJob("$tag Administrative Assistant", 'Bachelor\'s degree in Office Administration or Business, 1+ years administrative experience, proficient in MS Excel and data entry, records management', 'Encode reports, manage filing and records, assist customers at the front desk.', 'college', 'approved', 0, $future);
    $jobLicense = $mkJob("$tag Registered Nurse", 'Bachelor\'s degree in Nursing, Licensed Registered Nurse (PRC), 2+ years hospital experience, data entry of patient records', 'Provide patient care and maintain records.', 'college', 'approved', 0, $future);
    $jobExpired = $mkJob("$tag Data Encoder (expired)", 'MS Excel, data entry, records management', 'Encode records.', 'high_school', 'approved', 0, $past);
    $jobPending = $mkJob("$tag Office Clerk (pending)", 'MS Excel, data entry, records management', 'Encode records.', 'high_school', 'pending', 0, $future);
    $jobArchived = $mkJob("$tag Records Clerk (archived)", 'MS Excel, data entry, records management', 'Encode records.', 'high_school', 'approved', 1, $future);

    echo "Access control & empty states\n";
    $r = http('GET', "$base/resume-recommendations/me", $token);
    check($r['status'] === 200 && $r['json']['data']['status'] === 'no_resume' && $r['json']['data']['label'] === 'Based on Your Uploaded Resume', 'no resume → explanatory empty state with label', $r['json']);
    check(http('GET', "$base/resume-profile", null)['status'] === 401, 'unauthenticated request rejected');
    check(http('GET', "$base/resume-profile", $empToken)['status'] === 403, 'non-jobseeker cannot use resume profile');
    check(http('GET', "$base/resume-recommendations/$uid", $otherToken)['status'] === 403, "another jobseeker cannot read someone's recommendations");

    echo "Upload validation\n";
    $r = http('POST', "$base/resume-profile/upload", $token, null, ['bytes' => 'plain text resume', 'mime' => 'text/plain', 'name' => 'resume.txt']);
    check($r['status'] === 415, 'unsupported type rejected', $r['json']);
    $r = http('POST', "$base/resume-profile/upload", $token, null, ['bytes' => "%PDF-1.4\n1 0 obj << /Type /Catalog >>\ngarbage", 'mime' => 'application/pdf', 'name' => 'broken.pdf']);
    check($r['status'] === 422 && ($r['json']['errors']['reason'] ?? '') === 'corrupted', 'corrupted PDF rejected with clear error', $r['json']);
    $r = http('POST', "$base/resume-profile/upload", $token, null, ['bytes' => ResumeFixtures::manyPagesPdf(11), 'mime' => 'application/pdf', 'name' => 'long.pdf']);
    check($r['status'] === 422 && ($r['json']['errors']['reason'] ?? '') === 'too_many_pages', 'page limit enforced', $r['json']);
    check(q("SELECT COUNT(*) c FROM resume_profiles WHERE user_id = ?", 'i', $uid)[0]['c'] == 0, 'rejected uploads leave no records');

    echo "Scanned resume → OCR text\n";
    $r = http('POST', "$base/resume-profile/upload", $token, null, ['bytes' => ResumeFixtures::scannedPdf(), 'mime' => 'application/pdf', 'name' => 'scan.pdf']);
    check($r['status'] === 200 && $r['json']['data']['resume']['status'] === 'needs_ocr', 'scanned PDF stored and flagged for OCR', $r['json']);
    $scanPath = q("SELECT file_path FROM resume_profiles WHERE user_id = ?", 'i', $uid)[0]['file_path'];
    check(str_starts_with($scanPath, 'private/resumes/') && ResumeProcessor::resolvePath($scanPath) !== null, 'file stored in private storage', $scanPath);
    $r = http('POST', "$base/resume-profile/ocr", $token, ['text' => ResumeFixtures::PH_UNUSUAL_HEADINGS]);
    $ex = $r['json']['data']['extracted'] ?? [];
    check($r['status'] === 200 && $r['json']['data']['resume']['extraction_method'] === 'ocr_browser', 'OCR text accepted', $r['json']['message'] ?? null);
    check(!str_contains(json_encode($ex), '"confidence":"high"'), 'OCR-derived values are never marked high confidence');

    echo "Replace with a two-column PDF\n";
    $pdf = ResumeFixtures::twoColumnFlippedPdf(
        ['Liza Garcia', 'Administrative Assistant', '', 'Professional Experience', 'Administrative Assistant', 'Golden Harvest Trading Corp.', 'January 2021 - Present', '- Encoded sales reports in MS Excel and handled data entry', '- Managed filing and records management', 'Office Clerk', 'Sunrise Services Inc.', 'June 2019 - December 2020', '- Assisted customers at the front desk', '', 'Education', 'Bachelor of Science in Office Administration', 'Wesleyan University Philippines', '2015 - 2019'],
        ['Contact', "$tag.liza@example.com", '0918 222 3344', 'Gapan City, Nueva Ecija', '', 'Skills', 'MS Excel', 'Data Entry', 'data entry', 'Records Management', 'Customer Service', 'Typing']
    );
    $r = http('POST', "$base/resume-profile/upload", $token, null, ['bytes' => $pdf, 'mime' => 'application/pdf', 'name' => 'liza.pdf']);
    $data = $r['json']['data'] ?? [];
    check($r['status'] === 200 && $data['resume']['status'] === 'parsed', 'text PDF parsed', $r['json']['message'] ?? $r['raw']);
    check(ResumeProcessor::resolvePath($scanPath) === null, 'replaced file deleted from storage');
    $ex = $data['extracted'];
    check(($ex['personal']['full_name']['value'] ?? '') === 'Liza Garcia' && ($ex['personal']['phone']['value'] ?? '') === '0918 222 3344', 'personal details extracted', $ex['personal'] ?? null);
    check(count($ex['experience']) === 2 && $ex['total_experience_months'] >= 60, 'two jobs and total experience', array_map(fn($e) => [$e['title'], $e['company'], $e['start_date'], $e['end_date']], $ex['experience']));
    $skillNames = array_map(fn($s) => $s['name'], $ex['skills']);
    check(count(array_filter($skillNames, fn($n) => strtolower($n) === 'data entry')) === 1, 'duplicate skills merged', $skillNames);
    check(http('GET', "$base/resume-recommendations/me", $token)['json']['data']['status'] === 'needs_review', 'no recommendations before the jobseeker confirms');

    echo "Confirmation & profile synchronization\n";
    q("UPDATE users SET phone = '09170000000' WHERE id = ?", 'i', $uid);
    $resume = [
        'full_name' => 'Liza Garcia', 'email' => "$tag.liza@example.com", 'phone' => '0918 222 3344', 'location' => 'Gapan City, Nueva Ecija', 'city' => 'Gapan City', 'province' => 'Nueva Ecija',
        'education_level' => 'college', 'education' => $ex['education'], 'experience' => $ex['experience'],
        'skills' => array_merge(array_map(fn($s) => ['name' => $s['name']], $ex['skills']), [['name' => 'DATA ENTRY'], ['name' => '<script>alert(1)</script>Filing']]),
        'certifications' => [], 'trainings' => [], 'languages' => [['name' => 'English', 'proficiency' => 'fluent']],
    ];
    $r = http('POST', "$base/resume-profile/confirm", $token, ['profile_updates' => ['phone' => 'not-a-phone'], 'resume' => $resume]);
    check($r['status'] === 422 && isset($r['json']['errors']['phone']), 'invalid profile value rejected', $r['json']);
    check(q("SELECT phone FROM users WHERE id = ?", 'i', $uid)[0]['phone'] === '09170000000' && q("SELECT status FROM resume_profiles WHERE user_id = ?", 'i', $uid)[0]['status'] === 'parsed', 'nothing saved when validation fails');

    $skillsBefore = (int)q("SELECT COUNT(*) c FROM user_skills WHERE user_id = ?", 'i', $uid)[0]['c'];
    $r = http('POST', "$base/resume-profile/confirm", $token, ['profile_updates' => ['city' => 'Gapan City', 'province' => 'Nueva Ecija', 'education_level' => 'college', 'address' => '', 'bio' => null], 'resume' => $resume]);
    check($r['status'] === 200 && $r['json']['data']['resume']['status'] === 'confirmed', 'confirmed', $r['json']['message'] ?? $r['raw']);
    $user = q("SELECT phone, city, province, education_level, address FROM users WHERE id = ?", 'i', $uid)[0];
    check($user['city'] === 'Gapan City' && $user['education_level'] === 'college' && $user['phone'] === '09170000000', 'only chosen fields updated; conflicting phone kept', $user);
    check($user['address'] === null, 'empty resume values never overwrite the profile');
    $rows = q("SELECT skill_id, COUNT(*) c FROM user_skills WHERE user_id = ? GROUP BY skill_id", 'i', $uid);
    check($rows && max(array_column($rows, 'c')) == 1, 'catalog skills added without duplicates', $rows);
    check(count($rows) > $skillsBefore, 'skills synchronized to user_skills');
    $confirmed = json_decode(q("SELECT confirmed_data FROM resume_profiles WHERE user_id = ?", 'i', $uid)[0]['confirmed_data'], true);
    check(!str_contains(json_encode($confirmed), '<script'), 'markup stripped from confirmed data');
    check(count(array_filter($confirmed['skills'], fn($s) => strtolower($s['name']) === 'data entry')) === 1, 'confirmed skills deduplicated');
    http('POST', "$base/resume-profile/confirm", $token, ['profile_updates' => [], 'resume' => $resume]);
    $rows2 = q("SELECT COUNT(*) c FROM user_skills WHERE user_id = ?", 'i', $uid)[0]['c'];
    check($rows2 == count($rows), 're-confirming does not duplicate skills');

    q("UPDATE users SET is_verified = 1 WHERE id = ?", 'i', $uid);
    $r = http('POST', "$base/resume-profile/confirm", $token, ['profile_updates' => ['first_name' => 'Elizabeth'], 'resume' => $resume]);
    check($r['status'] === 200 && isset($r['json']['data']['applied']['skipped']['first_name']) && q("SELECT first_name FROM users WHERE id = ?", 'i', $uid)[0]['first_name'] === 'Liza', 'verified name is not overwritten', $r['json']['data']['applied'] ?? null);

    echo "Recommendations\n";
    $r = http('GET', "$base/resume-recommendations/me", $token);
    $recs = $r['json']['data']['recommendations'] ?? [];
    $ids = array_column($recs, 'job_id');
    check($r['json']['data']['status'] === 'ok' && $r['json']['data']['label'] === 'Based on Your Uploaded Resume', 'label returned', $r['json']['data']['label'] ?? null);
    check(str_contains($r['json']['data']['basis'], 'skills, qualifications, education, and work experience identified in your uploaded resume'), 'basis explanation');
    check(str_contains($r['json']['data']['disclaimer'], 'not the probability of being hired'), 'estimate disclaimer');
    check(in_array($jobOpen, $ids, true), 'matching open job recommended', array_map(fn($x) => [$x['title'], $x['match_score']], $recs));
    check(!array_intersect([$jobExpired, $jobPending, $jobArchived], $ids), 'expired, pending and archived postings excluded');
    $existing = array_map('intval', array_column(q("SELECT id FROM jobs WHERE approval_status='approved' AND COALESCE(archived,0)=0 AND (deadline IS NULL OR deadline >= CURDATE())"), 'id'));
    check(!array_diff($ids, $existing), 'every recommendation is a real open posting');
    $open = array_values(array_filter($recs, fn($x) => $x['job_id'] === $jobOpen))[0] ?? null;
    if ($open) {
        $resumeSkillKeys = array_map(fn($s) => SkillNormalizer::key($s['name']), $confirmed['skills']);
        check($open['matched_skills'] && !array_diff(array_map(fn($s) => SkillNormalizer::key($s), $open['matched_skills']), $resumeSkillKeys), 'matched skills all come from the confirmed resume', $open['matched_skills']);
        check(str_contains($open['explanation'], 'Your resume shows') && str_contains($open['explanation'], 'Administrative Assistant at Golden Harvest Trading Corp.'), 'explanation grounded in resume and job', $open['explanation']);
        check($open['match_score'] > 0 && $open['match_score'] <= 100, 'score is a bounded estimate', $open['match_score']);
    }
    $nurse = array_values(array_filter($recs, fn($x) => $x['job_id'] === $jobLicense))[0] ?? null;
    if ($nurse) {
        $creds = array_filter($nurse['missing_qualifications'], fn($m) => $m['type'] === 'credential');
        check($creds && $nurse['match_score'] <= 60, 'licensed role: missing license listed and score capped', $nurse);
        check(!preg_match('/licen[cs]e/i', $nurse['explanation']), 'explanation never claims a license that is not in the resume', $nurse['explanation']);
    } else {
        check(true, 'licensed role not recommended without a license');
    }
    $scores = array_column($recs, 'match_score');
    $sorted = $scores; rsort($sorted);
    check($scores === $sorted, 'ranked by compatibility');
    check(http('GET', "$base/resume-recommendations/$uid", $empToken)['status'] === 403, 'employer cannot read jobseeker recommendations');

    echo "Resume file access\n";
    $r = http('GET', "$base/users/$uid/uploaded-resume", $token);
    check($r['status'] === 200 && str_starts_with($r['raw'], '%PDF'), 'owner can download through authorized route');
    check(http('GET', "$base/users/$uid/uploaded-resume", $otherToken)['status'] === 403, 'other jobseeker cannot download it');

    echo "Deletion\n";
    $path = q("SELECT file_path FROM resume_profiles WHERE user_id = ?", 'i', $uid)[0]['file_path'];
    $added = $confirmed['added_skill_ids'];
    $r = http('DELETE', "$base/resume-profile?remove_skills=1", $token);
    check($r['status'] === 200 && $r['json']['data']['resume'] === null, 'resume deleted', $r['json']);
    check(ResumeProcessor::resolvePath($path) === null, 'file removed from storage');
    check(q("SELECT resume_path FROM users WHERE id = ?", 'i', $uid)[0]['resume_path'] === null, 'users.resume_path cleared');
    $left = $added ? q("SELECT COUNT(*) c FROM user_skills WHERE user_id = ? AND skill_id IN (" . implode(',', array_map('intval', $added)) . ")", 'i', $uid)[0]['c'] : 0;
    check($left == 0, 'skills added from the resume removed on request');
    check(http('GET', "$base/resume-recommendations/me", $token)['json']['data']['status'] === 'no_resume', 'recommendations stop after deletion');
} finally {
    foreach ($created['jobs'] as $id) q("DELETE FROM jobs WHERE id = ?", 'i', $id);
    foreach ($created['employers'] as $id) q("DELETE FROM employers WHERE id = ?", 'i', $id);
    foreach ($created['users'] as $id) {
        $row = q("SELECT file_path FROM resume_profiles WHERE user_id = ?", 'i', $id);
        if ($row && ($p = ResumeProcessor::resolvePath($row[0]['file_path']))) @unlink($p);
        q("DELETE FROM system_logs WHERE user_id = ?", 'i', $id);
        q("DELETE FROM rate_limits WHERE key_hash = ?", 's', hash('sha256', 'resume_upload:' . $id));
        q("DELETE FROM users WHERE id = ?", 'i', $id);
    }
}
echo "\n$passes passed, $failures failed\n";
exit($failures > 0 ? 1 : 0);
