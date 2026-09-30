<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Reads courses out of the NUC CCMAS 2023 OCR'd plain-text corpus.
 *
 * The corpus is not structured. Inside a programme the shape is:
 *
 *     Course Contents and Learning Outcomes
 *     100 Level
 *     GST 111: Communication in English
 *
 *     (2 Units C: LH15; PH 45)
 *
 *     Learning Outcomes
 *     1. identify possible sound patterns in English Language;
 *
 * and the real text is damaged: "MTH101" beside "MTH 102", en-dashes, column
 * tables linearised into one token per line, page furniture ("Computing", "60",
 * "New") injected mid-paragraph, and course titles that wrap.
 *
 * The design bias is one-directional. A course that cannot be read with
 * confidence is dropped and counted, never guessed, because a level
 * coordinator who finds a junk row in a search will add a junk course to a
 * real programme. Every rule below exists because a line in the corpus
 * demonstrated the need for it.
 */
class CcmasCourseExtractor
{
    /** A course only exists inside this section, and only until the first stop. */
    private const SECTION_START = 'Course Contents and Learning Outcomes';

    private const SECTION_STOPS = [
        'Minimum Academic Standards',
        'Global Course Structure',
        'Course Contents and Learning Outcomes',
        'Overview',
    ];

    /**
     * "100 Level", "300 LEVEL", "500-Level", "100-Level Courses", "200 Level
     * Ceramics option". The trailing group counts only when it cannot be a
     * sentence, because "300-Level is spent in industry)" and "500-Level.
     * Agriculture" are prose that happens to start with a number and a dash.
     */
    private const LEVEL_PATTERN = '/^(\d{3})\s*[-–—]?\s*[Ll][Ee][Vv][Ee][Ll]\b\s*[:\-–—]?\s*(.*)$/';

    /** Course number: exactly three digits, optionally suffixed ("ANA 201a"). */
    private const NUMBER = '\d{3}[A-Za-z]?';

    /** "<CODE> <number> <optional separator> <title>". */
    private const CODE_PATTERN = '/^([A-Za-z]{2,5})\s*[-–—]?\s*(' . self::NUMBER . ')(?!\d)\s*[:.\-–—\/]?\s*(.*)$/';

    /** The same line with nothing after the code: the title sits on the next line. */
    private const CODE_ONLY_PATTERN = '/^([A-Za-z]{2,5})\s*[-–—]?\s*(' . self::NUMBER . ')(?!\d)(?:\s*[\/&]\s*(' . self::NUMBER . ')|\s+(' . self::NUMBER . '))?\s*[:.\-–—]?\s*$/';

    /** "(2 Units C: LH 15; PH 45)", "(1 Unit)", "(2 units)". OCR doubles the U sometimes. */
    private const UNITS_PATTERN = '/\(\s*(\d+(?:\.\d+)?)\s*[Uu]{1,2}nit[e]?s?\b/';

    /**
     * A units fragment removed from a title line.
     *
     * The trailing `$` was the original form, and it was wrong in one common
     * case: the OCR frequently continues past the unit breakdown with the next
     * sentence, so "( 3 Units C: LH 45; PH 360) This course covers…" does not
     * end in the fragment. Anchoring at the end left both the unit metadata and
     * a sentence tail inside the stored title, and credit units came out null
     * for those rows.
     *
     * The group is required to be closed, and `\s*` on both sides means only a
     * whole parenthesised run is removed rather than a stray number in
     * brackets inside a real title.
     */
    private const UNITS_FRAGMENT_PATTERN = '/\s*\(\s*\d+(?:\.\d+)?\s*[Uu]{1,2}nit[e]?s?\b[^()]*\)\s*/';

    /**
     * A secondary code in a compound heading: "421: ", "LIN 403: ", "& 402 ",
     * ", 404 ".
     */
    private const LEADING_CODE_PATTERN = '/^(?:[A-Za-z]{2,5}\s*)?' . self::NUMBER . '(?!\d)\s*(?:[,\/&]\s*|\s+and\s+|\s+)?/';

    /** First words that mark a line as document furniture, never a course. */
    private const NOT_A_COURSE_PREFIXES = [
        'course', 'page', 'figure', 'table', 'units', 'source', 'unit',
    ];

    /** Labels that follow a real course heading and confirm one. */
    private const SECTION_LABELS = [
        'learning outcome', 'learning outcomes', 'course contents', 'course content',
    ];

    /** Running headers, watermarks and linearised table column fragments. */
    private const NOISE_PATTERN = '/^(new|\d{1,4}|&|-|–|c|lh|ph|pr|cp|tc|tt|ec|lt|sem|tot|total)$/i';

