<?php
/**
 * Extracts readable text from uploaded resumes.
 *
 * PDFs are read with the pure-PHP smalot/pdfparser library (works on shared hosting),
 * using positioned text chunks to rebuild lines and reading order for multi-column
 * layouts and tables. When that yields no usable text and the server has poppler's
 * `pdftotext` with shell execution permitted, it is tried as a fallback.
 * PDFs without a usable text layer (scans/photos) are reported as needing OCR.
 */
class ResumeExtractionException extends RuntimeException {
    public string $reason;

    public function __construct(string $message, string $reason, int $code = 422) {
        parent::__construct($message, $code);
        $this->reason = $reason;
    }
}

class ResumeTextExtractor {
    /** A new layout column starts: the parser resets the section context. */
    public const COLUMN_BREAK = "\u{241E}";
    /** Floating content (text box, layout table) starts / ends: the parser restores the previous section afterwards. */
    public const BLOCK_START = "\u{241D}";
    public const BLOCK_END = "\u{241F}";
    public const MARKERS = [self::COLUMN_BREAK, self::BLOCK_START, self::BLOCK_END];

    private const W_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    private const MC_NS = 'http://schemas.openxmlformats.org/markup-compatibility/2006';

    /** Helvetica advance widths (1/1000 em) for ASCII 32–126, used to estimate where words end. */
    private const GLYPH_WIDTHS = [278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556, 1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556, 333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556, 556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584];

    private int $maxPages;

    public function __construct(int $maxPages = 10) {
        $this->maxPages = max(1, $maxPages);
    }

    /**
     * @return array{text:string, pages:int, method:string, needs_ocr:bool, has_images:bool}
     */
    public function extract(string $path, string $extension): array {
        if (!is_file($path) || filesize($path) === 0) {
            throw new ResumeExtractionException('The uploaded file is empty or could not be read.', 'empty');
        }
        return match ($extension) {
            'pdf' => $this->extractPdf($path),
            'docx' => $this->extractDocx($path),
            default => throw new ResumeExtractionException('Unsupported file type. Please upload a PDF or DOCX resume.', 'unsupported'),
        };
    }

    // ─── PDF ────────────────────────────────────────────────────────────────

    private function extractPdf(string $path): array {
        $handle = fopen($path, 'rb');
        $head = $handle ? (string)fread($handle, 1024) : '';
        if ($handle) fclose($handle);
        if (!str_contains($head, '%PDF-')) {
            throw new ResumeExtractionException('This file is not a valid PDF document. It may be corrupted or renamed from another format.', 'corrupted');
        }

        $raw = (string)file_get_contents($path);
        $hasImages = (bool)preg_match('/\/Subtype\s*\/Image/', $raw);
        $truncated = !str_contains(substr($raw, -2048), '%%EOF');
        unset($raw);

        $document = null;
        $parseError = null;
        if (class_exists(\Smalot\PdfParser\Parser::class)) {
            try {
                $config = new \Smalot\PdfParser\Config();
                $config->setRetainImageContent(false);
                $config->setDataTmFontInfoHasToBeIncluded(true);
                $config->setDecodeMemoryLimit(32 * 1024 * 1024);
                $document = (new \Smalot\PdfParser\Parser([], $config))->parseFile($path);
            } catch (\Throwable $e) {
                $parseError = $e->getMessage();
            }
        }

        if ($document === null && !$this->pdftotextAvailable()) {
            if ($parseError !== null && stripos($parseError, 'secured') !== false) {
                throw new ResumeExtractionException('This PDF is password-protected. Please remove the password and upload it again.', 'encrypted');
            }
            if ($parseError === null) {
                throw new ResumeExtractionException('PDF processing is not available on the server. Please contact the administrator.', 'unavailable', 503);
            }
            throw new ResumeExtractionException('The PDF could not be opened. It may be corrupted or use an unsupported format. Please re-export it as a PDF and try again.', 'corrupted');
        }

        $pageCount = $document ? count($document->getPages()) : $this->pdfinfoPageCount($path);
        if ($pageCount > $this->maxPages) {
            throw new ResumeExtractionException("Resumes are limited to {$this->maxPages} pages. Your file has {$pageCount} pages.", 'too_many_pages');
        }

        // Coordinate-based reconstruction keeps line and column structure; pdftotext
        // (when installed) is the fallback for fonts the PHP parser cannot decode.
        $text = $document !== null ? $this->layoutText($document) : '';
        $method = 'pdfparser';
        if ($this->quality($text) < 0.5 && $this->pdftotextAvailable()) {
            $fallback = $this->runPdftotext($path);
            if ($this->quality($fallback) > $this->quality($text)) {
                $text = $fallback;
                $method = 'pdftotext';
            }
        }

        $text = self::cleanText($text);
        // OCR is only needed when there is (almost) no text layer; short text-only files are still parsed.
        $letters = preg_match_all('/\p{L}/u', $text);
        $needsOcr = $this->quality($text) < 0.5 && ($letters < 20 || ($hasImages && $letters < 60));
        if ($needsOcr && !$hasImages) {
            // Nothing to read and nothing to recognise: not a scan, but a broken or blank file.
            if ($truncated || $pageCount < 1) {
                throw new ResumeExtractionException('The PDF could not be opened. It may be corrupted or incomplete. Please re-export it as a PDF and try again.', 'corrupted');
            }
            throw new ResumeExtractionException('This PDF does not contain any readable text or scanned pages. Please check that you uploaded the right file.', 'no_text');
        }

        return ['text' => $needsOcr ? '' : $text, 'pages' => max(1, (int)$pageCount), 'method' => $method, 'needs_ocr' => $needsOcr, 'has_images' => $hasImages];
    }

