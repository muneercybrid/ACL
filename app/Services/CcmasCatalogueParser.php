<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Reads the NUC Core Curriculum and Minimum Academic Standards (CCMAS)
 * plain-text extractions and returns the national programme-of-study catalogue
 * together with the course codes each programme is built from.
 *
 * What the source actually looks like
 * -----------------------------------
 * The 17 files in `storage/app/nuc-ccmas` are text extractions of the CCMAS
 * PDFs. Three artefacts of that extraction drive the whole design:
 *
 *  1. **Page boundaries survive as form feeds (`\x0C`).** The text is split on
 *     them and the resulting 1-based index is exactly the printed page number
 *     the table of contents cites. That is the load-bearing fact: it lets a
 *     programme's section be located by page rather than by fuzzy title
 *     matching, which is what makes the extraction survive OCR damage.
 *
 *  2. **Table-of-contents lines are the only reliable programme index.** They
 *     look like `B.Sc. Accounting ..... 28` and every programme entry is
 *     immediately followed by its `Overview` sub-entry, which is a strong
 *     structural check that the line really is a programme and not a
 *     cross-reference. Leader characters vary by document (dots in most,
 *     dashes in medicine/law) and page numbers are sometimes OCR-glued
 *     (`1646` for page 164), so both are validated rather than trusted.
 *
 *  3. **Body headings are unreliable, the TOC is not.** A body heading may be
 *     wrapped across two lines, duplicated by a stray cross-reference, or
 *     missing entirely. So the TOC defines *which* programmes exist and in what
 *     order; the page numbers define *where* each one ends.
 *
 * Everything the parser returns is derived and re-derivable: no state is kept
 * between runs and no file is written, so re-running it is free and produces
 * byte-identical output for identical input.
 */
final class CcmasCatalogueParser
{
    /** Directory (relative to `storage/app`) holding the CCMAS text extractions. */
    public const SOURCE_DIRECTORY = 'nuc-ccmas';

    /**
     * Source document slug => `nuc_disciplines.code`.
     *
     * The mapping is stated here rather than guessed at runtime because the
     * filename is the only place a document declares its discipline, and the
     * discipline is a foreign key the catalogue needs. A file that is not
     * listed here is ignored rather than guessed at, because a wrong
     * discipline is worse than a missing programme.
     *
     * @var array<string, string>
     */
    public const DOCUMENTS = [
        'admin-mgmt' => 'ADM',
        'agriculture' => 'AGR',
        'allied-health' => 'AHS',
        'architecture' => 'ARC',
        'arts' => 'ART',
        'basic-medical' => 'BMS',
        'comm-media' => 'CMS',
        'computing' => 'CMP',
        'education' => 'EDU',
        'engineering' => 'ENG',
        'env-sciences' => 'ENV',
        'law' => 'LAW',
        'medicine' => 'MED',
        'pharmacy' => 'PHA',
        'sciences' => 'SCI',
        'social-sciences' => 'SOC',
        'veterinary' => 'VET',
    ];

    /**
     * Leaders used to right-align page numbers in a table of contents.
     * Most documents use full stops; medicine and law use hyphen runs, and a
     * few use the middle-dot or ellipsis glyphs.
     */
    private const LEADER = '[.\x{00B7}\x{2022}\x{2026}\x{2013}\x{2014}\-]';

    /** A run of at least this many leader characters counts as a leader. */
    private const MIN_LEADER = 3;

    /**
     * A table-of-contents line must begin with an award abbreviation. The set
     * is deliberately generous about spacing and case (`B.Sc.`, `B. Sc.`,
     * `BSc`, `BSC`, `LL.B`, `B.Eng.`) because the extractions are inconsistent,
     * but it requires a dot or a following capital so ordinary prose and words
     * such as `Bed Side Teaching` cannot be mistaken for a programme.
     */
    private const AWARD_PREFIX = '(?<![A-Za-z])(?:B|M|LL|Ph|D|HND|NCE|PG|BA|MBA|MSc)'
        .'\.?\s?[A-Za-z]{1,6}\.?';

    /** `ABC101`, `ABC 101`, `ABC-101` — three to five capitals and three digits. */
    private const COURSE_CODE = '(?<![A-Za-z0-9])([A-Z]{2,5})[\s.\-]?(\d{3})(?![0-9])';