    /**
     * A title opening under a line that ends on a particle is a wrapped
     * sentence, not a title: "…, covered in PHY 101 and" / "PHY 102. However,
     * emphasis should be placed …". Measured over the corpus this removes 16
     * lines and removes nothing else.
     */
    private const DANGLING_PARTICLE_PATTERN = '/\b(and|or|of|in|the|to|for|by|with|as|than|from|that|which|are|is|was|were|on|at|a|an)$/i';

    /** A title opening here is a sentence tail or a bare list of codes. */
    private const BAD_FIRST_CHARACTER_PATTERN = '/^[,.;:\)\]\}>]/';

    /**
     * Longest title accepted. Across the whole corpus the longest real course
     * title is 88 characters; anything longer is a wrapped paragraph.
     */
    private const MAX_TITLE_LENGTH = 90;

    /** Non-blank lines scanned ahead when confirming a code-only heading. */
    private const CONFIRMATION_WINDOW = 4;

    /** @var list<string> */
    private array $lines = [];

    /** @var list<int> */
    private array $overviewIndexes = [];

    /** @var list<int> */
    private array $sectionStopIndexes = [];

    /** @var list<int> */
    private array $sectionStartIndexes = [];

    /**
     * Extract every course the document can prove.
     *
     * @return array{
     *     courses: list<array<string, mixed>>,
     *     rejected: list<array{line: int, text: string}>,
     *     unconfirmed: list<array{line: int, code: string, next: string}>
     * }
     */
    public function extract(string $text, string $documentSlug): array
    {
        $this->index($text);

        $courses = [];
        $rejected = [];
        $unconfirmed = [];
        $seen = [];
        $previousSection = -1;

        foreach ($this->sectionStartIndexes as $section) {
            $programme = $this->programmeTitle($section, $previousSection);
            $end = $this->sectionEnd($section);
            $previousSection = $section;

            $level = null;

            // Index into $courses of the row still waiting for its credit
            // units, or null when there is none.
            $pending = null;

            for ($i = $section + 1; $i < $end; $i++) {
                $line = $this->lines[$i];

                if ($line === '') {
                    continue;
                }

                $headerLevel = $this->levelOf($line);

                if ($headerLevel !== null) {
                    $level = $headerLevel;
                    $pending = null;

                    continue;
                }

                // Learning-outcome and course-content bullets are numbered
                // prose. A bare "200" here is a page number out of a
                // linearised table, never a course.
                if (ctype_digit($line[0])) {
                    continue;
                }

                if ($this->startsWithFurniture($line)) {
                    $pending = null;

                    continue;
                }

                $isInline = preg_match(self::CODE_PATTERN, $line, $inline) === 1;
                $isBare = preg_match(self::CODE_ONLY_PATTERN, $line, $bare) === 1;
                // A bare heading matches both patterns: CODE_PATTERN accepts an
                // empty title, so the "title is on the next line" case is the
                // one where the inline capture came back empty.
                $hasInlineTitle = $isInline && trim($inline[3]) !== '';

                if (! $isInline && ! $isBare) {
                    // Credit units normally sit on the line after the title, but
                    // the column layout also splits them into "C: LH 30)" /
                    // "(2" / "Units" across three lines.
                    if ($pending !== null && $courses[$pending]['credit_units'] === null) {
                        $units = $this->unitsOn($line, $this->lines[$i - 1] ?? '');

                        if ($units !== null) {
                            $courses[$pending]['credit_units'] = $units;
                        }
                    }

                    continue;
                }

                $codeName = $this->normaliseCode($isInline ? $inline[1] : $bare[1], $isInline ? $inline[2] : $bare[2]);

                if (! $hasInlineTitle) {
                    $recovered = $this->recoverTitle($i, $end);

                    if ($recovered[0] === null) {
                        $unconfirmed[] = [
                            'line' => $i + 1,
                            'code' => $codeName,
                            'next' => $this->nextNonBlankText($i + 1, $end),
                        ];

                        $pending = null;

                        continue;
                    }

                    [$title, $units] = $recovered;
                } else {
                    $previous = $this->previousNonBlank($i);

                    if ($previous !== null && preg_match(self::DANGLING_PARTICLE_PATTERN, $previous) === 1) {
                        // This line is the tail of the previous sentence.
                        $rejected[] = ['line' => $i + 1, 'text' => $line];
                        $pending = null;

                        continue;
                    }

                    [$title, $units] = $this->cleanTitle($inline[3]);
                }

                if ($title === null) {
                    $rejected[] = ['line' => $i + 1, 'text' => $line];
                    $pending = null;

                    continue;
                }

                $key = $documentSlug . '|' . ($programme ?? '') . '|' . $codeName . '|' . $title;

                if (isset($seen[$key])) {
                    $pending = null;

                    continue;
                }

                $seen[$key] = true;

                $courses[] = [
                    'course_code' => $codeName,
                    'title' => $title,
                    'credit_units' => $units,
                    'level' => $level,
                    'programme_title' => $programme,
                    'source_document' => $documentSlug,
                    'source_line' => $i + 1,
                    // A missing level or a missing programme is not a
                    // rejection, but it is not trusted either.
                    'status' => ($level === null || $programme === null) ? 'needs_review' : 'imported',
                    'is_active' => true,
                ];

                $pending = count($courses) - 1;
            }
        }

        return ['courses' => $courses, 'rejected' => $rejected, 'unconfirmed' => $unconfirmed];
    }

