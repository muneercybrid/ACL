<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Reads the NUC reference list and turns it into structured institution rows.
 *
 * Source of truth: docs/reference/nigerian-tertiary-institutions.md, a
 * point-in-time capture of the NUC's published lists (fetched 2026-09-05) for
 * federal, state and private universities, transnational education
 * institutions, distance learning centres and approved affiliations.
 *
 * The file is treated strictly as data. It is a Markdown table captured from a
 * public web page; nothing in it is executed, and a cell is never allowed to
 * become a value that is later interpolated into SQL.
 *
 * Two properties of the source are deliberately preserved rather than hidden:
 *   - The list counts published rows, not distinct bodies. Approved Affiliations
 *     repeats some colleges under more than one base university, so the same
 *     college can legitimately appear twice.
 *   - Names carry the source's own spelling and encoding artefacts
 *     ("Fountain Unveristy", "Ileâ€Ife"). Normalisation happens downstream in
 *     InstitutionNormalizer, never by rewriting the stored name.
 */
final class NucInstitutionSource
{
    public const SECTION_FEDERAL = 'Federal Universities';
    public const SECTION_STATE = 'State Universities';
    public const SECTION_PRIVATE = 'Private Universities';
    public const SECTION_TRANSNATIONAL = 'Transnational Education Institutions';
    public const SECTION_DISTANCE = 'Distance Learning Centres';
    public const SECTION_AFFILIATIONS = 'Approved Affiliations';

    /** Section => [ownership (enum), type]. */
    private const CATEGORIES = [
        self::SECTION_FEDERAL => ['Federal', 'Federal University'],
        self::SECTION_STATE => ['State', 'State University'],
        self::SECTION_PRIVATE => ['Private', 'Private University'],
        self::SECTION_TRANSNATIONAL => ['Private', 'Transnational Institution'],
        self::SECTION_DISTANCE => ['Private', 'Distance Learning Centre'],
        self::SECTION_AFFILIATIONS => ['Private', 'Affiliated Centre'],
    ];

    /** Em dash marks a field the NUC left blank. */
    private const BLANK = '—';

    private string $path;

    public function __construct(?string $path = null)
    {
        // A default keeps the class resolvable by the container, which cannot
        // supply a bare string. Tests pass an explicit path to use a fixture.
        $this->path = $path ?? dirname(__DIR__, 2) . '/docs/reference/nigerian-tertiary-institutions.md';
    }

