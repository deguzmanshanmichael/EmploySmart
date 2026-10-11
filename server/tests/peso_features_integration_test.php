<?php
/**
 * End-to-end tests for placement feedback (PESO ↔ employer), skills-gap tracking and
 * municipality configuration. Creates its own records and removes them afterwards.
 * Never run against production.
 *
 *   php -S 127.0.0.1:8099 server/index.php
 *   php server/tests/peso_features_integration_test.php [http://127.0.0.1:8099/api]
 */
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/jwt.php';

if (strtolower((string)env('APP_ENV', 'development')) === 'production') { fwrite(STDERR, "Refusing to run in production\n"); exit(2); }
$base = rtrim($argv[1] ?? 'http://127.0.0.1:8099/api', '/');
$db = getDB();
$failures = 0; $passes = 0;
function check(bool $ok, string $label, $detail = null) {
    global $failures, $passes;
    if ($ok) { $passes++; echo "  ok   $label\n"; return; }
    $failures++;
    echo "  FAIL $label" . ($detail !== null ? ' :: ' . substr(json_encode($detail, JSON_UNESCAPED_UNICODE), 0, 500) : '') . "\n";
}
function http(string $method, string $url, ?string $token, $body = null): array {
    $ch = curl_init($url);
    $h = ['X-Requested-With: XMLHttpRequest', 'Content-Type: application/json'];
    if ($token) $h[] = 'Authorization: Bearer ' . $token;
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $h, CURLOPT_TIMEOUT => 60]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'json' => json_decode((string)$raw, true)];
}
function q(string $sql, string $types = '', ...$params) {
    global $db;
    $stmt = $db->prepare($sql);
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : $db->insert_id;
}

$tag = 'pesotest' . bin2hex(random_bytes(3));
$made = ['users' => [], 'employers' => [], 'jobs' => [], 'trainings' => [], 'skills' => []];
$user = function (string $role, string $first) use ($tag, &$made) {
    $email = "$tag.$first@example.com";
    $id = q("INSERT INTO users (first_name, last_name, sex, email, password, role, is_verified) VALUES (?, 'Test', 'female', ?, 'x', ?, 1)", 'sss', $first, $email, $role);
    $made['users'][] = $id;
    return [$id, generateJWT(['sub' => $id, 'email' => $email, 'role' => $role, 'name' => "$first Test"])];
};
$savedSettings = q("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('municipality_name','municipality_province','contact_address','office_hours')");

