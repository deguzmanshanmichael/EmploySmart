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
    public const COLUMN_BREAK = "\u{241E}";

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
                try { $pagesOut[] = (string)$page->getText(); } catch (\Throwable $e) {}
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
            $text = (string)$texts[$index++];
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
            $text = (string)($item[1] ?? '');
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

    /** Merge consecutive glyphs/words drawn on the same baseline into runs. */
    private function mergeRuns(array $chunks): array {
        $runs = [];
        foreach ($chunks as $c) {
            $c['w'] = mb_strlen($c['text'], 'UTF-8') * $c['size'] * 0.5;
            $last = count($runs) - 1;
            if ($last >= 0) {
                $r = $runs[$last];
                $sameLine = abs($r['y'] - $c['y']) <= max(1.5, $c['size'] * 0.2);
                $gap = $c['x'] - ($r['x'] + $r['w']);
                $lastChar = mb_substr($r['text'], -1);
                // Glyph advances vary (W vs i), so adjacency allows up to ~0.6em of estimate error.
                if ($sameLine && $c['x'] >= $r['x'] && $gap < $c['size'] * 0.6) {
                    $needsSpace = $gap > $c['size'] * 0.25 && mb_strlen($c['text']) > 1 && $lastChar !== ' ' && !str_starts_with($c['text'], ' ');
                    $runs[$last]['text'] .= ($needsSpace ? ' ' : '') . $c['text'];
                    $runs[$last]['w'] = max($r['w'], $c['x'] + $c['w'] - $r['x']);
                    continue;
                }
            }
            $runs[] = $c;
        }
        foreach ($runs as &$r) {
            $r['text'] = preg_replace('/\s+/u', ' ', $r['text']);
            // Re-estimate width from the run's own characters for gutter detection.
            $r['w'] = max($r['w'] * 0.85, mb_strlen(rtrim($r['text']), 'UTF-8') * $r['size'] * 0.45);
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
            $score = $count + min($leftLines, $rightLines);
            if ($score > $bestScore) { $bestScore = $score; $best = (float)$x - 2; }
        }
        return $best;
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

        $lines = [];
        $prevY = null;
        $prevSize = 10;
        foreach ($rows as $row) {
            usort($row['items'], fn($a, $b) => $a['x'] <=> $b['x']);
            $line = '';
            $endX = null;
            foreach ($row['items'] as $item) {
                $piece = $item['text'];
                if ($endX !== null) {
                    $gap = $item['x'] - $endX;
                    if ($gap > max(18, $item['size'] * 2.2)) {
                        $line = rtrim($line) . ' | ';
                    } elseif ($gap > $item['size'] * 0.15 && !preg_match('/\s$/u', $line) && !preg_match('/^\s/u', $piece)) {
                        $line .= ' ';
                    }
                }
                $line .= $piece;
                $endX = max($endX ?? 0, $item['x'] + $item['w']);
            }
            // A large vertical gap usually separates blocks (sections, entries).
            if ($prevY !== null && ($prevY - $row['y']) > max($prevSize, $row['size']) * 2.1) {
                $lines[] = '';
            }
            $lines[] = trim(preg_replace('/[ \t]+/u', ' ', $line));
            $prevY = $row['y'];
            $prevSize = $row['size'];
        }
        return implode("\n", $lines);
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
        $zip->close();
        if (!$xml) {
            throw new ResumeExtractionException('The DOCX file does not contain readable document text.', 'corrupted');
        }
        // Paragraphs and table cells become line breaks / separators before tags are stripped.
        $xml = preg_replace(['/<w:tab\/>/', '/<\/w:tc>/', '/<\/w:p>/', '/<w:br\/>/'], [' ', ' | ', "\n", "\n"], $xml);
        $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = self::cleanText($text);
        return ['text' => $text, 'pages' => 1, 'method' => 'docx', 'needs_ocr' => $this->quality($text) < 0.5, 'has_images' => false];
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