    /** Fraction-like score: 0 when there is no meaningful text, 1 for normal resume text. */
    private function quality(string $text): float {
        $letters = preg_match_all('/\p{L}/u', $text);
        if ($letters < 60) return 0.0;
        $words = preg_match_all('/\b\p{L}{2,}\b/u', $text);
        $printable = preg_match_all('/[\p{L}\p{N}\s.,;:()\-\/@+&\'"]/u', $text);
        $total = max(1, mb_strlen($text, 'UTF-8'));
        $ratio = $printable / $total;
        if ($ratio < 0.75 || $words < 15) return 0.2;
        return min(1.0, $ratio);
    }

    private function pdftotextAvailable(): bool {
        static $available = null;
        if ($available !== null) return $available;
        $setting = strtolower((string)env('RESUME_USE_PDFTOTEXT', 'auto'));
        if (in_array($setting, ['0', 'false', 'no', 'off'], true) || !function_exists('shell_exec')) {
            return $available = false;
        }
        $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
        if (in_array('shell_exec', $disabled, true)) {
            return $available = false;
        }
        $probe = PHP_OS_FAMILY === 'Windows' ? 'where pdftotext 2>NUL' : 'command -v pdftotext 2>/dev/null';
        $out = @shell_exec($probe);
        return $available = is_string($out) && trim($out) !== '';
    }

    private function runPdftotext(string $path): string {
        $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $cmd = 'pdftotext -enc UTF-8 -l ' . (int)$this->maxPages . ' ' . escapeshellarg($path) . ' - 2>' . $null;
        $out = @shell_exec($cmd);
        return is_string($out) ? $out : '';
    }

    private function pdfinfoPageCount(string $path): int {
        $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $out = @shell_exec('pdfinfo ' . escapeshellarg($path) . ' 2>' . $null);
        if (is_string($out) && preg_match('/Pages:\s+(\d+)/', $out, $m)) return (int)$m[1];
        return 1;
    }

    /**
     * Rebuild reading order from positioned chunks: detect a column gutter per page,
     * then emit each column top-to-bottom. Chunks on one row that are far apart
     * (table cells, right-aligned dates) are joined with " | ".
     */
    private function layoutText($document): string {
        $pagesOut = [];
        foreach ($document->getPages() as $page) {
            $chunks = $this->positionedChunks($page);
            if (!$chunks) {
                try { $pagesOut[] = self::validUtf8((string)$page->getText()); } catch (\Throwable $e) {}
                continue;
            }
            $pagesOut[] = $this->renderPage($chunks);
        }
        return implode("\n\n", $pagesOut);
    }

    private function renderPage(array $chunks): string {
        $chunks = $this->mergeRuns($chunks);
        $chunks = array_values(array_filter($chunks, fn($c) => trim($c['text']) !== ''));
        if (!$chunks) return '';

        $split = $this->findGutter($chunks);
        $columns = $split === null ? [$chunks] : [
            array_values(array_filter($chunks, fn($c) => $c['x'] < $split)),
            array_values(array_filter($chunks, fn($c) => $c['x'] >= $split)),
        ];

        $out = [];
        foreach ($columns as $column) {
            $out[] = $this->renderColumn($column);
        }
        // The marker tells the parser that a new column starts, so section context resets.
        return implode("\n\n" . self::COLUMN_BREAK . "\n\n", array_filter($out, fn($t) => trim($t) !== ''));
    }

