<?php
require_once __DIR__ . '/../config/database.php';

/**
 * Builds the system-generated resume (PDF) in the EmploySmart design:
 * brand blue palette, Sora + Plus Jakarta Sans, a light sidebar with the profile
 * photo, contact and skills, and a main column with the work history, education,
 * trainings and certifications.
 *
 * Content comes only from the jobseeker's profile, skills, completed EmploySmart
 * trainings, accepted placements and the resume details they confirmed.
 */
class ResumePdfService {

    private const EDUCATION = [
        'no_formal_education' => 'No formal education', 'elementary' => 'Elementary graduate', 'high_school' => 'High school graduate',
        'senior_high' => 'Senior high school graduate', 'vocational' => 'Vocational / technical graduate', 'college' => 'College graduate',
        'postgraduate' => 'Postgraduate',
    ];
    private const EMPLOYMENT = ['unemployed' => 'Available for work', 'employed' => 'Currently employed', 'self_employed' => 'Self-employed', 'student' => 'Student'];
    private const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    private mysqli $db;

    public function __construct(mysqli $db) {
        $this->db = $db;
    }

    /** @return array{0: string, 1: string} [pdf bytes, file name] */
    public function render(int $userId): array {
        $data = $this->collect($userId);
        try {
            $dompdf = $this->pdf($this->html($data));
        } catch (\Throwable $e) {
            if ($data['photo'] === null) throw $e;
            // A photo the PDF engine cannot read must never block the resume: retry without it.
            error_log('Resume photo skipped: ' . $e->getMessage());
            $data['photo'] = null;
            $dompdf = $this->pdf($this->html($data));
        }
        $name = trim(preg_replace('/[^A-Za-z0-9]+/', '_', $data['user']['first_name'] . '_' . $data['user']['last_name']), '_') ?: 'resume';
        return [$dompdf->output(), "EmploySmart_Resume_{$name}.pdf"];
    }

    private function pdf(string $html): \Dompdf\Dompdf {

        $fontCache = dataDir() . '/storage/fonts';
        if (!is_dir($fontCache)) @mkdir($fontCache, 0750, true);
        $options = new \Dompdf\Options();
        $options->setChroot([realpath(__DIR__ . '/../resources/fonts') ?: __DIR__ . '/../resources/fonts']);
        $options->setFontDir($fontCache);
        $options->setFontCache($fontCache);
        $options->setTempDir(sys_get_temp_dir());
        $options->setIsRemoteEnabled(false);
        $options->setIsFontSubsettingEnabled(true);
        $options->setDefaultFont('Jakarta');
        $options->setDpi(96);

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('Jakarta');
        // Page numbers in the footer of every page.
        $canvas->page_text($canvas->get_width() - 78, $canvas->get_height() - 38, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 7.5, [0.42, 0.45, 0.5]);
        return $dompdf;
    }

    // ─── Data ──────────────────────────────────────────────────────────────

    private function collect(int $userId): array {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if (!$user) throw new RuntimeException('User not found');

        $stmt = $this->db->prepare("SELECT DISTINCT s.skill_name FROM user_skills us JOIN skills s ON s.id = us.skill_id WHERE us.user_id = ? ORDER BY s.skill_name");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $skills = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'skill_name');