try {
    [$pesoId, $peso] = $user('peso', 'Pesa');
    [$empUser, $emp] = $user('employer', 'Owner');
    [$otherEmpUser, $otherEmp] = $user('employer', 'Rival');
    [$seekerId, $seeker] = $user('jobseeker', 'Seeker');
    [$needyId, $needy] = $user('jobseeker', 'Needy');
    $employerId = q("INSERT INTO employers (user_id, company_name, verification_status) VALUES (?, ?, 'approved')", 'is', $empUser, "$tag Foods Inc.");
    $otherEmployerId = q("INSERT INTO employers (user_id, company_name, verification_status) VALUES (?, ?, 'approved')", 'is', $otherEmpUser, "$tag Other Corp");
    $made['employers'] = [$employerId, $otherEmployerId];
    $skillId = q("INSERT INTO skills (skill_name) VALUES (?)", 's', "$tag Barista Skills");
    $made['skills'][] = $skillId;
    $future = date('Y-m-d', strtotime('+20 days'));
    $jobId = q("INSERT INTO jobs (employer_id, title, requirements, description, location, job_type, experience_level, approval_status, vacancies, deadline) VALUES (?, ?, 'Customer service skills, cash handling', 'Serve customers', 'Gapan City', 'fulltime', 'entry', 'approved', 4, ?)", 'iss', $employerId, "$tag Barista", $future);
    $made['jobs'][] = $jobId;
    q("INSERT INTO job_skills (job_id, skill_id) VALUES (?, ?)", 'ii', $jobId, $skillId);
    $trainingId = q("INSERT INTO training_programs (program_name, status, start_date, location) VALUES (?, 'upcoming', ?, 'Gapan City')", 'ss', "$tag Barista Course", $future);
    $made['trainings'][] = $trainingId;
    q("INSERT INTO training_skills (training_id, skill_id) VALUES (?, ?)", 'ii', $trainingId, $skillId);
    $appId = q("INSERT INTO applications (job_id, user_id, application_status) VALUES (?, ?, 'accepted')", 'ii', $jobId, $seekerId);
    $pendingApp = q("INSERT INTO applications (job_id, user_id, application_status) VALUES (?, ?, 'pending')", 'ii', $jobId, $needyId);

    echo "Placement feedback (PESO ↔ employer)\n";
    $r = http('GET', "$base/feedback/placements", $emp);
    $mine = array_column($r['json']['data']['placements'] ?? [], 'application_id');
    check($r['status'] === 200 && in_array($appId, array_map('intval', $mine), true), 'employer sees their placement', $r['json']);
    $r = http('GET', "$base/feedback/placements", $otherEmp);
    check(!in_array($appId, array_map('intval', array_column($r['json']['data']['placements'] ?? [], 'application_id')), true), "another employer cannot see it");
    check(http('GET', "$base/feedback/placements/$appId", $otherEmp)['status'] === 403, "another employer cannot open it");
    check(http('GET', "$base/feedback/placements", $seeker)['status'] === 403, 'jobseekers cannot use placement feedback');
    check(http('POST', "$base/feedback/placements/$pendingApp", $peso, ['rating' => 4, 'feedback' => 'x x x'])['status'] === 404, 'feedback only for accepted placements');
    check(http('POST', "$base/feedback/placements/$appId", $emp, ['rating' => 9, 'feedback' => 'Great'])['status'] === 422, 'rating validated');
    check(http('POST', "$base/feedback/placements/$appId", $emp, ['rating' => 3, 'outcome' => 'hacked', 'feedback' => 'Great'])['status'] === 422, 'outcome validated');

    $r = http('POST', "$base/feedback/placements/$appId", $emp, ['rating' => 3, 'outcome' => 'needs_training', 'feedback' => '<b>Good attitude</b> but needs barista training.']);
    $threadId = (int)($r['json']['data']['id'] ?? 0);
    check($r['status'] === 201 && $threadId > 0, 'employer starts a feedback thread', $r['json']);
    check(q("SELECT COUNT(*) c FROM notifications WHERE target_user_id = ? AND category = 'feedback'", 'i', $pesoId)[0]['c'] >= 1, 'PESO is notified');
    $r = http('POST', "$base/feedback/thread/$threadId/replies", $peso, ['message' => 'Thank you. We will recommend a barista course.']);
    check($r['status'] === 201, 'PESO replies');
    check(q("SELECT COUNT(*) c FROM notifications WHERE target_user_id = ? AND category = 'feedback'", 'i', $empUser)[0]['c'] >= 1, 'employer is notified of the reply');
    check(http('POST', "$base/feedback/thread/$threadId/replies", $otherEmp, ['message' => 'intrude'])['status'] === 403, 'outsider cannot reply');
    $r = http('GET', "$base/feedback/placements/$appId", $peso);
    $thread = $r['json']['data']['threads'][0] ?? [];
    check(($thread['outcome_label'] ?? '') === 'Needs additional training' && count($thread['replies'] ?? []) === 1, 'thread shows outcome and replies', $thread);
    check(!str_contains($thread['feedback'] ?? '<', '<b>'), 'markup stripped from feedback');
    check(http('PATCH', "$base/feedback/thread/$threadId/status", $peso, ['status' => 'closed'])['status'] === 200, 'PESO closes the thread');
    check(http('POST', "$base/feedback/thread/$threadId/replies", $emp, ['message' => 'one more'])['status'] === 409, 'closed threads cannot receive replies');
    $r = http('GET', "$base/feedback/summary", $peso);
    $s = $r['json']['data'] ?? [];
    check($r['status'] === 200 && $s['placements'] >= 1 && $s['threads'] >= 1 && in_array('needs_training', array_column($s['outcomes'], 'outcome'), true), 'PESO outcome summary', $s);
    check(http('GET', "$base/feedback/summary", $emp)['status'] === 403, 'summary is PESO-only');
    check(http('POST', "$base/feedback", $emp, ['target_type' => 'employer', 'target_id' => $otherEmployerId, 'feedback' => 'spam'])['status'] === 403, 'employers cannot post generic feedback about other employers');
    check(http('GET', "$base/feedback/employer/$otherEmployerId", $emp)['status'] === 403, "employers cannot read other employers' feedback");
    check(http('POST', "$base/feedback", $seeker, ['target_type' => 'jobseeker', 'target_id' => $seekerId, 'rating' => 5, 'feedback' => 'Growth center feedback'])['status'] === 201, 'Growth Center self-feedback still works');

    echo "Skills gap tracking\n";
    $r = http('GET', "$base/skill-gaps", $peso);
    $d = $r['json']['data'] ?? [];
    $row = array_values(array_filter($d['skills'] ?? [], fn($x) => $x['skill_id'] === $skillId))[0] ?? null;
    check($r['status'] === 200 && $row && $row['open_jobs'] === 1 && $row['vacancies'] === 4, 'demand from open job postings', $row);
    check($row && $row['jobseekers_with_skill'] === 0 && $row['shortage'] === 4, 'supply and shortage', $row);
    check($row && ($row['trainings'][0]['id'] ?? 0) === $trainingId, 'training that closes the gap is linked', $row['trainings'] ?? null);
    check(count($d['trend'] ?? []) === 12, '12-month progression trend');
    $flagged = array_values(array_filter($d['applicants_needing_support'] ?? [], fn($a) => $a['id'] === $seekerId))[0] ?? null;
    check($flagged && $flagged['flagged_by_employer'] === true, 'employer "needs training" feedback flags the applicant', $d['applicants_needing_support'] ?? null);
    check(http('GET', "$base/skill-gaps", $seeker)['status'] === 403 && http('GET', "$base/skill-gaps", $emp)['status'] === 403, 'overview is staff-only');

    $r = http('GET', "$base/skill-gaps/user/$seekerId", $peso);
    $a = $r['json']['data'] ?? [];
    check($r['status'] === 200 && in_array($skillId, array_column($a['missing_in_demand'] ?? [], 'skill_id'), true), 'applicant view lists missing in-demand skills', $a['missing_in_demand'] ?? null);
    check(($a['placement_feedback'][0]['outcome'] ?? '') === 'needs_training', 'applicant view shows placement feedback');
    check(http('GET', "$base/skill-gaps/user/$seekerId", $needy)['status'] === 403, "jobseekers cannot view someone else's gaps");
    $own = http('GET', "$base/skill-gaps/user/me", $seeker);
    check($own['status'] === 200 && ($own['json']['data']['placement_feedback'] ?? null) === [] && !isset($own['json']['data']['interventions']), 'jobseekers see their own gaps without staff-only data');

    $r = http('POST', "$base/skill-gaps/user/$seekerId/recommend", $peso, ['training_id' => $trainingId, 'message' => 'Slots are limited.']);
    check($r['status'] === 200, 'PESO recommends a training (targeted intervention)', $r['json']);
    $n = q("SELECT message FROM notifications WHERE target_user_id = ? AND category = 'training_recommendation'", 'i', $seekerId);
    check($n && str_contains($n[0]['message'], "$tag Barista Course") && str_contains($n[0]['message'], 'Slots are limited.'), 'jobseeker receives the recommendation', $n);
    check(http('POST', "$base/skill-gaps/user/$seekerId/recommend", $emp, ['training_id' => $trainingId])['status'] === 403, 'employers cannot send interventions');
    q("INSERT INTO user_skills (user_id, skill_id) VALUES (?, ?)", 'ii', $seekerId, $skillId);
    $row2 = array_values(array_filter(http('GET', "$base/skill-gaps", $peso)['json']['data']['skills'], fn($x) => $x['skill_id'] === $skillId))[0] ?? null;
    check($row2 && $row2['jobseekers_with_skill'] === 1 && $row2['shortage'] === 3, 'gap shrinks when a jobseeker gains the skill', $row2);

    echo "Printable report data\n";
    [, $clcdo] = $user('clcdo', 'Clara');
    [, $admin] = $user('admin', 'Adam');
    $today = date('Y-m-d');
    $r = http('GET', "$base/reports/employment?from=$today&to=$today", $peso);
    $d = $r['json']['data'] ?? [];
    check($r['status'] === 200 && in_array($seekerId, array_map('intval', array_column($d['jobseekers'] ?? [], 'id')), true), 'employment report lists jobseekers registered in range', $r['json']);
    check(in_array($jobId, array_map('intval', array_column($d['jobs'] ?? [], 'id')), true) && in_array($appId, array_map('intval', array_column($d['applications'] ?? [], 'id')), true), 'employment report lists jobs and applications in range');
    $old = http('GET', "$base/reports/employment?from=2000-01-01&to=2000-01-31", $peso)['json']['data'] ?? [];
    check(($old['jobseekers'] ?? null) === [] && ($old['applications'] ?? null) === [], 'records outside the range are excluded');
    check(http('GET', "$base/reports/employment?from=2026-05-01&to=2026-01-01", $peso)['status'] === 422, 'reversed date range rejected');
    check(http('GET', "$base/reports/employment?from=bad&to=2026-01-01", $peso)['status'] === 422, 'invalid date rejected');
    check(http('GET', "$base/reports/employment", $emp)['status'] === 403 && http('GET', "$base/reports/employment", $seeker)['status'] === 403, 'employment report is PESO/admin only');
    $r = http('GET', "$base/reports/training?from=$today&to=" . date('Y-m-d', strtotime('+60 days')), $clcdo);
    check($r['status'] === 200 && in_array($trainingId, array_map('intval', array_column($r['json']['data']['programs'] ?? [], 'id')), true), 'training report lists programs running in range', $r['json']);
    check(http('GET', "$base/reports/logs", $admin)['status'] === 200 && http('GET', "$base/reports/logs", $peso)['status'] === 403, 'log report is admin only');

    echo "Report seals\n";
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    $uploadSeal = function (string $type, string $token) use ($base, $png) {
        $tmp = tempnam(sys_get_temp_dir(), 'seal'); file_put_contents($tmp, $png);
        $ch = curl_init("$base/settings/municipality/upload");
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['X-Requested-With: XMLHttpRequest', "Authorization: Bearer $token"],
            CURLOPT_POSTFIELDS => ['image_type' => $type, 'image' => new CURLFile($tmp, 'image/png', 'seal.png')]]);
        $raw = curl_exec($ch); $status = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); @unlink($tmp);
        return ['status' => $status, 'json' => json_decode((string)$raw, true)];
    };
    $savedSeals = q("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'landing_seal_%' OR setting_key LIKE 'report_seal_%'");
    $r = $uploadSeal('seal_peso', $admin);
    $sealPath = $r['json']['data']['landing_seal_peso_image'] ?? '';
    check($r['status'] === 200 && str_contains($sealPath, 'uploads/landing/landing_seal_peso_'), 'admin uploads the PESO seal', $r['json']);
    check($uploadSeal('seal_clcdo', $peso)['status'] === 403, 'only admin can upload seals');
    check($uploadSeal('seal_unknown', $admin)['status'] === 422, 'unknown seal type rejected');
    http('POST', "$base/settings/municipality", $admin, ['report_seal_peso_caption' => 'PESO Talavera', 'report_seal_lgu_caption' => 'Municipality of Talavera']);
    $pub = http('GET', "$base/settings/landing", null)['json']['data'] ?? [];
    check(($pub['landing_seal_peso_image'] ?? '') === $sealPath && ($pub['report_seal_peso_caption'] ?? '') === 'PESO Talavera' && array_key_exists('landing_seal_clcdo_image', $pub), 'print layout receives seal images and captions', $pub);
    if ($sealPath && is_file(dataDir() . '/' . $sealPath)) @unlink(dataDir() . '/' . $sealPath);
    q("DELETE FROM system_settings WHERE setting_key LIKE 'landing_seal_%' OR setting_key LIKE 'report_seal_%'");
    foreach ($savedSeals as $s) q("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)", 'ss', $s['setting_key'], $s['setting_value']);

    echo "Municipality configuration\n";
    $r = http('POST', "$base/settings/municipality", $peso, ['municipality_name' => 'Talavera', 'municipality_province' => 'Nueva Ecija', 'contact_address' => 'Municipal Hall, Talavera', 'office_hours' => 'Mon–Fri 8–5']);
    check($r['status'] === 200, 'PESO saves municipality settings');
    $pub = http('GET', "$base/settings/landing", null)['json']['data'] ?? [];
    check(($pub['municipality_name'] ?? '') === 'Talavera' && ($pub['municipality_province'] ?? '') === 'Nueva Ecija' && ($pub['contact_address'] ?? '') === 'Municipal Hall, Talavera' && ($pub['office_hours'] ?? '') === 'Mon–Fri 8–5', 'public pages receive the configured municipality', $pub);
} finally {
    foreach (['municipality_name', 'municipality_province', 'contact_address', 'office_hours'] as $k) q("DELETE FROM system_settings WHERE setting_key = ?", 's', $k);
    foreach ($savedSettings as $s) q("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)", 'ss', $s['setting_key'], $s['setting_value']);
    foreach ($made['jobs'] as $id) q("DELETE FROM jobs WHERE id = ?", 'i', $id);
    foreach ($made['trainings'] as $id) q("DELETE FROM training_programs WHERE id = ?", 'i', $id);
    foreach ($made['skills'] as $id) q("DELETE FROM skills WHERE id = ?", 'i', $id);
    foreach ($made['employers'] as $id) q("DELETE FROM employers WHERE id = ?", 'i', $id);
    foreach ($made['users'] as $id) {
        q("DELETE FROM feedback_threads WHERE sender_id = ?", 'i', $id);
        q("DELETE FROM system_logs WHERE user_id = ?", 'i', $id);
        q("DELETE FROM users WHERE id = ?", 'i', $id);
    }
}
echo "\n$passes passed, $failures failed\n";
exit($failures > 0 ? 1 : 0);