    /**
     * Split and normalise the document once, recording the line indexes the
     * section walk needs so it stays a pointer walk rather than a rescan.
     */
    private function index(string $text): void
    {
        // Split on newlines only. PCRE's \R also matches the form feed the OCR
        // uses as a page break, which would make source_line disagree with
        // `grep -n` by hundreds on the longer documents.
        $raw = preg_split('/\r\n|\n|\r/u', $text) ?: [];

        $this->lines = [];
        $this->overviewIndexes = [];
        $this->sectionStopIndexes = [];
        $this->sectionStartIndexes = [];

        foreach ($raw as $line) {
            $line = trim((string) preg_replace('/\s{2,}/u', ' ', str_replace("\f", ' ', $line)));
            $index = count($this->lines);

            $this->lines[] = $line;

            if ($line === 'Overview') {
                $this->overviewIndexes[] = $index;
            }

            if ($line === self::SECTION_START) {
                $this->sectionStartIndexes[] = $index;
            }

            if (in_array($line, self::SECTION_STOPS, true)) {
                $this->sectionStopIndexes[] = $index;
            }
        }
    }

    /**
     * A level is only ever read from an explicit heading, never from the digits
     * of a course code.
     */
    private function levelOf(string $line): ?int
    {
        if (preg_match(self::LEVEL_PATTERN, $line, $m) !== 1) {
            return null;
        }

        $remainder = trim($m[2]);

        if ($remainder === '') {
            return (int) $m[1];
        }

        if (mb_strlen($remainder) > 60 || preg_match('/[.,;]/', $remainder) === 1) {
            return null;
        }

        if (count(preg_split('/\s+/', $remainder) ?: []) > 6) {
            return null;
        }

        return (int) $m[1];
    }

    /**
     * The programme a section belongs to.
     *
     * Every CCMAS programme opens with an "Overview" heading immediately after
     * its name, and programmes run sequentially, so the nearest "Overview"
     * above a course section is that programme's own. Null when the document
     * does not say — reported rather than guessed.
     */
    private function programmeTitle(int $section, int $previousSection): ?string
    {
        $anchor = null;

        for ($i = count($this->overviewIndexes) - 1; $i >= 0; $i--) {
            $index = $this->overviewIndexes[$i];

            if ($index >= $section) {
                continue;
            }

            if ($index <= $previousSection) {
                break;
            }

            $anchor = $index;

            break;
        }

        if ($anchor === null) {
            return null;
        }

        for ($i = $anchor - 1; $i >= 0 && $i > $anchor - 15; $i--) {
            $line = $this->lines[$i];

            if ($line === '' || $this->isNoise($line) || str_ends_with($line, '.')) {
                continue;
            }

            // Headings wrap: "B.Sc. Employment Relations and Human" followed by
            // "Resource Management".
            $above = $i > 0 ? $this->lines[$i - 1] : '';

            if (mb_strlen($line) < 70
                && $above !== ''
                && ! $this->isNoise($above)
                && ! str_ends_with($above, '.')
                && mb_strlen($above) < 70) {
                return $above . ' ' . $line;
            }

            return $line;
        }

        return null;
    }

    private function sectionEnd(int $section): int
    {
        foreach ($this->sectionStopIndexes as $index) {
            if ($index > $section) {
                return $index;
            }
        }

        return count($this->lines);
    }

    /**
     * A heading sometimes leaves the code alone on its line and puts the title
     * underneath. Recovering it needs positive confirmation, because "GST 111"
     * on its own line also appears inside a course-structure table.
     *
     * @return array{0: string|null, 1: float|null}
     */
    private function recoverTitle(int $index, int $end): array
    {
        $next = $index + 1;

        while ($next < $end && $this->lines[$next] === '') {
            $next++;
        }

        if ($next >= $end || ! $this->isTitleLike($this->lines[$next]) || ! $this->isConfirmedHeading($next, $end)) {
            return [null, null];
        }

        return $this->cleanTitle($this->lines[$next]);
    }

    private function nextNonBlankText(int $from, int $end): string
    {
        for ($i = $from; $i < $end; $i++) {
            if ($this->lines[$i] !== '') {
                return $this->lines[$i];
            }
        }

        return '';
    }