        $stmt = $this->db->prepare("SELECT tp.program_name, tp.location, ut.completion_date, ut.certificate_path FROM user_training ut JOIN training_programs tp ON tp.id = ut.training_id
                                    WHERE ut.user_id = ? AND ut.status = 'completed' ORDER BY ut.completion_date DESC, ut.id DESC");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $trainings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $stmt = $this->db->prepare("SELECT j.title, j.location, e.company_name, a.applied_at FROM applications a JOIN jobs j ON j.id = a.job_id LEFT JOIN employers e ON e.id = j.employer_id
                                    WHERE a.user_id = ? AND a.application_status = 'accepted' ORDER BY a.applied_at DESC");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $placements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $resume = [];
        $res = $this->db->query("SHOW TABLES LIKE 'resume_profiles'");
        if ($res && $res->num_rows) {
            $stmt = $this->db->prepare("SELECT confirmed_data FROM resume_profiles WHERE user_id = ? AND status = 'confirmed'");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $resume = $row ? (json_decode((string)$row['confirmed_data'], true) ?: []) : [];
        }

        // Skills confirmed from the uploaded resume that are not in the catalog list.
        $known = array_map('mb_strtolower', $skills);
        foreach ((array)($resume['skills'] ?? []) as $s) {
            $name = trim((string)($s['name'] ?? ''));
            if ($name !== '' && !in_array(mb_strtolower($name), $known, true)) { $skills[] = $name; $known[] = mb_strtolower($name); }
        }

        return ['user' => $user, 'skills' => array_slice($skills, 0, 40), 'trainings' => $trainings, 'placements' => $placements, 'resume' => $resume, 'photo' => $this->photo($user)];
    }

    /** Profile photo as a data URI (JPEG/PNG directly; WebP/GIF converted when GD is available). */
    /**
     * Profile photo as ['uri' => data URI, 'circle' => bool].
     * With GD the photo is centre-cropped into a circle (transparent corners); without GD,
     * JPEG/PNG photos are used as-is in a square frame.
     */
    private function photo(array $user): ?array {
        $path = !empty($user['profile_picture']) && function_exists('resolveUploadPath') ? resolveUploadPath($user['profile_picture']) : null;
        if (!$path || filesize($path) > 8 * 1024 * 1024) return null;
        $bytes = (string)file_get_contents($path);
        if (function_exists('imagecreatefromstring') && ($src = @imagecreatefromstring($bytes))) {
            $size = 360;
            $w = imagesx($src); $h = imagesy($src); $side = min($w, $h);
            $dst = imagecreatetruecolor($size, $size);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $clear = imagecolorallocatealpha($dst, 255, 255, 255, 127);
            imagefill($dst, 0, 0, $clear);
            imagecopyresampled($dst, $src, 0, 0, (int)(($w - $side) / 2), (int)(($h - $side) / 2), $size, $size, $side, $side);
            $r = $size / 2;
            for ($y = 0; $y < $size; $y++) {
                for ($x = 0; $x < $size; $x++) {
                    if (($x - $r + 0.5) ** 2 + ($y - $r + 0.5) ** 2 > $r * $r) imagesetpixel($dst, $x, $y, $clear);
                }
            }
            ob_start();
            imagepng($dst);
            $png = (string)ob_get_clean();
            return ['uri' => 'data:image/png;base64,' . base64_encode($png), 'circle' => true];
        }
        $mime = (@getimagesize($path))['mime'] ?? '';
        // Without GD the PDF engine can only embed JPEG; other formats use the initials badge.
        if ($mime === 'image/jpeg') {
            return ['uri' => "data:$mime;base64," . base64_encode($bytes), 'circle' => false];
        }
        return null;
    }

    // ─── Formatting helpers ─────────────────────────────────────────────────

    private function e($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function monthYear(?string $value): string {
        if (!$value || !preg_match('/^(\d{4})(?:-(\d{2}))?/', $value, $m)) return '';
        return isset($m[2]) && (int)$m[2] >= 1 ? self::MONTHS[(int)$m[2] - 1] . ' ' . $m[1] : $m[1];
    }

    private function longDate(?string $value): string {
        if (!$value || !preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m)) return '';
        return date('F j, Y', mktime(0, 0, 0, (int)$m[2], (int)$m[3], (int)$m[1]));
    }

    private function period(array $entry): string {
        $start = $this->monthYear($entry['start_date'] ?? null);
        $end = !empty($entry['is_current']) ? 'Present' : $this->monthYear($entry['end_date'] ?? null);
        if ($start && $end) return "$start – $end";
        return $start ?: $end;
    }

    private function section(string $title, string $body): string {
        return $body === '' ? '' : '<div class="section"><div class="section-title">' . $this->e($title) . '</div>' . $body . '</div>';
    }