    /**
     * Device-space position of every text-showing operation on the page.
     *
     * smalot's getDataTm() folds the graphics transform (cm) into explicit Tm operators
     * only, so pages that combine "cm" with Td/T* moves (Word, Canva, browsers) come out
     * mirrored or scrambled. This replays the same decoded operators while tracking the
     * graphics-state stack, text matrix and text line matrix as the PDF spec defines.
     */
    private function positionedChunks($page): array {
        try {
            $commands = $page->getDataCommands();
            $texts = $page->getTextArray();
        } catch (\Throwable $e) {
            return $this->smalotChunks($page);
        }
        $identity = [1.0, 0.0, 0.0, 1.0, 0.0, 0.0];
        $ctm = $identity; $stack = [];
        $tm = $identity; $tlm = $identity;
        $leading = 0.0; $fontSize = 10.0;
        $chunks = [];
        $index = 0;
        $show = function () use (&$chunks, &$index, $texts, &$tm, &$ctm, &$fontSize) {
            if (!array_key_exists($index, $texts)) return;
            $text = self::validUtf8((string)$texts[$index++]);
            if ($text === '') return;
            $m = self::multiply($tm, $ctm);
            $scale = sqrt($m[2] * $m[2] + $m[3] * $m[3]);
            $size = $fontSize * ($scale ?: 1.0);
            if ($size <= 0 || $size > 300) $size = 10.0;
            $chunks[] = ['x' => $m[4], 'y' => $m[5], 'text' => $text, 'size' => $size];
        };
        foreach ($commands as $command) {
            if ($index >= count($texts)) break;
            $op = $command['o'] ?? '';
            $args = is_string($command['c'] ?? null) ? preg_split('/\s+/', trim($command['c'])) : [];
            switch ($op) {
                case 'q': $stack[] = $ctm; break;
                case 'Q': $ctm = array_pop($stack) ?? $identity; break;
                case 'cm':
                    if (count($args) >= 6) $ctm = self::multiply(array_map('floatval', array_slice($args, 0, 6)), $ctm);
                    break;
                case 'BT': $tm = $identity; $tlm = $identity; break;
                case 'Tf':
                    $size = (float)end($args);
                    if ($size != 0) $fontSize = abs($size);
                    break;
                case 'TL': $leading = (float)($args[0] ?? 0); break;
                case 'Tm':
                    if (count($args) >= 6) { $tm = array_map('floatval', array_slice($args, 0, 6)); $tlm = $tm; }
                    break;
                case 'Td':
                case 'TD':
                    $tx = (float)($args[0] ?? 0); $ty = (float)($args[1] ?? 0);
                    if ($op === 'TD') $leading = -$ty;
                    $tlm = self::multiply([1, 0, 0, 1, $tx, $ty], $tlm);
                    $tm = $tlm;
                    break;
                case 'T*':
                    $tlm = self::multiply([1, 0, 0, 1, 0, -$leading], $tlm);
                    $tm = $tlm;
                    break;
                case "'":
                case '"':
                    $tlm = self::multiply([1, 0, 0, 1, 0, -$leading], $tlm);
                    $tm = $tlm;
                    $show();
                    break;
                case 'Tj':
                case 'TJ':
                    $show();
                    break;
            }
        }
        // If the operator stream and decoded texts do not line up, use the library's positions.
        if (count($chunks) < count($texts) * 0.9) {
            return $this->smalotChunks($page);
        }
        return $chunks;
    }

    private function smalotChunks($page): array {
        try {
            $data = $page->getDataTm();
        } catch (\Throwable $e) {
            return [];
        }
        $chunks = [];
        foreach ($data as $item) {
            $text = self::validUtf8((string)($item[1] ?? ''));
            if ($text === '') continue;
            $tm = $item[0] ?? [];
            $size = (float)($item[3] ?? 10) * (abs((float)($tm[3] ?? 1)) ?: 1.0);
            if ($size <= 0 || $size > 300) $size = 10.0;
            $chunks[] = ['x' => (float)($tm[4] ?? 0), 'y' => (float)($tm[5] ?? 0), 'text' => $text, 'size' => $size];
        }
        return $chunks;
    }

    /** 2D affine matrix product a×b in PDF [a b c d e f] form. */
    private static function multiply(array $a, array $b): array {
        return [
            $a[0] * $b[0] + $a[1] * $b[2],
            $a[0] * $b[1] + $a[1] * $b[3],
            $a[2] * $b[0] + $a[3] * $b[2],
            $a[2] * $b[1] + $a[3] * $b[3],
            $a[4] * $b[0] + $a[5] * $b[2] + $b[4],
            $a[4] * $b[1] + $a[5] * $b[3] + $b[5],
        ];
    }

    /** Drop byte sequences that are not valid UTF-8 (e.g. emoji from fonts without a Unicode map). */
    private static function validUtf8(string $text): string {
        if (mb_check_encoding($text, 'UTF-8')) return $text;
        $previous = mb_substitute_character();
        mb_substitute_character(0xFFFD);
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        mb_substitute_character($previous);
        return str_replace("\u{FFFD}", '', $text);
    }

    /** Approximate advance width of one character in em. */
    private static function glyphWidth(string $char): float {
        $code = mb_ord($char, 'UTF-8');
        if ($code === false) return 0.55;
        if ($code >= 32 && $code <= 126) return self::GLYPH_WIDTHS[$code - 32] / 1000;
        if ($code === 0xA0) return 0.278;
        if ($code >= 0xC0 && $code <= 0x24F) return mb_strtoupper($char, 'UTF-8') === $char ? 0.7 : 0.55; // accented Latin letters
        return match (true) {
            $code === 0x2013 => 0.556, $code === 0x2014, $code === 0x2026 => 1.0,
            $code === 0x2022 => 0.35, $code === 0x2018, $code === 0x2019 => 0.222, $code === 0x201C, $code === 0x201D => 0.333,
            $code >= 0x2E80 => 1.0, // CJK, emoji and other wide symbols
            default => 0.6,
        };
    }

    private static function textWidth(string $text, float $size): float {
        $em = 0.0;
        foreach (mb_str_split($text, 1, 'UTF-8') as $char) $em += self::glyphWidth($char);
        return $em * $size;
    }