    /**
     * A course code standing alone on a line, optionally followed on the same
     * line by its title: this is the layout of the "Global Course Structure"
     * and "Course Contents and Learning Outcomes" tables.
     */
    private const CODE_LINE = '/^\s*([A-Z]{2,5})[\s.\-]?(\d{3})\s*[:.\-–—]?\s*(.*)$/u';

    /** Sub-entries that must follow a table-of-contents programme line. */
    private const OVERVIEW = '/^Overview\b/iu';

    /** Standalone heading that ends a programme and starts discipline-wide standards. */
    private const MINIMUM_STANDARDS = '/^Minimum Academic Standards\b/iu';

    /**
     * Duration statements, most specific first. Every pattern must produce a
     * UTME/full-time length, so the Direct Entry (three-year) figure never wins:
     * the national catalogue describes the standard route into the programme.
     *
     * @var list<string>
     */
    private const DURATION_PATTERNS = [
        // "The minimum duration of the X degree programme is four academic
        //  sessions for UTME" / "... is 4 academic sessions (4-year duration)"
        '/minimum\s+duration[^.]{0,160}?\b(?:is|of|are)\s+(?:a\s+|an\s+)?(\d|[a-z]{3,9})\s*(?:\(\d\)\s*)?academic\s+sessions?/iu',
        // "... shall be for a period of four academic sessions (eight semester)"
        '/duration\s+(?:of\s+the\s+\S+\s+)?(?:programme|course)?[^.]{0,80}?\b(?:is|shall\s+be|will\s+be)\s+(?:a\s+period\s+of\s+)?(\d|[a-z]{3,9})\s*(?:\(\d\)\s*)?academic\s+sessions?/iu',
        // "runs for five years", "shall run for six (6) years"
        '/\b(?:runs?|run|lasts?|spans?)\s+for\s+(?:a\s+period\s+of\s+)?(\d|[a-z]{3,9})\s*(?:\(\d\)\s*)?years?/iu',
        // "is a 6-year University training programme"
        '/\b(?:is|are|as)\s+an?\s+(\d)\s*-\s*year\s+(?:university\s+)?(?:training\s+)?programme/iu',
        // "a four-year programme", "the 5-year Bachelor of Radiography degree programme"
        '/\ba\s+(\d|[a-z]{3,9})\s*-\s*year\s+(?:degree\s+)?programme/iu',
    ];

    /** Word-to-number so both "four" and "4" are read the same way. */
    private const NUMBER_WORDS = [
        'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5,
        'six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10,
    ];

    /**
     * Award abbreviation => `programmes.degree_type`.
     *
     * `degree_type` is a single short token, so the raw abbreviation is
     * canonicalised (case and internal spacing) rather than free-typed from the
     * document, which spells the same degree six different ways.
     *
     * @var array<string, string>
     */
    private const DEGREE_TYPES = [
        'B.Sc.' => 'B.Sc.',
        'B.Sc' => 'B.Sc.',
        'B.SC' => 'B.Sc.',
        'B.S' => 'B.Sc.',
        'B.A.' => 'B.A.',
        'B.A' => 'B.A.',
        'B.Eng.' => 'B.Eng.',
        'B.Eng' => 'B.Eng.',
        'B.Tech.' => 'B.Tech.',
        'B.Tech' => 'B.Tech.',
        'B.Ed.' => 'B.Ed.',
        'B.Ed' => 'B.Ed.',
        'B.Sc.Ed.' => 'B.Sc.Ed.',
        'B.A.Ed.' => 'B.A.Ed.',
        'B.Sc.(Ed)' => 'B.Sc.Ed.',
        'B.Sc.(Ed)' => 'B.Sc.Ed.',
        'B.A.(Ed)' => 'B.A.Ed.',
        'B.Pharm.' => 'B.Pharm.',
        'B.M.L.S.' => 'B.M.L.S.',
        'B.MLS' => 'B.M.L.S.',
        'B.H.N.' => 'B.H.N.',
        'B.N.Sc.' => 'B.N.Sc.',
        'B.NSC' => 'B.N.Sc.',
        'B.M.Sc.' => 'B.M.Sc.',
        'M.Sc.' => 'M.Sc.',
        'MSc' => 'M.Sc.',
        'M.Sc' => 'M.Sc.',
        'M.A.' => 'M.A.',
        'M.A' => 'M.A.',
        'MBA' => 'MBA',
        'MPA' => 'MPA',
        'Ph.D.' => 'Ph.D.',
        'Ph.D' => 'Ph.D.',
        'LL.B' => 'LL.B',
        'LLB' => 'LL.B',
        'LL.M' => 'LL.M',
        'MBBS' => 'MBBS',
        'MBChB' => 'MBBS',
        'BDS' => 'BDS',
        'BChD' => 'BDS',
        'DVM' => 'DVM',
        'Pharm.D' => 'Pharm.D',
        'Pharm. D' => 'Pharm.D',
    ];