    /**
     * Normalise a title, or return null when the text after the code is not one.
     *
     * @return array{0: string|null, 1: float|null}
     */
    private function cleanTitle(string $raw): array
    {
        $title = trim($raw);
        $units = null;

        if (preg_match(self::UNITS_FRAGMENT_PATTERN, $title, $fragment, PREG_OFFSET_CAPTURE) === 1) {
            $units = $this->unitsOn($fragment[0][0]);
            // The fragment is removed wherever it sits rather than only at the
            // end, so prose that follows it is closed up instead of being
            // dropped along with the metadata. Without this the title kept a
            // dangling sentence tail such as "… Cooperatives This course".
            $title = (string) preg_replace(self::UNITS_FRAGMENT_PATTERN, ' ', $title, 1);
        }

        // "ENG 404/LIN 403: Multilingualism" and "MCM 401 & 402: Research
        // Project" carry more than one code before the real title.
        for ($i = 0; $i < 4; $i++) {
            $stripped = preg_replace(self::LEADING_CODE_PATTERN, '', $title, 1);

            if ($stripped === null || $stripped === $title) {
                break;
            }

            $title = $stripped;
        }

        $title = trim((string) preg_replace('/[\s,;:.\-–—]+$/u', '', $title));

        if ($title === '' || preg_match(self::BAD_FIRST_CHARACTER_PATTERN, $title) === 1) {
            return [null, $units];
        }

        if ($title[0] === '(' && ! ctype_alnum($title[1] ?? '')) {
            return [null, $units];
        }

        if (preg_match('/^[\(\[]?[A-Z0-9]/', $title) !== 1) {
            return [null, $units];
        }

        if (preg_match_all('/[A-Za-z]/', $title) < 3 || mb_strlen($title) > self::MAX_TITLE_LENGTH) {
            return [null, $units];
        }

        return [$title, $units];
    }

    private function isTitleLike(string $line): bool
    {
        if ($line === '' || mb_strlen($line) > self::MAX_TITLE_LENGTH) {
            return false;
        }

        if ($this->isNoise($line) || in_array(mb_strtolower($line), self::SECTION_LABELS, true)) {
            return false;
        }

        if (ctype_digit($line[0]) || $this->carriesUnits($line)) {
            return false;
        }

        if (preg_match('/^[\(\[]?[A-Z0-9]/', $line) !== 1) {
            return false;
        }

        return preg_match_all('/[A-Za-z]/', $line) >= 3;
    }

    /**
     * Positive evidence that the line under a bare code really is a title: a
     * credit-units line or a section label within the next few non-blank lines,
     * without crossing another course heading.
     */
    private function isConfirmedHeading(int $index, int $end): bool
    {
        $seen = 0;

        for ($i = $index + 1; $i < $end && $seen < self::CONFIRMATION_WINDOW; $i++) {
            $line = $this->lines[$i];

            if ($line === '') {
                continue;
            }

            $seen++;

            if ($this->carriesUnits($line) || in_array(mb_strtolower($line), self::SECTION_LABELS, true)) {
                return true;
            }

            if (preg_match(self::CODE_PATTERN, $line) === 1 || preg_match(self::CODE_ONLY_PATTERN, $line) === 1) {
                return false;
            }
        }

        return false;
    }

    private function carriesUnits(string $line): bool
    {
        return preg_match(self::UNITS_PATTERN, $line) === 1
            || preg_match('/^[Uu]{1,2}nit[e]?s?$/i', $line) === 1;
    }

    /**
     * Credit units for the line, or for the "(2" that a split column left
     * behind when the word "Units" is on its own line underneath.
     */
    private function unitsOn(string $line, string $previous = ''): ?float
    {
        if (preg_match(self::UNITS_PATTERN, $line, $m) === 1) {
            return (float) $m[1];
        }

        $bareNumber = '/^\(\s*(\d+(?:\.\d+)?)\s*$/';

        if (preg_match($bareNumber, trim($line), $m) === 1) {
            return (float) $m[1];
        }

        if ($line !== '' && preg_match('/^[Uu]{1,2}nit[e]?s?$/i', $line) === 1
            && preg_match($bareNumber, trim($previous), $m) === 1) {
            return (float) $m[1];
        }

        return null;
    }

    private function startsWithFurniture(string $line): bool
    {
        $lower = mb_strtolower($line);

        foreach (self::NOT_A_COURSE_PREFIXES as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function isNoise(string $line): bool
    {
        return preg_match(self::NOISE_PATTERN, $line) === 1;
    }

    private function previousNonBlank(int $index): ?string
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            if ($this->lines[$i] !== '') {
                return $this->lines[$i];
            }
        }

        return null;
    }

    private function normaliseCode(string $prefix, string $number): string
    {
        return mb_strtoupper($prefix) . ' ' . $number;
    }
}