    /**
     * Calibrate width estimates against the page itself.
     * Returns [scale, explicitSpaces]: most generators (Word, Chrome/Canva, Google Docs) draw
     * real space characters, so touching glyphs belong to one word even when the font is wider
     * than the estimate; the scale comes from how far apart consecutive glyphs are drawn.
     */
    private function calibrate(array $chunks): array {
        $chars = 0; $spaces = 0;
        foreach ($chunks as $c) {
            $chars += mb_strlen($c['text'], 'UTF-8');
            $spaces += substr_count($c['text'], ' ');
        }
        $explicit = $chars > 0 && $spaces / $chars >= 0.05;
        $ratios = [];
        if ($explicit) {
            for ($i = 0, $n = count($chunks) - 1; $i < $n; $i++) {
                [$a, $b] = [$chunks[$i], $chunks[$i + 1]];
                $len = mb_strlen($a['text'], 'UTF-8');
                if ($len < 1 || $len > 3 || abs($a['y'] - $b['y']) > 0.5 || abs($a['size'] - $b['size']) > 0.1 || preg_match('/\s/u', $a['text'])) continue;
                $estimate = self::textWidth($a['text'], $a['size']);
                if ($estimate <= 0) continue;
                $ratio = ($b['x'] - $a['x']) / $estimate;
                if ($ratio > 0.6 && $ratio < 1.6) $ratios[] = $ratio;
            }
        }
        $scale = 1.0;
        if (count($ratios) >= 8) {
            sort($ratios);
            $scale = max(0.8, min(1.25, $ratios[intdiv(count($ratios), 2)]));
        }
        return [$scale, $explicit];
    }

    /** Merge consecutive glyphs/words drawn on the same baseline into runs. */
    private function mergeRuns(array $chunks): array {
        [$scale, $explicitSpaces] = $this->calibrate($chunks);
        // Without drawn spaces a small gap is a word break; with them, only a clear gap is.
        $spaceGap = $explicitSpaces ? 0.45 : 0.18;
        $runs = [];
        foreach ($chunks as $c) {
            $c['w'] = self::textWidth($c['text'], $c['size']) * $scale;
            $last = count($runs) - 1;
            if ($last >= 0) {
                $r = $runs[$last];
                $sameLine = abs($r['y'] - $c['y']) <= max(1.5, $c['size'] * 0.2);
                $gap = $c['x'] - ($r['x'] + $r['w']);
                if ($sameLine && $c['x'] >= $r['x'] && $gap < $c['size'] * 0.6) {
                    $needsSpace = $gap > $c['size'] * $spaceGap && !preg_match('/\s$/u', $r['text']) && !preg_match('/^\s/u', $c['text']);
                    $runs[$last]['text'] .= ($needsSpace ? ' ' : '') . $c['text'];
                    $runs[$last]['w'] = max($r['w'], $c['x'] + $c['w'] - $r['x']);
                    continue;
                }
            }
            $c['scale'] = $scale;
            $runs[] = $c;
        }
        foreach ($runs as &$r) {
            $r['text'] = preg_replace('/\s+/u', ' ', $r['text']) ?? $r['text'];
        }
        unset($r);
        return $runs;
    }

    /** Returns the x position of a vertical gutter separating two text columns, or null. */
    private function findGutter(array $chunks): ?float {
        $minX = min(array_column($chunks, 'x'));
        $maxRight = max(array_map(fn($c) => $c['x'] + $c['w'], $chunks));
        $width = $maxRight - $minX;
        if ($width < 200 || count($chunks) < 12) return null;

        // Candidate gutters are left edges of chunks that start a meaningful number of lines.
        $starts = [];
        foreach ($chunks as $c) {
            $bucket = (int)(round($c['x'] / 4) * 4);
            $starts[$bucket] = ($starts[$bucket] ?? 0) + 1;
        }
        ksort($starts);
        $best = null;
        $bestScore = 0;
        foreach ($starts as $x => $count) {
            $relative = ($x - $minX) / $width;
            if ($relative < 0.2 || $relative > 0.75 || $count < 4) continue;
            // Only text in the vertical band occupied by the right column can contradict a gutter;
            // full-width headers above or footers below the columns are allowed to span it.
            $rightYs = array_map(fn($c) => $c['y'], array_filter($chunks, fn($c) => $c['x'] >= $x - 2));
            if (!$rightYs) continue;
            $bandTop = max($rightYs) + 4; $bandBottom = min($rightYs) - 4;
            $left = 0; $right = count($rightYs); $crossing = 0;
            foreach ($chunks as $c) {
                if ($c['x'] >= $x - 2) continue;
                $left++;
                if ($c['y'] <= $bandTop && $c['y'] >= $bandBottom && $c['x'] + $c['w'] > $x + 6) $crossing++;
            }
            if ($left < 4 || $right < 4) continue;
            // A real gutter is almost never crossed by text from the left column.
            if ($crossing / max(1, $left) > 0.08) continue;
            // Both sides must hold real text over a vertical span (not one right-aligned date column).
            $leftLines = count(array_unique(array_map(fn($c) => (int)round($c['y']), array_filter($chunks, fn($c) => $c['x'] < $x - 2))));
            $rightLines = count(array_unique(array_map(fn($c) => (int)round($c['y']), array_filter($chunks, fn($c) => $c['x'] >= $x - 2))));
            if ($leftLines < 4 || $rightLines < 4) continue;
            $rightText = array_sum(array_map(fn($c) => mb_strlen($c['text']), array_filter($chunks, fn($c) => $c['x'] >= $x - 2)));
            $leftText = array_sum(array_map(fn($c) => mb_strlen($c['text']), array_filter($chunks, fn($c) => $c['x'] < $x - 2)));
            if (min($leftText, $rightText) < 80) continue;
            // Labels or dates beside their content (Europass, timelines) are rows, not a sidebar.
            if ($this->isLabelColumn($chunks, (float)$x)) continue;
            $score = $count + min($leftLines, $rightLines);
            if ($score > $bestScore) { $bestScore = $score; $best = (float)$x - 2; }
        }
        return $best;
    }

