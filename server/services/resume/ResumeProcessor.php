<?php
require_once __DIR__ . '/SkillNormalizer.php';
require_once __DIR__ . '/ResumeTextExtractor.php';
require_once __DIR__ . '/ResumeParser.php';
require_once __DIR__ . '/ResumeAiExtractor.php';

/**
 * Orchestrates resume storage, text extraction, parsing and optional AI assistance.
 */
class ResumeProcessor {

    public const PRIVATE_PREFIX = 'private/resumes/';

    // ─── Storage ─────────────────────────────────────────────────────────────

    /** Directory for uploaded resumes; configure RESUME_STORAGE_DIR outside public_html in production. */
    public static function storageDir(): string {
        $configured = trim((string)env('RESUME_STORAGE_DIR', ''));
        $dir = $configured !== '' ? $configured : dirname(__DIR__, 2) . '/storage/resumes';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        // When the default location is inside the web root, make sure Apache refuses direct access.
        $guard = dirname($dir) . '/.htaccess';
        if ($configured === '' && is_dir(dirname($dir)) && !is_file($guard)) {
            @file_put_contents($guard, "Require all denied\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n");
        }
        return rtrim($dir, '/\\');
    }

    /** Resolve a users.resume_path value (new private paths and legacy uploads/ paths) to a file. */
    public static function resolvePath(?string $stored): ?string {
        $stored = (string)$stored;
        if ($stored === '') return null;
        if (str_starts_with($stored, self::PRIVATE_PREFIX)) {
            $name = basename(substr($stored, strlen(self::PRIVATE_PREFIX)));
            $path = self::storageDir() . DIRECTORY_SEPARATOR . $name;
        } else {
            $base = realpath(dirname(__DIR__, 2));
            $path = realpath($base . '/' . ltrim($stored, '/'));
            if ($path === false || $base === false || !str_starts_with($path, $base)) return null;
        }
        return is_file($path) ? $path : null;
    }

    // ─── Processing ─────────────────────────────────────────────────────────

    public static function maxPages(): int {
        return max(1, (int)env('RESUME_MAX_PAGES', 10));
    }

    public static function maxBytes(): int {
        return max(1, (int)env('RESUME_MAX_SIZE_MB', 5)) * 1024 * 1024;
    }

    /**
     * @return array{status:string, method:string, pages:int, raw_text:string, parsed:?array, ai_used:bool}
     * @throws ResumeExtractionException
     */
    public function process(string $path, string $extension, array $accountHints, array $catalog): array {
        $extraction = (new ResumeTextExtractor(self::maxPages()))->extract($path, $extension);
        $text = $extraction['text'];
        $method = $extraction['method'];
        $aiUsed = false;
        $ai = null;

        if (ResumeAiExtractor::enabled()) {
            $ai = (new ResumeAiExtractor())->extract($text, $extraction['needs_ocr'] && $extension === 'pdf' ? $path : null);
            if ($ai !== null) {
                $aiUsed = true;
                if ($text === '') {
                    // Scanned PDF read by the AI: its transcription becomes the resume text.
                    $text = ResumeTextExtractor::cleanText($ai['source_text']);
                    $method = 'ai_vision';
                }
            }
        }

        if ($text === '') {
            return ['status' => 'needs_ocr', 'method' => $method, 'pages' => $extraction['pages'], 'raw_text' => '', 'parsed' => null, 'ai_used' => false];
        }

        $parsed = $this->parseText($text, $accountHints, $catalog, $ai['data'] ?? null, $method === 'ai_vision');
        return ['status' => 'parsed', 'method' => $method, 'pages' => $extraction['pages'], 'raw_text' => $text, 'parsed' => $parsed, 'ai_used' => $aiUsed];
    }

    /** Parse already-extracted text (used for client-side OCR results as well). */
    public function parseText(string $text, array $accountHints, array $catalog, ?array $ai = null, bool $fromImage = false): array {
        $parsed = (new ResumeParser($accountHints))->parse($text);
        if ($ai !== null) {
            $parsed = $this->mergeAi($parsed, $ai);
        }
        if ($fromImage) {
            // Text recognised from an image is less certain: nothing is marked high confidence.
            $parsed = $this->capConfidence($parsed);
            $parsed['warnings'][] = 'This resume appears to be scanned. Text was recognised from the image, so please check every field carefully.';
        }
        foreach ($parsed['skills'] as &$skill) {
            $match = SkillNormalizer::mapToCatalog($skill['canonical'] ?? $skill['name'], $catalog)
                ?? SkillNormalizer::mapToCatalog($skill['name'], $catalog);
            $skill['catalog_skill_id'] = $match['id'] ?? null;
            $skill['catalog_skill_name'] = $match['skill_name'] ?? null;
            $skill['catalog_match'] = $match['match'] ?? null;
        }
        unset($skill);
        $parsed['warnings'] = array_values(array_unique($parsed['warnings']));
        return $parsed;
    }

    private function capConfidence(array $parsed): array {
        $walk = function (&$node) use (&$walk) {
            if (!is_array($node)) return;
            if (isset($node['confidence']) && $node['confidence'] === 'high') $node['confidence'] = 'medium';
            foreach ($node as &$child) $walk($child);
        };
        $walk($parsed);
        return $parsed;
    }