    /**
     * Parse every available CCMAS document.
     *
     * @return list<array<string, mixed>> one entry per programme, in document
     *                                     order then table-of-contents order
     */
    public function all(): array
    {
        $programmes = [];

        foreach ($this->availableDocuments() as $slug) {
            foreach ($this->parseDocument($slug) as $programme) {
                $programmes[] = $programme;
            }
        }

        return $programmes;
    }

    /**
     * The document slugs that are both declared and actually on disk, so a
     * partially-populated source directory is reported rather than fatal.
     *
     * @return list<string>
     */
    public function availableDocuments(): array
    {
        $available = [];

        foreach (array_keys(self::DOCUMENTS) as $slug) {
            if (is_readable($this->path($slug))) {
                $available[] = $slug;
            }
        }

        return $available;
    }

    public function path(string $slug): string
    {
        return storage_path('app/'.self::SOURCE_DIRECTORY.'/'.$slug.'.txt');
    }

    /**
     * Parse one document into its programmes.
     *
     * @return list<array<string, mixed>>
     */
    public function parseDocument(string $slug): array
    {
        $discipline = self::DOCUMENTS[$slug] ?? null;
        if ($discipline === null) {
            return [];
        }

        $raw = @file_get_contents($this->path($slug));
        if ($raw === false) {
            return [];
        }

        $pages = $this->pages($raw);
        $entries = $this->contentsEntries($pages);

        if ($entries === []) {
            return [];
        }

        // Resolve each entry to the page its section actually starts on, then
        // give every programme the page range up to the next programme.
        $starts = [];
        foreach ($entries as $index => $entry) {
            $starts[$index] = $this->resolveStartPage($entry, $pages, $starts);
        }

        $programmes = [];
        $count = count($entries);

        foreach ($entries as $index => $entry) {
            $from = $starts[$index];

            if ($index + 1 < $count) {
                $to = $starts[$index + 1] - 1;
            } else {
                $to = $this->lastProgrammePage($pages, $from);
            }

            if ($to < $from) {
                $to = $from;
            }

            $section = $this->sectionText($pages, $from, $to);

            $programmes[] = $this->buildProgramme($slug, $discipline, $entry, $section, $from);
        }

        return $programmes;
    }

    // ------------------------------------------------------------------ pages

    /**
     * The readable text of one programme, given its page range.
     *
     * Page footers ("Computing", "27", "New") are dropped: they sit at the
     * bottom of every page, carry no content, and the bare page number is a
     * reliable source of false three-digit course codes.
     *
     * @param list<list<string>> $pages
     */
    private function sectionText(array $pages, int $from, int $to): string
    {
        $text = [];

        for ($page = max(1, $from); $page <= min(count($pages), $to); $page++) {
            foreach ($pages[$page - 1] as $index => $line) {
                if ($this->isRunningFoot($line, count($pages[$page - 1]) - 1 - $index)) {
                    continue;
                }
                $text[] = $line;
            }
        }

        return implode("\n", $text);
    }

    /**
     * Is this the trailing "discipline name / page number / New" block the
     * extractor emits at the foot of every page?
     *
     * Only the last few lines of a page are ever considered, and a footer must
     * be exactly the page number plus the `New` marker, so a course line that
     * happens to be short is never mistaken for one.
     *
     * @param list<string> $lines
     */
    private function isRunningFoot(string $line, int $linesFromEnd): bool
    {
        if ($linesFromEnd > 3) {
            return false;
        }

        return $line === 'New' || $line === '-' || ctype_digit($line);
    }