    /**
     * A left "column" whose rows sit on the same baselines as the content beside them and that
     * holds dates/section labels (or is right-aligned toward the content) labels that content.
     */
    private function isLabelColumn(array $chunks, float $x): bool {
        $rows = [];
        $right = [];
        foreach ($chunks as $c) {
            if ($c['x'] >= $x - 2) { $right[] = $c['y']; continue; }
            foreach ($rows as &$row) {
                if (abs($row['y'] - $c['y']) <= 1.5) {
                    $row['text'] .= ' ' . $c['text'];
                    $row['l'] = min($row['l'], $c['x']);
                    $row['r'] = max($row['r'], $c['x'] + $c['w']);
                    continue 2;
                }
            }
            unset($row);
            $rows[] = ['y' => $c['y'], 'text' => $c['text'], 'l' => $c['x'], 'r' => $c['x'] + $c['w']];
        }
        if (count($rows) < 4) return false;
        $aligned = 0;
        $labelish = 0;
        foreach ($rows as $row) {
            foreach ($right as $y) {
                if (abs($y - $row['y']) <= 2.5) { $aligned++; break; }
            }
            $text = trim($row['text']);
            if (preg_match('/\b(?:19|20)\d{2}\b|\bpresent\b/i', $text) || (mb_strlen($text) <= 40 && $text === mb_strtoupper($text) && preg_match('/\p{L}{3}/u', $text))) $labelish++;
        }
        $deviation = function (array $values): float {
            sort($values);
            $median = $values[intdiv(count($values), 2)];
            $dev = array_map(fn($v) => abs($v - $median), $values);
            sort($dev);
            return $dev[intdiv(count($dev), 2)];
        };
        $rightAligned = $deviation(array_column($rows, 'r')) <= 3 && $deviation(array_column($rows, 'l')) > 6;
        return $aligned / count($rows) >= 0.5 && ($rightAligned || $labelish / count($rows) >= 0.5);
    }

    private function renderColumn(array $chunks): string {
        if (!$chunks) return '';
        // PDF y grows upwards; sort top-to-bottom, then left-to-right.
        usort($chunks, fn($a, $b) => abs($a['y'] - $b['y']) > 2 ? $b['y'] <=> $a['y'] : $a['x'] <=> $b['x']);
        $rows = [];
        foreach ($chunks as $c) {
            $last = count($rows) - 1;
            $tolerance = max(2.0, $c['size'] * 0.35);
            if ($last >= 0 && abs($rows[$last]['y'] - $c['y']) <= $tolerance) {
                $rows[$last]['items'][] = $c;
            } else {
                $rows[] = ['y' => $c['y'], 'size' => $c['size'], 'items' => [$c]];
            }
        }

        // Gaps between runs on a row; a gap that starts at the same x on another row is a table column.
        foreach ($rows as &$row) {
            usort($row['items'], fn($a, $b) => $a['x'] <=> $b['x']);
            $row['gaps'] = [];
            $endX = null;
            foreach ($row['items'] as $k => $item) {
                if ($endX !== null) $row['gaps'][$k] = $item['x'] - $endX;
                $endX = max($endX ?? $item['x'], $item['x'] + $item['w']);
            }
            $row['end'] = $endX;
        }
        unset($row);
        $columnStarts = [];
        foreach ($rows as $r => $row) {
            foreach ($row['gaps'] as $k => $gap) {
                if ($gap >= $row['items'][$k]['size'] * 0.6) $columnStarts[] = [$r, $row['items'][$k]['x']];
            }
        }
        $aligned = function (int $r, float $x) use ($columnStarts): bool {
            foreach ($columnStarts as [$other, $sx]) {
                if ($other !== $r && abs($sx - $x) <= 2.5) return true;
            }
            return false;
        };

        $lines = [];
        $prevY = null;
        $prevSize = 10;
        foreach ($rows as $r => $row) {
            $line = '';
            $separators = 0;
            $tail = null;
            $bodyEnd = $row['items'][0]['x'] + $row['items'][0]['w'];
            $lastIndex = count($row['items']) - 1;
            foreach ($row['items'] as $k => $item) {
                $piece = $item['text'];
                if ($k > 0) {
                    $gap = $row['gaps'][$k];
                    // A date placed at the end of a row (right-aligned) is its own cell.
                    $dateCell = $k === $lastIndex && $gap >= $item['size'] * 0.6 && self::isDateText($piece);
                    if ($gap >= $item['size'] * 1.5 || $dateCell || ($gap >= $item['size'] * 0.6 && $aligned($r, $item['x']))) {
                        $line = rtrim($line) . ' | ';
                        $piece = ltrim($piece);
                        $separators++;
                        if ($k === $lastIndex && self::isDateText($piece)) $tail = ['text' => trim($piece), 'x' => $item['x']];
                    } elseif ($gap > $item['size'] * 0.15 && !preg_match('/\s$/u', $line) && !preg_match('/^\s/u', $piece)) {
                        $line .= ' ';
                    }
                }
                if ($k < $lastIndex || $tail === null) $bodyEnd = max($bodyEnd, $item['x'] + $item['w']);
                $line .= $piece;
            }
            $text = trim(preg_replace('/[ \t]+/u', ' ', $line));
            // A large vertical gap usually separates blocks (sections, entries).
            $blockGap = $prevY !== null && ($prevY - $row['y']) > max($prevSize, $row['size']) * 2.1;
            if ($blockGap) $lines[] = ['text' => ''];
            $lines[] = ['text' => $text, 'y' => $row['y'], 'size' => $row['size'], 'start' => $row['items'][0]['x'], 'end' => $row['end'],
                'separated' => $separators > 0, 'separators' => $separators, 'tail' => $separators === 1 ? $tail : null, 'bodyEnd' => $bodyEnd,
                'scale' => $row['items'][0]['scale'] ?? 1.0];
            $prevY = $row['y'];
            $prevSize = $row['size'];
        }
        return implode("\n", $this->joinWrappedLines($lines));
    }