    private function sideSection(string $title, string $body): string {
        return $body === '' ? '' : '<div class="side-section"><div class="side-title">' . $this->e($title) . '</div>' . $body . '</div>';
    }

    // ─── HTML ──────────────────────────────────────────────────────────────

    private function html(array $d): string {
        $u = $d['user'];
        $r = $d['resume'];
        $name = trim(implode(' ', array_filter([$u['first_name'], $u['middle_name'] ?? '', $u['last_name'], $u['suffix'] ?? ''])));
        $initials = mb_strtoupper(mb_substr((string)$u['first_name'], 0, 1) . mb_substr((string)$u['last_name'], 0, 1));
        $experience = array_values(array_filter((array)($r['experience'] ?? []), fn($e) => !empty($e['title']) || !empty($e['company'])));
        $headline = $experience[0]['title'] ?? ($d['placements'][0]['title'] ?? 'Jobseeker');
        $tagline = implode('  ·  ', array_filter([self::EDUCATION[$u['education_level'] ?? ''] ?? '', self::EMPLOYMENT[$u['employment_status'] ?? ''] ?? '']));
        $fontDir = str_replace('\\', '/', realpath(__DIR__ . '/../resources/fonts') ?: __DIR__ . '/../resources/fonts');

        // Sidebar
        $photo = $d['photo']
            ? ($d['photo']['circle']
                ? '<div class="photo-ring"><img class="photo-circle" src="' . $this->e($d['photo']['uri']) . '" alt="Profile photo"></div>'
                : '<img class="photo-square" src="' . $this->e($d['photo']['uri']) . '" alt="Profile photo">')
            : '<div class="photo-ring initials-ring"><div class="initials">' . $this->e($initials ?: 'ES') . '</div></div>';
        $contact = '';
        $rows = [
            ['Email', $u['email'] ?? ''],
            ['Phone', $u['phone'] ?? ''],
            ['Alt. phone', $u['alternate_phone'] ?? ''],
            ['Address', implode(', ', array_filter([$u['address'] ?? '', $u['city'] ?? '', $u['province'] ?? '', $u['zip_code'] ?? '']))],
        ];
        foreach ($rows as [$label, $value]) {
            // Long e-mail addresses use a smaller size so they fit the sidebar on one line.
            $cls = $label === 'Email' && mb_strlen((string)$value) > 26 ? 'v small' : 'v';
            if (trim((string)$value) !== '') $contact .= '<div class="kv"><div class="k">' . $this->e($label) . '</div><div class="' . $cls . '">' . $this->e($value) . '</div></div>';
        }
        $personal = '';
        $age = !empty($u['birth_date']) ? (int)date_diff(date_create($u['birth_date']), date_create('today'))->y : null;
        $rows = [
            ['Birth date', !empty($u['birth_date']) ? $this->longDate($u['birth_date']) . ($age ? " ($age yrs)" : '') : ''],
            ['Sex', ucfirst((string)($u['sex'] ?? ''))],
            ['Civil status', ucfirst((string)($u['civil_status'] ?? ''))],
            ['Nationality', $u['nationality'] ?? ''],
        ];
        foreach ($rows as [$label, $value]) {
            if (trim((string)$value) !== '') $personal .= '<div class="kv"><div class="k">' . $this->e($label) . '</div><div class="v">' . $this->e($value) . '</div></div>';
        }
        $skills = $d['skills'] ? '<div class="pills">' . implode('', array_map(fn($s) => '<span class="pill">' . $this->e($s) . '</span>', $d['skills'])) . '</div>' : '';
        $languages = '';
        foreach ((array)($r['languages'] ?? []) as $l) {
            if (!empty($l['name'])) $languages .= '<div class="lang"><span>' . $this->e($l['name']) . '</span>' . (!empty($l['proficiency']) ? '<em>' . $this->e(ucfirst($l['proficiency'])) . '</em>' : '') . '</div>';
        }

        // Main column
        $profile = trim((string)($u['bio'] ?: ($r['objective'] ?? '')));
        $work = '';
        foreach (array_slice($experience, 0, 8) as $e) {
            $bullets = array_slice(array_filter((array)($e['responsibilities'] ?? [])), 0, 5);
            $work .= '<div class="entry"><table class="entry-head"><tr><td><div class="entry-title">' . $this->e($e['title'] ?: 'Position') . '</div>'
                . '<div class="entry-sub">' . $this->e(implode(' · ', array_filter([$e['company'] ?? '', $e['location'] ?? '']))) . '</div></td>'
                . '<td class="entry-date">' . $this->e($this->period($e)) . '</td></tr></table>'
                . ($bullets ? '<ul>' . implode('', array_map(fn($b) => '<li>' . $this->e($b) . '</li>', $bullets)) . '</ul>' : '') . '</div>';
        }
        $placements = '';
        foreach (array_slice($d['placements'], 0, 6) as $p) {
            $placements .= '<div class="entry"><table class="entry-head"><tr><td><div class="entry-title">' . $this->e($p['title']) . '</div>'
                . '<div class="entry-sub">' . $this->e(implode(' · ', array_filter([$p['company_name'] ?? '', $p['location'] ?? '']))) . '</div></td>'
                . '<td class="entry-date"><span class="badge badge-green">Hired via EmploySmart</span><br>' . $this->e($this->longDate($p['applied_at'])) . '</td></tr></table></div>';
        }
        $education = '';
        foreach (array_slice((array)($r['education'] ?? []), 0, 6) as $e) {
            if (empty($e['degree']) && empty($e['institution'])) continue;
            $status = ['ongoing' => 'Ongoing', 'incomplete' => 'Not completed', 'expected' => 'Expected'][$e['status'] ?? ''] ?? '';
            $education .= '<div class="entry"><table class="entry-head"><tr><td><div class="entry-title">' . $this->e($e['degree'] ?: ($e['institution'] ?? '')) . '</div>'
                . '<div class="entry-sub">' . $this->e($e['degree'] ? ($e['institution'] ?? '') : '') . (!empty($e['honors']) ? ' · ' . $this->e($e['honors']) : '') . '</div></td>'
                . '<td class="entry-date">' . $this->e(trim($this->period($e) . ($status ? " ($status)" : ''))) . '</td></tr></table></div>';
        }
        if ($education === '' && !empty($u['education_level'])) {
            $education = '<div class="entry"><div class="entry-title">' . $this->e(self::EDUCATION[$u['education_level']] ?? $u['education_level']) . '</div></div>';
        }
        $credentials = '';
        foreach ($d['trainings'] as $t) {
            $credentials .= '<div class="entry small"><table class="entry-head"><tr><td><div class="entry-title">' . $this->e($t['program_name']) . '</div>'
                . '<div class="entry-sub">EmploySmart livelihood training' . (!empty($t['location']) ? ' · ' . $this->e($t['location']) : '') . '</div></td>'
                . '<td class="entry-date">' . (!empty($t['certificate_path']) ? '<span class="badge badge-blue">Certificate on file</span><br>' : '') . $this->e($this->longDate($t['completion_date'])) . '</td></tr></table></div>';
        }
        foreach (array_merge((array)($r['certifications'] ?? []), (array)($r['trainings'] ?? [])) as $c) {
            if (empty($c['name'])) continue;
            $credentials .= '<div class="entry small"><table class="entry-head"><tr><td><div class="entry-title">' . $this->e($c['name']) . '</div>'
                . (!empty($c['issuer']) ? '<div class="entry-sub">' . $this->e($c['issuer']) . '</div>' : '') . '</td>'
                . '<td class="entry-date">' . $this->e($this->monthYear($c['date'] ?? null)) . '</td></tr></table></div>';
        }
        $list = fn($items) => $items ? '<ul class="plain">' . implode('', array_map(fn($i) => '<li>' . $this->e(is_array($i) ? ($i['name'] ?? '') : $i) . '</li>', $items)) . '</ul>' : '';
        $awards = $list(array_filter((array)($r['awards'] ?? []), fn($i) => !empty($i['name'] ?? $i)));
        $projects = $list(array_filter((array)($r['projects'] ?? []), fn($i) => !empty($i['name'] ?? $i)));
        $generated = date('F j, Y');

        $css = <<<CSS
@font-face { font-family: 'Jakarta'; font-weight: normal; src: url('file://{$fontDir}/PlusJakartaSans_400Regular.ttf') format('truetype'); }
@font-face { font-family: 'Jakarta'; font-weight: bold; src: url('file://{$fontDir}/PlusJakartaSans_700Bold.ttf') format('truetype'); }
@font-face { font-family: 'JakartaSemi'; font-weight: normal; src: url('file://{$fontDir}/PlusJakartaSans_600SemiBold.ttf') format('truetype'); }
@font-face { font-family: 'Sora'; font-weight: normal; src: url('file://{$fontDir}/Sora_700Bold.ttf') format('truetype'); }
@font-face { font-family: 'Sora'; font-weight: bold; src: url('file://{$fontDir}/Sora_800ExtraBold.ttf') format('truetype'); }
@page { margin: 13mm 12mm 19mm 0; }
* { box-sizing: border-box; }
body { margin: 0; font-family: 'Jakarta', 'DejaVu Sans', sans-serif; font-size: 9.6pt; color: #1f2937; line-height: 1.38; }
.sidebar-bg { position: fixed; top: -13mm; left: 0; bottom: -19mm; width: 64mm; background: #eff6ff; }
.topbar { position: fixed; top: -13mm; left: 0; right: -12mm; height: 3mm; background: #1d4ed8; }
.topbar-accent { position: fixed; top: -13mm; right: -12mm; width: 70mm; height: 3mm; background: #4f46e5; }
.footer { position: fixed; bottom: -12mm; left: 73mm; right: 0; height: 8mm; padding-top: 2.6mm; padding-right: 30mm; border-top: 0.6pt solid #dbeafe; font-size: 7.3pt; color: #6b7280; }
.side { position: absolute; top: 0; left: 8mm; width: 49mm; }
.main { margin-left: 73mm; }
.photo-ring { width: 35.8mm; height: 35.8mm; margin: 0 auto 4mm; border-radius: 19mm; background: #1d4ed8; padding: 1.1mm; box-sizing: content-box; }
.photo-circle { display: block; width: 35.8mm; height: 35.8mm; }
.photo-square { display: block; width: 34mm; height: 34mm; margin: 0 auto 4mm; border: 1.2mm solid #ffffff; outline: 0.6mm solid #1d4ed8; }
.photo { display: block; width: 36mm; height: 36mm; margin: 0 auto 4mm; border-radius: 18mm; border: 1.6mm solid #ffffff; }
.initials-ring { background: #bfdbfe; }
.initials { width: 35.8mm; height: 22.8mm; padding-top: 13mm; border-radius: 17.9mm; background: #1d4ed8; color: #ffffff; text-align: center; font-family: 'Sora'; font-weight: bold; font-size: 26pt; line-height: 1; box-sizing: content-box; }
.side-section { margin-top: 6mm; }
.side-title { font-family: 'Sora'; font-weight: normal; font-size: 8.4pt; letter-spacing: 1.2pt; color: #1d4ed8; text-transform: uppercase; padding-bottom: 1.5mm; margin-bottom: 2.5mm; border-bottom: 0.8pt solid #bfdbfe; }
.kv { margin-bottom: 2.4mm; }
.k { font-size: 7.2pt; text-transform: uppercase; letter-spacing: 0.6pt; color: #6b7280; }
.v { font-family: 'JakartaSemi'; font-size: 8.6pt; color: #1e3a8a; word-wrap: break-word; line-height: 1.35; }
.v.small { font-size: 7.6pt; }
.pills { line-height: 1.2; }
.pill { display: inline-block; background: #ffffff; border: 0.6pt solid #bfdbfe; color: #1d4ed8; border-radius: 3mm; padding: 0.7mm 2.4mm; font-family: 'JakartaSemi'; font-size: 7.8pt; line-height: 1.2; margin: 0 1mm 1.6mm 0; }
.lang { font-size: 8.8pt; margin-bottom: 1.5mm; color: #1e3a8a; }
.lang em { font-style: normal; color: #6b7280; font-size: 7.8pt; margin-left: 1.5mm; }
.name { font-family: 'Sora'; font-weight: bold; font-size: 25pt; color: #1e3a8a; line-height: 1.1; }
.headline { font-family: 'JakartaSemi'; font-size: 12pt; color: #1d4ed8; margin-top: 1.5mm; }
.tagline { font-size: 8.8pt; color: #6b7280; margin-top: 1mm; }
.rule { height: 1.1mm; background: #1d4ed8; border-radius: 1mm; margin: 4.5mm 0 1mm; width: 28mm; }
.section { margin-top: 6mm; }
.section-title { page-break-after: avoid; }
.section-title { font-family: 'Sora'; font-weight: normal; font-size: 10pt; color: #1e3a8a; text-transform: uppercase; letter-spacing: 1pt; padding-bottom: 1.6mm; margin-bottom: 3mm; border-bottom: 0.8pt solid #e5e7eb; }
.section-title:before { content: ''; }
.profile { color: #374151; font-size: 9.4pt; }
.entry { margin-bottom: 3.6mm; page-break-inside: avoid; }
.entry.small { margin-bottom: 2.6mm; }
.entry-head { width: 100%; border-collapse: collapse; }
.entry-head td { vertical-align: top; padding: 0; }
.entry-title { font-family: 'Jakarta'; font-weight: bold; font-size: 10pt; color: #111827; }
.entry-sub { font-size: 8.8pt; color: #1d4ed8; font-family: 'JakartaSemi'; }
.entry-date { text-align: right; white-space: nowrap; font-size: 8pt; color: #6b7280; width: 40mm; }
ul { margin: 1.4mm 0 0 0; padding-left: 4.2mm; }
li { margin-bottom: 0.8mm; color: #374151; font-size: 8.9pt; }
ul.plain { margin-top: 0; }
.badge { display: inline-block; border-radius: 3mm; padding: 0.1mm 2mm; font-size: 7pt; font-family: 'JakartaSemi'; }
.badge-green { background: #dcfce7; color: #15803d; }
.badge-blue { background: #dbeafe; color: #1d4ed8; }
.empty { color: #9ca3af; font-style: italic; font-size: 8.8pt; }
CSS;

        $workBlock = $work !== '' ? $work : ($placements === '' ? '<div class="empty">No work experience recorded yet.</div>' : '');
        return '<!doctype html><html><head><meta charset="utf-8"><title>' . $this->e($name) . ' — Resume</title><style>' . $css . '</style></head><body>'
            . '<div class="sidebar-bg"></div><div class="topbar"></div><div class="topbar-accent"></div>'
            . '<div class="footer">Generated by EmploySmart · ' . $this->e($generated) . '</div>'
            . '<div class="side">' . $photo
                . $this->sideSection('Contact', $contact)
                . $this->sideSection('Personal details', $personal)
                . $this->sideSection('Skills', $skills)
                . $this->sideSection('Languages', $languages)
            . '</div>'
            . '<div class="main">'
                . '<div class="name">' . $this->e($name) . '</div>'
                . '<div class="headline">' . $this->e($headline) . '</div>'
                . ($tagline ? '<div class="tagline">' . $this->e($tagline) . '</div>' : '')
                . '<div class="rule"></div>'
                . $this->section('Profile', $profile !== '' ? '<div class="profile">' . nl2br($this->e($profile)) . '</div>' : '')
                . $this->section('Work experience', $workBlock)
                . $this->section('Placements through EmploySmart', $placements)
                . $this->section('Education', $education)
                . $this->section('Trainings & certifications', $credentials)
                . $this->section('Awards', $awards)
                . $this->section('Projects', $projects)
            . '</div></body></html>';
    }
}