    /**
     * Split the raw extraction into pages and normalise each page's lines.
     *
     * The form feed is the page boundary left behind by the PDF extractor, and
     * its 1-based position is the printed page number the table of contents
     * cites. Splitting before stripping control characters is what makes that
     * correspondence usable.
     *
     * @return list<string> one entry per page, each already line-normalised
     */
    private function pages(string $raw): array
    {
        $pages = [];

        foreach (explode("\x0C", $raw) as $page) {
            $lines = [];
            foreach (explode("\n", $page) as $line) {
                $line = $this->cleanLine($line);
                if ($line !== '') {
                    $lines[] = $line;
                }
            }
            $pages[] = $lines;
        }

        return $pages;
    }

    /**
     * Normalise one extracted line.
     *
     * The extractions carry a lot of near-invisible noise: non-breaking spaces,
     * soft hyphens, zero-width marks and a stray form feed at the start of a
     * line. Left in place they defeat every anchored pattern match below, so
     * they are removed here once rather than being worked around per pattern.
     */
    private function cleanLine(string $line): string
    {
        $line = str_replace(["\r", "\x0C", "\x0B", "\u{00AD}", "\u{200B}", "\u{FEFF}"], ['', ' ', ' ', '', '', ''], $line);

        // Curly quotes and dashes to their ASCII equivalents, so title
        // comparisons and leader detection do not have to know about both.
        $line = strtr($line, [
            "\u{2018}" => "'", "\u{2019}" => "'", "\u{201C}" => '"', "\u{201D}" => '"',
            "\u{2013}" => '-', "\u{2014}" => '-', "\u{2212}" => '-', "\u{00A0}" => ' ',
        ]);

        return trim(preg_replace('/\s+/u', ' ', $line) ?? '');
    }

    // ------------------------------------------------------- table of contents

    /**
     * Extract the programme entries from the table of contents.
     *
     * A line qualifies only when it carries a leader run, starts with an award
     * abbreviation, and is followed within a couple of lines by an `Overview`
     * entry. All three together are what separates a programme from every
     * other dotted line in the document.
     *
     * @param list<list<string>> $pages
     * @return list<array{title:string,page:int|null,line:string}>
     */
    private function contentsEntries(array $pages): array
    {
        $entries = [];
        $seen = [];

        foreach ($pages as $pageIndex => $lines) {
            $count = count($lines);

            for ($i = 0; $i < $count; $i++) {
                $line = $lines[$i];

                if (! preg_match('/^'.self::AWARD_PREFIX.'/u', $line)) {
                    continue;
                }

                if (! $this->hasLeader($line)) {
                    continue;
                }

                if (! $this->followedByOverview($lines, $i)) {
                    continue;
                }

                $title = $this->stripLeader($line);
                if ($title === '') {
                    continue;
                }

                $page = $this->leaderPage($line);
                $key = ProgrammeCatalogue::normalize($title);

                // The contents list each programme once. A repeated title is a
                // wrapped line or a second listing of the same programme.
                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $entries[] = ['title' => $title, 'page' => $page, 'line' => $line];
            }
        }

        // Contents order is the authoritative programme order, and a document
        // whose contents could not be read at all must not produce a catalogue
        // in an arbitrary order.
        usort($entries, fn ($a, $b) => ($a['page'] ?? PHP_INT_MAX) <=> ($b['page'] ?? PHP_INT_MAX));

        return $entries;
    }

    private function hasLeader(string $line): bool
    {
        return (bool) preg_match('/'.self::LEADER.'{'.self::MIN_LEADER.',}/u', $line);
    }

    /**
     * Strip the leader run and the page number, leaving the title.
     *
     * An em-dash or hyphen inside a title ("B.Sc. Peace Studies and Conflict
     * Resolution") is not a leader because it is not part of a run, but a
     * document that hyphenates a long title across two contents lines would
     * leave a trailing dash; it is dropped for that reason.
     */
    private function stripLeader(string $line): string
    {
        $title = preg_replace('/'.self::LEADER.'{'.self::MIN_LEADER.',}.*$/u', '', $line) ?? $line;

        return trim(preg_replace('/[\s\-–—:]+$/u', '', $title) ?? '');
    }

