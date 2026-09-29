<?php

namespace App\Services;

use App\Models\Curriculum\Programme;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Builds and maintains the central programme catalogue.
 *
 * What "central" means here
 * -------------------------
 * A programme of study in Nigeria is a national thing. "B.Sc. Computer Science"
 * is the same degree everywhere, offered by many universities, and its
 * curriculum is set nationally, not invented per university. So a programme is
 * NOT a child of an organization: it is a row in one national catalogue, and an
 * organization *offers* programmes from it.
 *
 * This matters because the alternative is what the data was actually doing.
 * Every organization was free to type its own programme rows, which produced
 * near-duplicates that differ only in punctuation — "B.Sc. Cyber Security" and
 * "Cyber Security" and "B.Sc. Cybersecurity" all meaning the same degree. A
 * student then had to pick a programme that matched how their own university
 * happened to spell it, and a course could not be shared between two
 * organizations that disagreed on the spelling.
 *
 * So the catalogue needs a stable key that does not depend on spelling, and
 * every offering must resolve to exactly one catalogue row.
 */
class ProgrammeCatalogue
{
    /**
     * The normalisation used to decide whether two programme names are the
     * same degree.
     *
     * Deliberately aggressive: case, punctuation and the degree abbreviation
     * are all noise. "B.Sc. Computer Science", "BSC COMPUTER SCIENCE" and
     * "Computer Science" are one programme, and treating them as three is
     * precisely the duplication this catalogue exists to remove.
     */
    public static function normalize(string $name): string
    {
        $name = Str::lower(trim($name));

        // Strip a leading degree abbreviation, so the abbreviation does not
        // become part of the identity: "B.Sc. Computer Science" and
        // "Computer Science" must collide, or they would never be seen as one.
        $name = preg_replace(
            '/\b(b\.?s\.?c|b\.?sc|b\.?eng|b\.?ed|b\.?a\.?gric|b\.?a|b\.?pharm|b\.?nsc|b\.?mls|'
            .'m\.?b\.?b\.?s|ll\.?b|d\.?v\.?m|m\.?sc|ph\.?d|m\.?a|pgd|n\.?d|n\.?i\.?d)\b\.?/u',
            '',
            $name
        );

        // Remaining punctuation is noise too.
        $name = preg_replace('/[^a-z0-9]+/u', ' ', $name);

        return trim(preg_replace('/\s+/', ' ', $name));
    }

    /**
     * Derives a stable catalogue code for a programme name.
     *
     * NUC codes a programme by its discipline, so the code is the normalised
     * name abbreviated to a short uppercase token. Deterministic, so the same
     * programme always produces the same code and two runs cannot disagree.
     */
    public static function deriveCode(string $name): string
    {
        $normalized = self::normalize($name);

        if ($normalized === '') {
            return 'PRG-' . strtoupper(Str::random(6));
        }

        $words = explode(' ', $normalized);
        $code = Str::upper(Str::substr($words[0], 0, 3));

        foreach (array_slice($words, 1, 3) as $word) {
            $code .= Str::upper(Str::substr($word, 0, 2));
        }

        return $code;
    }

    /**
     * Finds the catalogue row matching a name, if one exists.
     */
    public static function find(string $name): ?Programme
    {
        return Programme::query()
            ->where('normalized_name', self::normalize($name))
            ->first();
    }

    /**
     * Gives every catalogue entry a unique code.
     *
     * `code` is what an organization's offering will reference, and what a
     * student will select, so it has to be populated and unique before the
     * catalogue can be used for anything. Duplicates are disambiguated with a
     * numeric suffix rather than by overwriting, because two programmes that
     * normalized to the same string are genuinely different rows that the
     * discipline disambiguates — silently merging them would lose one.
     */
    public static function backfillCodes(): array
    {
        $assigned = [];
        $collisions = [];

        $programmes = Programme::query()
            ->with('nucDiscipline')
            ->orderBy('id')
            ->get();

        // Codes already taken, so a generated code never steals an existing one.
        $used = Programme::query()
            ->whereNotNull('code')
            ->pluck('code')
            ->flip();

        foreach ($programmes as $programme) {
            $base = self::deriveCode($programme->name);
            $code = $base;
            $suffix = 2;

            while (isset($used[$code])) {
                $code = $base . '-' . $suffix;
                $suffix++;
            }

            $used[$code] = true;

            if ($programme->code !== $code) {
                $programme->code = $code;
                $programme->save();
            }

            $assigned[$programme->id] = $code;

            if (str_contains($code, '-')) {
                $collisions[] = "{$programme->name} -> {$code}";
            }
        }

        return ['assigned' => $assigned, 'collisions' => $collisions];
    }

    /**
     * Reports how well the catalogue would absorb a set of local programme
     * names, without changing anything. Used to decide what an organization
     * should link to rather than define.
     */
    public static function coverageReport(array $names): array
    {
        $catalogue = Programme::query()
            ->pluck('id', 'normalized_name')
            ->all();

        $matched = [];
        $unmatched = [];

        foreach ($names as $name) {
            $key = self::normalize($name);

            if (isset($catalogue[$key])) {
                $matched[$name] = $catalogue[$key];
            } else {
                $unmatched[] = $name;
            }
        }

        return ['matched' => $matched, 'unmatched' => $unmatched];
    }
}