    private static function isDateText(string $text): bool {
        $text = trim($text);
        return mb_strlen($text) <= 32 && (bool)preg_match('/^\(?\s*(?:(?:jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)\p{L}*\.?\s*,?\s*)?(?:\d{1,2}[\/\-.]\s*)?(?:(?:19|20)\d{2}\b|present\b|current\b)/iu', $text);
    }

    /**
     * Rejoin lines that the layout wrapped (long emails, URLs, certificate names in narrow
     * sidebars). A break is a wrap only when the next line's first word could not have fit
     * on the previous line, which is how word processors decide where a line ends.
     */
    private function joinWrappedLines(array $lines): array {
        $real = array_values(array_filter($lines, fn($l) => $l['text'] !== ''));
        if (count($real) < 4) return array_column($lines, 'text');
        // The column edge is set by lines that wrap (several words); one long token may overflow it.
        $wrapping = array_filter($real, fn($l) => str_contains($l['text'], ' ') && !$l['separated']);
        $right = max(array_column($wrapping ?: $real, 'end'));
        // Usual distance between consecutive lines; wrapped lines sit closer than separate items.
        $pitches = [];
        for ($i = 1; $i < count($real); $i++) {
            $pitch = $real[$i - 1]['y'] - $real[$i]['y'];
            if (abs($real[$i - 1]['size'] - $real[$i]['size']) <= 0.6 && $pitch > 0 && $pitch < 3 * $real[$i]['size']) $pitches[] = $pitch;
        }
        sort($pitches);
        $typicalPitch = $pitches ? $pitches[intdiv(count($pitches), 2)] : 0.0;
        $out = [];
        $tail = null; // right-aligned date of the line being built, re-attached once it is complete
        $finish = function () use (&$out, &$tail) {
            if ($tail !== null) $out[count($out) - 1] .= ' | ' . $tail;
            $tail = null;
        };
        $prev = null;
        foreach ($lines as $line) {
            if ($prev !== null && $line['text'] !== '' && $this->isWrapped($prev, $line, $right, $typicalPitch)) {
                $last = count($out) - 1;
                $glue = (preg_match('/\p{L}-$/u', $out[$last]) && preg_match('/^\p{Ll}/u', $line['text']))
                    || (preg_match('/[@\/][^\s]*$/u', $out[$last]) && preg_match('/^[\p{Ll}\d.\-_\/]+$/u', $line['text'])) ? '' : ' ';
                $out[$last] .= $glue . $line['text'];
                $prev = $line; // the physical line decides whether the next one wraps
                continue;
            }
            $finish();
            $text = $line['text'];
            if (!empty($line['tail'])) {
                // Keep the date aside so wrapped text continues the description, not the date.
                $text = trim(preg_replace('/\s*\|\s*' . preg_quote($line['tail']['text'], '/') . '$/u', '', $text));
                $tail = $line['tail']['text'];
            }
            $out[] = $text;
            $prev = $line['text'] === '' ? null : $line;
        }
        $finish();
        return $out;
    }

    private function isWrapped(array $prev, array $next, float $right, float $typicalPitch): bool {
        if ($next['separated'] || ($prev['separated'] && empty($prev['tail']))) return false;
        if (!empty($prev['tail'])) {
            // Text beside a right-aligned date wraps at the date, not at the column edge.
            $right = $prev['tail']['x'] - $prev['size'] * 0.6;
            $prev = ['end' => $prev['bodyEnd'], 'text' => preg_replace('/\s*\|[^|]*$/u', '', $prev['text'])] + $prev;
        }
        if (abs($prev['size'] - $next['size']) > 0.6 || abs($prev['start'] - $next['start']) > 2.0) return false;
        $pitch = $prev['y'] - $next['y'];
        if ($pitch <= 0 || $pitch > max($prev['size'], $next['size']) * 1.75) return false;
        if (preg_match('/[.!?:;]$/u', $prev['text']) || preg_match('/^[•●▪◦‣➢➤►▶✓✔\-*]/u', $next['text'])) return false;
        // Contact details are separate items even when the next one is too long to have fit.
        $contact = '/^(?:\+?\(?\d[\d\s().\-]{6,}\d|[\w.%+\-]+@[\w.\-]+\.\w{2,}|(?:https?:\/\/|www\.)\S+)$/iu';
        if (preg_match($contact, $next['text']) || preg_match('/\d[\d\s().\-]{6,}\d$/u', $prev['text'])) return false;
        // The next line's first word must not have fit at the end of the previous line.
        $firstWord = preg_split('/\s+/u', $next['text'])[0];
        $needed = (self::textWidth(' ' . $firstWord, $next['size']) * ($next['scale'] ?? 1.0)) * 0.95;
        if ($prev['end'] + $needed <= $right + 1.0) return false;
        // ...and something must show that the text continues.
        $brokenToken = preg_match('/(?:[@\/]\S*|\p{L}-)$/u', $prev['text']) && preg_match('/^[\p{Ll}\d.\-_\/]/u', $next['text']);
        $connector = preg_match('/(?:[,&\/]|\b(?:and|of|in|for|the|with|to|at|de|del|ng|sa))$/iu', $prev['text']);
        $lowercase = preg_match('/^[\p{Ll}\d(&–\-]/u', $next['text']) && str_contains($prev['text'], ' ');
        $tighter = $typicalPitch > 0 && $pitch < $typicalPitch * 0.92 && str_contains($prev['text'], ' ');
        return $brokenToken || $connector || $lowercase || $tighter;
    }

