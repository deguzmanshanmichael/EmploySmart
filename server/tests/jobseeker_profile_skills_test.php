<?php
/**
 * Regression tests for jobseeker profile saving and custom skills.
 *   php -S 127.0.0.1:8099 server/index.php
 *   php server/tests/jobseeker_profile_skills_test.php [http://127.0.0.1:8099/api]
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
    echo "  FAIL $label" . ($detail !== null ? ' :: ' . substr(json_encode($detail, JSON_UNESCAPED_UNICODE), 0, 400) : '') . "\n";
}
function http(string $method, string $url, string $token, $body = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Requested-With: XMLHttpRequest', "Authorization: Bearer $token"]]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'json' => json_decode((string)$raw, true)];
}
$tag = 'jsfix' . bin2hex(random_bytes(3));
$mk = function () use ($db, $tag) {
    static $n = 0; $n++;
    $email = "$tag.$n@example.com";
    $db->query("INSERT INTO users (first_name,last_name,sex,email,password,role,is_verified) VALUES ('Ana','Cruz','female','$email','x','jobseeker',1)");
    $id = $db->insert_id;
    return [$id, generateJWT(['sub' => $id, 'role' => 'jobseeker', 'email' => $email])];
};
$createdSkills = [];
[$uid, $tok] = $mk();
[$otherId, $otherTok] = $mk();
try {
    echo "Profile update\n";
    $form = ['first_name' => 'Ana', 'middle_name' => '', 'last_name' => 'Cruz', 'suffix' => '', 'sex' => 'female', 'birth_date' => '', 'phone' => '0917 123 4567', 'alternate_phone' => '',
        'address' => 'Purok 1 & 2, Brgy. <b>Sto.</b> Niño', 'city' => 'Gapan City', 'province' => '', 'zip_code' => '', 'civil_status' => '', 'education_level' => '', 'employment_status' => 'unemployed', 'bio' => ''];
    $r = http('PUT', "$base/users/$uid", $tok, $form);
    check($r['status'] === 200, 'form with blank optional fields saves', $r['json']);
    $row = $db->query("SELECT address, middle_name, civil_status, sex FROM users WHERE id = $uid")->fetch_assoc();
    check($row['address'] === 'Purok 1 & 2, Brgy. Sto. Niño', 'text stored as typed (no &amp;, tags removed)', $row);
    check($row['middle_name'] === null && $row['civil_status'] === null && $row['sex'] === 'female', 'blank optional fields cleared, required columns kept', $row);
    $r = http('PUT', "$base/users/$uid", $tok, ['first_name' => 'Ana', 'middle_name' => null, 'last_name' => 'Cruz', 'bio' => null]);
    check($r['status'] === 200, 'null values accepted');
    $r = http('PUT', "$base/users/$uid", $tok, array_merge($form, ['civil_status' => 'complicated']));
    check($r['status'] === 422 && isset($r['json']['errors']['civil_status']), 'invalid choice gives a field error, not a crash', $r['json']);
    $r = http('PUT', "$base/users/$uid", $tok, array_merge($form, ['first_name' => '']));
    check($r['status'] === 422 && isset($r['json']['errors']['first_name']), 'first name required');
    $r = http('PUT', "$base/users/$uid", $tok, array_merge($form, ['phone' => 'abc']));
    check($r['status'] === 422 && isset($r['json']['errors']['phone']), 'invalid phone rejected');
    $r = http('PUT', "$base/users/$uid", $tok, array_merge($form, ['birth_date' => date('Y-m-d', strtotime('+1 day'))]));
    check($r['status'] === 422 && isset($r['json']['errors']['birth_date']), 'future birth date rejected');
    check(http('PUT', "$base/users/$uid", $otherTok, $form)['status'] === 403, "cannot edit someone else's profile");

    echo "Custom skills\n";
    $name = "$tag Drone Piloting";
    $r = http('POST', "$base/skills/user/$uid/custom", $tok, ['skill_name' => "  $name  "]);
    $skillId = (int)($r['json']['data']['id'] ?? 0);
    if (!empty($r['json']['data']['created'])) $createdSkills[] = $skillId;
    check($r['status'] === 201 && $skillId > 0 && $r['json']['data']['skill_name'] === $name, 'new skill created and trimmed', $r['json']);
    check((int)$db->query("SELECT COUNT(*) FROM user_skills WHERE user_id = $uid AND skill_id = $skillId")->fetch_row()[0] === 1, 'added to the user profile');
    $r = http('POST', "$base/skills/user/$uid/custom", $tok, ['skill_name' => strtoupper($name)]);
    check($r['status'] === 200 && (int)$r['json']['data']['id'] === $skillId && empty($r['json']['data']['created']), 'same name (any case) reuses the skill');
    check((int)$db->query("SELECT COUNT(*) FROM user_skills WHERE user_id = $uid AND skill_id = $skillId")->fetch_row()[0] === 1, 'no duplicate user skill');
    $existing = $db->query("SELECT id, skill_name FROM skills WHERE COALESCE(archived,0) = 0 ORDER BY id LIMIT 1")->fetch_assoc();
    $r = http('POST', "$base/skills/user/$uid/custom", $tok, ['skill_name' => strtolower($existing['skill_name'])]);
    check((int)($r['json']['data']['id'] ?? 0) === (int)$existing['id'], 'existing catalog skill is reused', $r['json']);
    check(http('POST', "$base/skills/user/$uid/custom", $tok, ['skill_name' => 'x'])['status'] === 422, 'too-short name rejected');
    check(http('POST', "$base/skills/user/$uid/custom", $tok, ['skill_name' => '<script>alert(1)</script>'])['status'] === 422, 'markup-only name rejected');
    check(http('POST', "$base/skills/user/$uid/custom", $otherTok, ['skill_name' => 'Welding'])['status'] === 403, "cannot add skills to someone else's profile");
    $r = http('PUT', "$base/skills/user/$uid", $tok, ['skills' => [$skillId, $skillId, (string)$existing['id']]]);
    check($r['status'] === 200 && (int)$db->query("SELECT COUNT(*) FROM user_skills WHERE user_id = $uid")->fetch_row()[0] === 2, 'saving the skill list removes duplicates', $r['json']);
} finally {
    foreach ([$uid, $otherId] as $id) {
        $db->query("DELETE FROM rate_limits WHERE key_hash = '" . hash('sha256', "custom_skill:$id") . "'");
        $db->query("DELETE FROM users WHERE id = $id");
    }
    foreach ($createdSkills as $sid) $db->query("DELETE FROM skills WHERE id = " . (int)$sid);
}
echo "\n$passes passed, $failures failed\n";
exit($failures > 0 ? 1 : 0);