    /**
     * The page number a contents line points at, or null when there is none.
     *
     * Only digits attached to the leader are accepted. A bare trailing number
     * elsewhere on the line is not a page citation and is ignored rather than
     * guessed at.
     */
    private function leaderPage(string $line): ?int
    {
        if (! preg_match('/'.self::LEADER.'{'.self::MIN_LEADER.',}\s*(\d{1,4})\s*$/u', $line, $m)) {
            return null;
        }

        return (int) $m[1];
    }

    /** @param list<string> $lines */
    private function followedByOverview(array $lines, int $index): bool
    {
        $count = count($lines);

        // Contents wrap a long programme title onto its own line, so the
        // Overview entry is looked for a few lines ahead rather than only on
        // the very next one.
        for ($j = $index + 1; $j < min($count, $index + 4); $j++) {
            if (preg_match(self::OVERVIEW, $lines[$j])) {
                return true;
            }
        }

        return false;
    }

    // -------------------------------------------------------- section bounds

    /**
     * The page a programme's section starts on.
     *
     * The contents page number is trusted only when it is in range *and* the
     * page really does open that programme. Otherwise the page is searched for,
     * which is what recovers the OCR failures where a page number is glued to
     * the following column (`1646` for page 164) or a heading never made it
     * out of the PDF at all.
     *
     * @param list<list<string>> $pages
     * @param array<int,int> $resolved
     */
    private function resolveStartPage(array $entry, array $pages, array $resolved): int
    {
        $total = count($pages);
        $earliest = $resolved === [] ? 1 : (max($resolved) + 1);

        $candidates = [];

        if ($entry['page'] !== null && $entry['page'] >= 1 && $entry['page'] <= $total) {
            $candidates[] = $entry['page'];
        }

        // A glued page number is recoverable by truncation: `1646` is page 164.
        if ($entry['page'] !== null && $entry['page'] > $total) {
            $truncated = (int) substr((string) $entry['page'], 0, 3);
            if ($truncated >= 1 && $truncated <= $total) {
                $candidates[] = $truncated;
            }
        }

        $wanted = ProgrammeCatalogue::normalize($entry['title']);

        foreach ($candidates as $candidate) {
            if ($candidate < $earliest) {
                continue;
            }
            if ($this->pageOpens($pages[$candidate - 1] ?? [], $wanted)) {
                return $candidate;
            }
        }

        foreach ($candidates as $candidate) {
            if ($candidate >= 1 && $candidate <= $total) {
                return max($candidate, $earliest);
            }
        }

        return $this->searchForProgramme($pages, $wanted, $earliest) ?? max(1, min($earliest, $total));
    }