    // ─── DOCX ───────────────────────────────────────────────────────────────

    private function extractDocx(string $path): array {
        if (!class_exists('ZipArchive')) {
            throw new ResumeExtractionException('DOCX processing is not available on this server. Please upload your resume as a PDF.', 'unavailable');
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new ResumeExtractionException('The DOCX file could not be opened. It may be corrupted.', 'corrupted');
        }
        // Check the uncompressed size first so a compressed "zip bomb" is never expanded.
        $stat = $zip->statName('word/document.xml');
        if (!$stat || $stat['size'] > 20 * 1024 * 1024) {
            $zip->close();
            throw new ResumeExtractionException('The DOCX file does not contain readable document text.', 'corrupted');
        }
        $xml = $zip->getFromName('word/document.xml');
        // Page headers/footers often hold the name and contact details.
        $parts = ['header' => [], 'footer' => []];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string)$zip->getNameIndex($i);
            $partStat = $zip->statIndex($i);
            if (preg_match('#^word/(header|footer)\d*\.xml$#', $name, $m) && $partStat && $partStat['size'] <= 2 * 1024 * 1024) {
                $parts[$m[1]][$name] = (string)$zip->getFromIndex($i);
            }
        }
        $zip->close();
        if (!$xml) {
            throw new ResumeExtractionException('The DOCX file does not contain readable document text.', 'corrupted');
        }
        ksort($parts['header'], SORT_NATURAL);
        ksort($parts['footer'], SORT_NATURAL);

        $lines = [];
        $seen = [];
        $addPart = function (string $partXml) use (&$lines, &$seen) {
            $partLines = $this->docxLines($partXml);
            $key = trim(implode("\n", array_diff($partLines, self::MARKERS)));
            if ($key === '' || isset($seen[$key]) || preg_match('/^(?:page\s*)?\d+(?:\s*(?:of|\/)\s*\d+)?$/i', $key)) return;
            $seen[$key] = true;
            array_push($lines, self::BLOCK_START, ...$partLines);
            $lines[] = self::BLOCK_END;
        };
        foreach ($parts['header'] as $partXml) $addPart($partXml);
        array_push($lines, ...$this->docxLines($xml));
        foreach ($parts['footer'] as $partXml) $addPart($partXml);

        $text = self::cleanText(implode("\n", $lines));
        return ['text' => $text, 'pages' => 1, 'method' => 'docx', 'needs_ocr' => $this->quality($text) < 0.5, 'has_images' => false];
    }

    /** Lines of one WordprocessingML part, following the document's own structure. */
    private function docxLines(string $xml): array {
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded || !$dom->documentElement) {
            // Damaged XML: fall back to plain tag stripping.
            $xml = preg_replace(['/<w:tab\/>/', '/<\/w:tc>/', '/<\/w:p>/', '/<w:br\/>/'], [' | ', ' | ', "\n", "\n"], $xml);
            return explode("\n", html_entity_decode(strip_tags((string)$xml), ENT_QUOTES | ENT_XML1, 'UTF-8'));
        }
        $root = $dom->getElementsByTagNameNS(self::W_NS, 'body')->item(0) ?? $dom->documentElement;
        $out = [];
        $this->docxBlock($root, $out);
        return $out;
    }

    private function docxBlock(DOMNode $node, array &$out): void {
        foreach ($node->childNodes as $child) {
            if (!$child instanceof DOMElement) continue;
            if ($child->namespaceURI === self::MC_NS) {
                // Alternate content: read the preferred version only (the fallback repeats it).
                if ($child->localName === 'AlternateContent') {
                    foreach ($child->childNodes as $alt) {
                        if ($alt instanceof DOMElement && $alt->localName === 'Choice') { $this->docxBlock($alt, $out); break; }
                    }
                }
                continue;
            }
            if ($child->namespaceURI !== self::W_NS) { $this->docxBlock($child, $out); continue; }
            match ($child->localName) {
                'p' => $this->docxParagraph($child, $out),
                'tbl' => $this->docxTable($child, $out),
                'sectPr', 'pPr', 'tblPr', 'del', 'moveFrom' => null,
                default => $this->docxBlock($child, $out), // sdt, sdtContent, customXml, ins, ...
            };
        }
    }

    private function docxParagraph(DOMElement $p, array &$out): void {
        $text = '';
        $boxes = [];
        $this->docxInline($p, $text, $boxes);
        $isListItem = false;
        foreach ($p->childNodes as $c) {
            if ($c instanceof DOMElement && $c->localName === 'pPr') {
                foreach ($c->childNodes as $prop) {
                    if ($prop instanceof DOMElement && $prop->localName === 'numPr') $isListItem = true;
                }
            }
        }
        // Text boxes anchored here are separate blocks (sidebars, banners), not part of this line.
        foreach ($boxes as $box) {
            array_push($out, self::BLOCK_START, ...$box);
            $out[] = self::BLOCK_END;
        }
        if (trim($text) === '') { $out[] = ''; return; }
        foreach (explode("\n", $text) as $i => $segment) {
            // A tab between words separates columns ("Sales Associate<tab>2019 – Present").
            $segment = preg_replace('/[^\S\t]*\t[\s]*/u', ' | ', trim($segment, " \t")) ?? $segment;
            if (trim($segment) === '') continue;
            $out[] = ($isListItem && $i === 0 ? '• ' : '') . trim($segment);
        }
    }

    private function docxInline(DOMNode $node, string &$text, array &$boxes): void {
        foreach ($node->childNodes as $c) {
            if (!$c instanceof DOMElement) continue;
            if ($c->namespaceURI === self::MC_NS) {
                if ($c->localName !== 'Fallback') $this->docxInline($c, $text, $boxes);
                continue;
            }
            if ($c->localName === 'txbxContent') {
                $box = [];
                $this->docxBlock($c, $box);
                $boxes[] = $box;
                continue;
            }
            if ($c->namespaceURI === self::W_NS) {
                switch ($c->localName) {
                    case 't': $text .= $c->textContent; continue 2;
                    case 'tab': $text .= "\t"; continue 2;
                    case 'br': case 'cr': $text .= "\n"; continue 2;
                    case 'noBreakHyphen': $text .= '-'; continue 2;
                    case 'sym': $text .= ' '; continue 2; // icon-font symbol
                    case 'pPr': case 'rPr': case 'instrText': case 'delText': case 'del': case 'moveFrom': case 'fldChar':
                    case 'commentReference': case 'footnoteReference': case 'endnoteReference':
                        continue 2;
                }
            }
            // Drawing/VML wrappers: only look for text boxes inside them; their numbers are not text.
            $this->docxInline($c, $text, $boxes);
        }
    }

    /**
     * Tables are either page layout (a sidebar column next to the main column) or data
     * (education/experience/personal-data rows). Layout cells become separate columns;
     * data rows become one line per row with " | " between cells.
     */
    private function docxTable(DOMElement $table, array &$out): void {
        $rows = [];
        foreach ($table->childNodes as $tr) {
            if (!$tr instanceof DOMElement || $tr->localName !== 'tr') continue;
            $cells = [];
            foreach ($tr->childNodes as $tc) {
                if (!$tc instanceof DOMElement || $tc->localName !== 'tc') continue;
                $lines = [];
                $this->docxBlock($tc, $lines);
                $cells[] = $lines;
            }
            $rows[] = $cells;
        }
        $content = fn(array $lines) => array_values(array_filter($lines, fn($l) => trim($l) !== '' && !in_array($l, self::MARKERS, true)));
        $layout = false;
        foreach ($rows as $cells) {
            $rich = count(array_filter($cells, fn($lines) => count($content($lines)) >= 3));
            if ($rich >= 2) $layout = true;
        }
        if ($layout) {
            $out[] = self::BLOCK_START;
            foreach ($rows as $cells) {
                foreach ($cells as $lines) {
                    if (!$content($lines)) continue;
                    $out[] = self::COLUMN_BREAK;
                    array_push($out, ...$lines);
                }
            }
            $out[] = self::BLOCK_END;
            return;
        }
        foreach ($rows as $cells) {
            $first = [];
            $rest = [];
            foreach ($cells as $lines) {
                $lines = $content($lines);
                if (!$lines) continue;
                $first[] = array_shift($lines);
                array_push($rest, ...$lines);
            }
            if ($first) {
                $out[] = implode(' | ', $first);
                array_push($out, ...$rest);
            }
        }
        $out[] = '';
    }

    // ─── Shared cleanup ─────────────────────────────────────────────────────

    public static function cleanText(string $text): string {
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        }
        $text = str_replace(
            ["\r\n", "\r", "\f", "\u{00A0}", "\u{FB01}", "\u{FB02}", "\u{FB00}", "\u{FB03}", "\u{FB04}", "\u{2010}", "\u{2011}", "\u{2012}", "\u{2013}", "\u{2014}", "\u{2015}", "\u{2018}", "\u{2019}", "\u{201C}", "\u{201D}", "\u{200B}", "\u{FEFF}"],
            ["\n", "\n", "\n\n", ' ', 'fi', 'fl', 'ff', 'ffi', 'ffl', '-', '-', '-', ' - ', ' - ', ' - ', "'", "'", '"', '"', '', ''],
            $text
        );
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $text) ?? $text;
        // Private-use glyphs (icon fonts for phone/email/location) carry no text.
        $text = preg_replace('/[\x{E000}-\x{F8FF}]/u', ' ', $text) ?? $text;
        $text = preg_replace('/[ \t]+/u', ' ', $text);
        $text = preg_replace("/ *\n */u", "\n", $text);
        $text = preg_replace("/\n{3,}/u", "\n\n", $text);
        return trim((string)$text);
    }
}