    public static function fromDefaultPath(): self
    {
        return new self(dirname(__DIR__, 2) . '/docs/reference/nigerian-tertiary-institutions.md');
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return list<array{
     *   name: string, section: string, ownership: string, type: string,
     *   website: ?string, est: ?int, state: ?string, affiliationOf: ?string
     * }>
     */
    public function all(): array
    {
        if (! is_readable($this->path)) {
            throw new RuntimeException("NUC reference list not readable at {$this->path}");
        }

        $lines = file($this->path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new RuntimeException("Could not read NUC reference list at {$this->path}");
        }

        $entries = [];
        $section = null;
        /** @var array<string,int> header name => cell index, for the current table */
        $columns = [];

        foreach ($lines as $line) {
            if (preg_match('/^##\s+(.+?)\s*\(/', $line, $m) === 1) {
                $section = $m[1];
                $columns = [];

                continue;
            }

            if ($section === null || ! isset(self::CATEGORIES[$section])) {
                continue;
            }

            $cells = $this->cells($line);

            if ($cells === null) {
                continue;
            }

            // A header row names the columns. The register does not use one
            // shape throughout: federal/state/private list Vice-Chancellor,
            // Website and Est.; transnational lists Model and Est.; distance
            // learning and affiliations each list a base institution and the
            // body that sits under it. Reading cells by position instead of by
            // name silently put "2025" in the website field and named base
            // universities as if they were distance learning centres.
            if ($this->isHeader($cells)) {
                $columns = $this->columnMap($cells);

                continue;
            }

            // Data rows are numbered; anything else in the table is neither a
            // header nor a record.
            if (! ctype_digit($cells[0] ?? '')) {
                continue;
            }

            if ($columns === [] || ! isset($columns['name'])) {
                continue;
            }

            $name = $this->cell($cells[$columns['name']] ?? null);

            if ($name === null) {
                continue;
            }

            $base = isset($columns['base'])
                ? $this->cell($cells[$columns['base']] ?? null)
                : null;

            // Distance learning centres are listed under a generic label —
            // "Distance Learning Centre", "Centre for Distance Learning" — and
            // the base university is the only thing that distinguishes one from
            // another. Left unqualified, 34 rows would collapse onto a handful
            // of names and collide on the unique normalized_name index, so the
            // centre is named together with the university it serves.
            if ($base !== null && $section === self::SECTION_DISTANCE) {
                $name = $name . ', ' . $base;
            }

            [$ownership, $type] = self::CATEGORIES[$section];

            $entries[] = [
                'name' => $name,
                'section' => $section,
                'ownership' => $ownership,
                'type' => $type,
                'website' => isset($columns['website'])
                    ? $this->cell($cells[$columns['website']] ?? null)
                    : null,
                'est' => isset($columns['est'])
                    ? $this->year($cells[$columns['est']] ?? null)
                    : null,
                'state' => $this->stateFrom($name),
                // Affiliations and distance learning both name the body the
                // centre belongs to, in a column other sections do not have.
                'affiliationOf' => $base,
            ];
        }

        return $entries;
    }

    /**
     * Split a table row into trimmed cells, or null when the line is not a
     * data row.
     *
     * @return list<string>|null
     */
    private function cells(string $line): ?array
    {
        if (str_starts_with(trim($line), '|') === false) {
            return null;
        }

        $parts = array_map('trim', explode('|', trim($line)));

        // Leading and trailing pipes produce empty edge cells.
        if ($parts !== [] && $parts[0] === '') {
            array_shift($parts);
        }
        if ($parts !== [] && end($parts) === '') {
            array_pop($parts);
        }

        return array_values($parts);
    }

    /**
     * A header row is the one whose first cell is the column marker, e.g.
     * "| # | Institution | Website | Est. |". Data rows are numbered, so this
     * distinguishes the two without depending on a particular column set.
     *
     * @param  list<string>  $cells
     */
    private function isHeader(array $cells): bool
    {
        if (($cells[0] ?? '') === '#') {
            return true;
        }

        // Tolerate a header written without the marker column.
        foreach (array_slice($cells, 0, 2) as $label) {
            if (in_array(mb_strtolower(trim($label)), [
                'institution', 'base institution', 'affiliated institution', 'odl centre',
            ], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Map each header label onto a field, by name rather than position.
     *
     * @param  list<string>  $cells
     * @return array<string,int>
     */
    private function columnMap(array $cells): array
    {
        $map = [];

        foreach ($cells as $index => $label) {
            $label = mb_strtolower(trim($label));

            $field = match ($label) {
                // The body itself. Distance learning lists the centre here and
                // the university it belongs to in 'base'.
                'institution', 'affiliated institution', 'odl centre' => 'name',
                'website' => 'website',
                'est.', 'est' => 'est',
                'base (affiliating) university', 'base institution' => 'base',
                default => null,
            };

            if ($field !== null) {
                $map[$field] = $index;
            }
        }

        return $map;
    }

    private function cell(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return ($value === '' || $value === self::BLANK) ? null : $value;
    }

    private function year(?string $value): ?int
    {
        $value = $this->cell($value);

        return ($value !== null && ctype_digit($value)) ? (int) $value : null;
    }

    /**
     * Best-effort state for an institution whose name carries it, e.g.
     * "Abubakar Tafawa Balewa University, Bauchi" is not a state, but
     * "Federal University, Dutse, Jigawa State" is.
     *
     * Deliberately conservative: a wrong state is worse than a null one, and
     * the NigeriaStatesLgasSeeder is the proper authority for state names.
     */
    private function stateFrom(string $name): ?string
    {
        if (preg_match('/,\s*([A-Z][A-Za-z ]*?)\s+State\s*$/u', $name, $m) === 1) {
            return trim($m[1]);
        }

        return null;
    }
}