    /**
     * Does this page start the programme? Compared on normalised text so that
     * `B. Sc.  Accounting` and `B.Sc. Accounting` are the same page, and so
     * that a two-line contents title matches the one-line body heading.
     *
     * @param list<string> $lines
     */
    private function pageOpens(array $lines, string $wanted): bool
    {
        if ($wanted === '') {
            return false;
        }

        foreach (array_slice($lines, 0, 6) as $line) {
            $normalised = ProgrammeCatalogue::normalize($line);
            if ($normalised === '' || ! str_starts_with($normalised, 'overview')) {
                continue;
            }

            // The heading is the content directly above the Overview entry.
            $heading = null;
            for ($i = count($lines) - 1; $i >= 0; $i--) {
                $candidate = ProgrammeCatalogue::normalize($lines[$i]);
                if ($candidate !== '' && ! str_starts_with($candidate, 'overview')) {
                    $heading = $candidate;
                    break;
                }
            }

            if ($heading === null) {
                continue;
            }

            if ($heading === $wanted || str_starts_with($heading, $wanted) || str_starts_with($wanted, $heading)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Last resort: find the first page at or after `$from` that opens the
     * programme, scanning the whole document.
     *
     * @param list<list<string>> $pages
     */
    private function searchForProgramme(array $pages, string $wanted, int $from): ?int
    {
        foreach ($pages as $index => $lines) {
            $page = $index + 1;
            if ($page < $from) {
                continue;
            }
            if ($this->pageOpens($lines, $wanted)) {
                return $page;
            }
        }

        return null;
    }

    /**
     * Where the final programme's section ends.
     *
     * Everything after the last programme in a discipline document is
     * discipline-wide material — the Minimum Academic Standards for
     * laboratories, staffing and libraries — which belongs to no programme.
     * Left in scope it would attach other programmes' course codes to the last
     * one, so the section is cut at that heading when it is present.
     *
     * @param list<list<string>> $pages
     */
    private function lastProgrammePage(array $pages, int $from): int
    {
        $total = count($pages);

        for ($index = $from; $index < $total; $index++) {
            foreach (array_slice($pages[$index], 0, 4) as $line) {
                if (preg_match(self::MINIMUM_STANDARDS, $line)) {
                    // The heading itself is not part of the programme, but the
                    // pages before it are.
                    return max($from, $index);
                }
            }
        }

        return $total;
    }

    // ------------------------------------------------------------- programme

    /**
     * @return array<string, mixed>
     */
    private function buildProgramme(string $slug, string $discipline, array $entry, string $section, int $page): array
    {
        $courses = $this->courseCodes($section);

        return [
            'name' => $this->tidyTitle($entry['title']),
            'normalized_name' => ProgrammeCatalogue::normalize($entry['title']),
            'degree_type' => $this->degreeType($entry['title']),
            'duration_years' => $this->durationYears($section),
            'nuc_discipline_code' => $discipline,
            'source_document' => self::SOURCE_DIRECTORY.'/'.$slug.'.txt',
            'source_page' => $page,
            'course_codes' => array_keys($courses),
            'courses' => $courses,
        ];
    }

    /**
     * Normalise the contents spelling of a programme title.
     *
     * Contents titles carry OCR damage that is obvious to a reader and
     * corrosive to a catalogue key: `B. Sc / B. Tech ENVIRONMENTAL STANDARDS`
     * is an all-caps artefact, and several documents drop the final full stop
     * of the abbreviation. Both are corrected here rather than in the database
     * so the same document always yields the same name.
     */
    private function tidyTitle(string $title): string
    {
        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? $title);

        // Leading multiple award abbreviations are collapsed to the first one;
        // the others describe an alternative award of the *same* programme.
        if (preg_match('/^((?:'.self::DEGREE_TYPES_NORMALISED.'))\b/i', $title, $m)) {
            $rest = trim(substr($title, strlen($m[0])));
            $title = rtrim($m[1], '. ').'. '.$rest;
            $title = ltrim($title, '/ ');
        }

        // An all-caps tail is a heading artefact, not a real difference in name.
        $parts = explode(' ', $title);
        foreach ($parts as $i => $part) {
            if (strlen($part) > 3 && $part === mb_strtoupper($part)) {
                $parts[$i] = ucfirst(mb_strtolower($part));
            }
        }

        return trim(implode(' ', $parts));
    }

    /**
     * The award a programme leads with, canonicalised to a short token.
     *
     * `degree_type` is a controlled-ish column, so the raw spelling is mapped
     * through a table rather than written through as typed; an abbreviation
     * that is not in the table falls back to the leading token itself so the
     * programme is still imported with an honest value.
     */
    private function degreeType(string $title): string
    {
        if (preg_match('/^('.self::AWARD_PREFIX.')/u', $title, $m)) {
            $raw = trim($m[1]);
            $raw = preg_replace('/\s+/u', '', $raw) ?? $raw;

            $spaced = preg_replace('/\.(?=[A-Za-z])/', '. ', $raw) ?? $raw;
            $spaced = preg_replace('/\.(?=\s)/', '.', $spaced) ?? $spaced;

            foreach (self::DEGREE_TYPES as $key => $canonical) {
                if (strcasecmp($key, $raw) === 0 || strcasecmp($key, $spaced) === 0) {
                    return $canonical;
                }
            }

            return rtrim($raw, '.');
        }

        // Award named in brackets, e.g. "Bachelor of Pharmacy (B. Pharm.)".
        if (preg_match('/\(([A-Z][A-Za-z. ]{1,20})\)\s*$/u', $title, $m)) {
            $inner = trim($m[1]);
            foreach (self::DEGREE_TYPES as $key => $canonical) {
                if (strcasecmp($key, $inner) === 0) {
                    return $canonical;
                }
            }
        }

        return 'B.Sc.';
    }

    /**
     * How many years the programme runs, read from the admission section.
     *
     * Conservative by design: a programme whose duration cannot be stated in
     * one of the CCMAS's own standard phrasings returns null rather than a
     * guess, and the caller then leaves the column at its default instead of
     * asserting something the document does not say.
     */
    private function durationYears(string $section): ?int
    {
        foreach (self::DURATION_PATTERNS as $pattern) {
            if (preg_match($pattern, $section, $m)) {
                $years = $this->toInt($m[1] ?? null);
                if ($years !== null && $years >= 2 && $years <= 10) {
                    return $years;
                }
            }
        }

        return null;
    }

    private function toInt(?string $token): ?int
    {
        if ($token === null) {
            return null;
        }

        $token = trim($token);
        if ($token === '') {
            return null;
        }

        if (ctype_digit($token)) {
            return (int) $token;
        }

        return self::NUMBER_WORDS[mb_strtolower($token)] ?? null;
    }

    // ---------------------------------------------------------------- courses

    /**
     * The course codes a programme is built from, with their titles where the
     * document supplies one.
     *
     * Two layouts are read, because CCMAS uses both:
     *
     *  - `GST 111: Communication in English` — code and title on one line, the
     *    "Course Contents and Learning Outcomes" style;
     *  - `GST 111` followed by the title on the next line, the flattened
     *    "Global Course Structure" table style.
     *
     * A title is only accepted when the text after the code looks like a title
     * rather than a unit count, a contact-hour total or a stray table value,
     * and the longest plausible reading wins when a code appears more than
     * once. That keeps `COS 101 3` from becoming a course called "3".
     *
     * @return array<string,string> code => title
     */
    private function courseCodes(string $section): array
    {
        $lines = [];
        foreach (explode("\n", $section) as $line) {
            $line = $this->cleanLine($line);
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        $courses = [];
        $count = count($lines);

        for ($i = 0; $i < $count; $i++) {
            if (! preg_match(self::CODE_LINE, $lines[$i], $m)) {
                continue;
            }

            $code = $m[1].$m[2];
            $title = $this->usableTitle($m[3] ?? '');

            if ($title === '') {
                // Title on the following line(s), as the flattened tables have it.
                for ($j = $i + 1; $j < min($count, $i + 3); $j++) {
                    if (preg_match(self::CODE_LINE, $lines[$j])) {
                        break;
                    }
                    $title = $this->usableTitle($lines[$j]);
                    if ($title !== '') {
                        break;
                    }
                }
            }

            if (isset($courses[$code])) {
                if (mb_strlen($title) > mb_strlen($courses[$code])) {
                    $courses[$code] = $title;
                }

                continue;
            }

            $courses[$code] = $title;
        }

        ksort($courses);

        return $courses;
    }

    /**
     * Accept a title only when it plausibly is one.
     *
     * The flattened tables put contact hours, unit counts and status letters on
     * the same visual row as the title, so most candidates are rejected. A
     * title starts with a letter, carries no unit/hour language, and is not
     * itself a course code.
     */
    private function usableTitle(string $candidate): string
    {
        $candidate = trim(preg_replace('/\s+/u', ' ', $candidate) ?? '');
        if ($candidate === '' || mb_strlen($candidate) > 160) {
            return '';
        }

        if (preg_match(self::CODE_LINE, $candidate)) {
            return '';
        }

        if (! preg_match('/^[\p{Lu}\p{Ll}]/u', $candidate)) {
            return '';
        }

        if (preg_match('/\b(units?|semester|level|pre-?requisite|core|elective|optional|compulsory)\b/i', $candidate)) {
            return '';
        }

        // Reject a row of bare numbers ("3 45 0 0 45") and stray status letters.
        if (preg_match('/^[\d\s,.\-+]+$/', $candidate)) {
            return '';
        }

        return rtrim($candidate, " .:-");
    }

    /**
     * The leading award abbreviations, in the canonical spelling, used when a
     * title starts with more than one.
     */
    private const DEGREE_TYPES_NORMALISED = 'B\.?\s?Sc\.?|B\.?\s?A\.?|B\.?\s?Eng\.?|B\.?\s?Tech\.?'
        .'|B\.?\s?Ed\.?|LL\.?\s?B|LL\.?\s?M|MBA|M\.?\s?Sc\.?|M\.?\s?A\.?|Ph\.?\s?D';
}