    /** Combine AI-extracted (already grounded) values with the rule-based result. */
    private function mergeAi(array $parsed, array $ai): array {
        $evidence = 'AI-assisted extraction, verified against the resume text';
        $personal = &$parsed['personal'];
        foreach (['email' => 'email', 'phone' => 'phone', 'location' => 'location', 'full_name' => 'full_name'] as $aiKey => $key) {
            if (empty($personal[$key]) && !empty($ai[$aiKey])) {
                $personal[$key] = ['value' => $ai[$aiKey], 'confidence' => 'medium', 'evidence' => $evidence];
                if ($key === 'full_name') {
                    foreach ((new ResumeParser())->splitName($ai[$aiKey]) as $k => $v) {
                        if ($v !== '' && empty($personal[$k])) $personal[$k] = ['value' => $v, 'confidence' => 'medium', 'evidence' => $evidence];
                    }
                }
            }
        }
        unset($personal);
        if (empty($parsed['objective']) && !empty($ai['objective'])) {
            $parsed['objective'] = ['value' => $ai['objective'], 'confidence' => 'medium', 'evidence' => $evidence];
        }

        // Prefer the AI's entry structure when it found at least as many entries.
        if (count($ai['experience']) >= count($parsed['experience']) && $ai['experience']) {
            $parsed['experience'] = array_map(fn($e) => $this->aiExperience($e), $ai['experience']);
        }
        if (count($ai['education']) >= count($parsed['education']) && $ai['education']) {
            $parser = new ResumeParser();
            $parsed['education'] = array_map(function ($e) use ($parser) {
                $level = $parser->educationLevelOf(($e['degree'] ?? '') . ' ' . ($e['institution'] ?? ''));
                return $e + ['level' => $level, 'honors' => null, 'confidence' => 'medium', 'raw' => trim(($e['degree'] ?? '') . ' ' . ($e['institution'] ?? ''))];
            }, $ai['education']);
        }

        $known = [];
        foreach ($parsed['skills'] as $s) $known[SkillNormalizer::key($s['name'])] = true;
        foreach ($ai['skills'] as $name) {
            $key = SkillNormalizer::key($name);
            if (isset($known[$key]) || mb_strlen($name) > 60) continue;
            $known[$key] = true;
            $canonical = SkillNormalizer::canonicalize($name);
            $parsed['skills'][] = ['name' => $name, 'as_written' => $name, 'canonical' => $canonical, 'category' => SkillNormalizer::categorize($canonical ?? $name), 'confidence' => 'medium', 'sources' => ['ai'], 'evidence' => $evidence, 'duplicates' => 0];
        }

        $listMerge = function (array $existing, array $names, string $kind) {
            $seen = array_map(fn($i) => mb_strtolower(preg_replace('/\W+/u', '', $i['name'])), $existing);
            foreach ($names as $name) {
                $n = is_array($name) ? $name['name'] : $name;
                $key = mb_strtolower(preg_replace('/\W+/u', '', $n));
                if (in_array($key, $seen, true)) continue;
                $seen[] = $key;
                $existing[] = ['name' => $n, 'date' => is_array($name) ? ($name['date'] ?? null) : null, 'kind' => $kind, 'confidence' => 'medium', 'raw' => $n, 'type' => $kind === 'certification' ? 'certification' : null, 'issuer' => is_array($name) ? ($name['issuer'] ?? null) : null];
            }
            return $existing;
        };
        $parsed['certifications'] = $listMerge($parsed['certifications'], $ai['certifications'], 'certification');
        $parsed['trainings'] = $listMerge($parsed['trainings'], $ai['trainings'], 'training');
        $parsed['awards'] = $listMerge($parsed['awards'], $ai['awards'], 'award');
        $parsed['projects'] = $listMerge($parsed['projects'], $ai['projects'], 'project');
        $parsed['volunteer'] = $listMerge($parsed['volunteer'], $ai['volunteer'], 'activity');
        if (!$parsed['languages']) {
            $parsed['languages'] = array_map(fn($l) => ['name' => $l, 'proficiency' => null, 'confidence' => 'medium'], $ai['languages']);
        }
        $parsed['total_experience_months'] = (new ResumeParser())->totalExperienceMonths($parsed['experience']);
        $parsed['ai_assisted'] = true;
        return $parsed;
    }

    private function aiExperience(array $e): array {
        $start = $e['start_date'] ? substr($e['start_date'] . '-01', 0, 7) : null;
        $end = $e['end_date'] ? substr($e['end_date'] . '-01', 0, 7) : null;
        $months = null;
        $warnings = [];
        $endForCalc = $e['is_current'] ? date('Y-m') : $end;
        if ($start && $endForCalc) {
            if ($endForCalc < $start) {
                $warnings[] = 'End date is earlier than start date.';
            } else {
                [$sy, $sm] = array_map('intval', explode('-', $start));
                [$ey, $em] = array_map('intval', explode('-', $endForCalc));
                $months = ($ey - $sy) * 12 + ($em - $sm) + 1;
            }
        }
        return [
            'title' => $e['title'], 'company' => $e['company'], 'location' => $e['location'],
            'start_date' => $start, 'end_date' => $e['is_current'] ? null : $end, 'is_current' => (bool)$e['is_current'],
            'duration_months' => $months, 'responsibilities' => array_slice($e['responsibilities'], 0, 15), 'achievements' => [],
            'confidence' => 'medium', 'warnings' => $warnings, 'raw' => trim(($e['title'] ?? '') . ' | ' . ($e['company'] ?? '')),
        ];
    }
}
