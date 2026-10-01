<?php

namespace App\Services\Courses;

/**
 * Reads the raw NUC CCMAS documents into a per-course content index.
 *
 * The seventeen discipline documents already carry everything chapter
 * generation needs: a course title, its units, a numbered Learning Outcomes
 * list and a Course Contents paragraph. None of that was ever imported --
 * ccmas_courses stores only code, title, units, level and semester -- so the
 * generator had nothing to work from and wrote chapters from the title alone.
 *
 * Two things made the existing extraction miss almost everything:
 *
 * - The documents write codes with a space ("MTH 101"); the database stores
 *   them without ("MTH101"). A literal search found nothing.
 * - A code appears many times in a document, most often inside a curriculum
 *   table of contents. The entry that carries content is the one shaped
 *   "MTH 101: Elementary Mathematics I" followed by Learning Outcomes.
 *
 * A shared course appears in many documents: MTH 101 is listed in eleven. The
 * texts are equivalent, not identical -- one discipline writes "understand
 * basic definition of set", another "explain", another "identify", and the
 * stem alternates between "will be able to" and "should be able to" -- so the
 * variants are consolidated onto one canonical entry per code. That is what
 * makes central generation sound: every programme running MTH 101 reads the
 * same chapter list.
 */
class CcmasContentIndex
{
    /**
     * @var array<string, array<string, string>>
     */
    protected array $cache = [];

    /**
     * Parses every discipline document once and returns code => entry.
     *
     * @return array<string, array{title: string, units: string, learning_outcomes: string, course_contents: string, variants: int, documents: array<int, string>}>
     */
    public function all(): array
    {
        if ($this->cache !== []) {
            return $this->cache;
        }

        $byCode = [];

        foreach ($this->documentPaths() as $path) {
            $text = @file_get_contents($path);

            if ($text === false) {
                continue;
            }

            $discipline = pathinfo($path, PATHINFO_FILENAME);

            foreach ($this->parseEntries($text) as $code => $entry) {
                // First document to supply a given code wins, which is a stable
                // rule rather than an arbitrary one: the disciplines are walked
                // in a fixed order, so the same code always resolves to the same
                // canonical entry. Later variants are counted, not discarded
                // silently.
                if (! isset($byCode[$code])) {
                    $byCode[$code] = $entry + ['variants' => 1, 'documents' => [$discipline]];

                    continue;
                }

                $byCode[$code]['variants']++;

                if (! in_array($discipline, $byCode[$code]['documents'], true)) {
                    $byCode[$code]['documents'][] = $discipline;
                }

                // Prefer whichever variant carries the most material, so the
                // canonical entry is the fullest statement available.
                if ($this->weight($entry) > $this->weight($byCode[$code])) {
                    $documents = $byCode[$code]['documents'];
                    $byCode[$code] = $entry + ['variants' => $byCode[$code]['variants'], 'documents' => $documents];
                }
            }
        }

        return $this->cache = $byCode;
    }

    public function forCode(string $code): ?array
    {
        $code = $this->normalizeCode($code);

        return $this->all()[$code] ?? null;
    }

    public function count(): int
    {
        return count($this->all());
    }

    /**
     * @return array<int, string>
     */
    protected function documentPaths(): array
    {
        $paths = glob(base_path('storage/app/nuc-ccmas/*.txt')) ?: [];
        sort($paths);

        return $paths;
    }

    /**
     * Finds every "CODE: Title ... Learning Outcomes ... Course Contents" block.
     *
     * @return array<string, array<string, string>>
     */
    protected function parseEntries(string $text): array
    {
        $text = $this->normalise($text);

        $pattern = '/^([A-Z]{2,8}\s?\d{3}):\s*(.+?)\n(.*?)(?=^[A-Z]{2,8}\s?\d{3}:|\z)/ms';

        $entries = [];

        if (! preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            return [];
        }

        foreach ($matches as $match) {
            $code = $this->normalizeCode($match[1]);
            $title = trim(preg_replace('/\s+/', ' ', $match[2]) ?? '');
            $body = $match[3];

            $outcomes = $this->section($body, 'Learning Outcomes', ['Course Contents', 'Course Content']);
            $contents = $this->section($body, 'Course Contents', ['Learning Outcomes']);

            if ($outcomes === null || $contents === null) {
                // A table-of-contents hit or a bare title with no substance.
                continue;
            }

            // A section that still contains its own label, or another
            // course's code, means the boundaries failed for this extraction.
            // Rejecting it matters more than it looks: the consolidation rule
            // keeps the fullest variant, so a run-on parse would otherwise
            // win on length and become the canonical entry.
            foreach (['Learning Outcomes' => $outcomes, 'Course Contents' => $contents] as $label2 => $text) {
                if (preg_match('/(?:^|\n)\s*' . preg_quote($label2, '/') . '\s*\n/i', $text)
                    || preg_match('/(?:^|\n)[A-Z]{2,8}\s?\d{3}\s*:/', $text)) {
                    $outcomes = null;
                    break;
                }
            }

            if ($outcomes === null || $contents === null) {
                continue;
            }

            $units = $this->units($body);

            $entries[$code] = [
                'code' => $code,
                'title' => $title,
                'units' => $units,
                'learning_outcomes' => $outcomes,
                'course_contents' => $contents,
            ];
        }

        return $entries;
    }

    /**
     * Returns one labelled section, stopping at the next known label.
     */
    protected function section(string $body, string $label, array $stops): ?string
    {
        // Stop at the next labelled section OR at the next course code. Some
        // extractions run straight from one course's contents into the
        // following course's outcomes with no label in between, and without
        // the code boundary a single entry swallowed the rest of the document.
        $pattern = '/(?:^|\n)\s*' . preg_quote($label, '/') . '\s*\n?(.*?)(?=\n\s*(?:'
            . implode('|', array_map(fn ($s) => preg_quote($s, '/'), $stops))
            . ')\s*\n|\n[A-Z]{2,4}\s?\d{3}\s*:|\z)/is';

        if (! preg_match($pattern, $body, $m)) {
            return null;
        }

        $text = trim(preg_replace('/[ \t]+/', ' ', $m[1]) ?? '');

        // Drop the page furniture the extraction carries through: the
        // discipline name, running heads and bare page numbers. Left in, they
        // read as subject matter to the model.
        $text = preg_replace('/^(?:[A-Z][a-z]+(?: [A-Z][a-z]+)*|\d+|New)\s*$/m', '', $text) ?? $text;
        $text = trim(preg_replace('/\n{2,}/', "\n", $text) ?? $text);

        return $text === '' ? null : $text;
    }

    protected function units(string $body): string
    {
        if (preg_match('/\(\s*([0-9]+\s*Units?[^)]{0,60})\)/i', $body, $m)) {
            return trim($m[1]);
        }

        return '';
    }

    protected function normalizeCode(string $code): string
    {
        return strtoupper(str_replace(' ', '', trim($code)));
    }

    /**
     * Line endings and non-breaking spaces vary between the extractions; a
     * section that does not start cleanly on its own line is missed entirely.
     */
    protected function normalise(string $text): string
    {
        // Form feeds matter as much as carriage returns. A PDF page break
        // arrives as \f, which renders like a line break but is not \n, so a
        // section boundary written to match \n sailed straight through it and
        // one course's contents ran into the next course's header.
        return str_replace(
            ["\r\n", "\r", "\f", "\x0b", "\xc2\xa0"],
            ["\n", "\n", "\n", "\n", ' '],
            $text
        );
    }

    protected function weight(array $entry): int
    {
        return strlen($entry['learning_outcomes'] ?? '') + strlen($entry['course_contents'] ?? '');
    }
}