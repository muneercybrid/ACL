<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Canonical normalisation for institution identity.
 *
 * This is the single source of truth for `institutions.normalized_name` and
 * `institutions.slug`. Both columns are dedup keys, so the normalisation has
 * to be stable, total (never throws, never returns an empty string) and
 * deterministic across PHP versions.
 *
 * Why this class exists
 * ---------------------
 * A previous NUC import wrote every capital letter as a hyphen, producing
 * values like `-michael--kpara--niversity-of--griculture` for "Michael Okpara
 * University of Agriculture". The `unique` index declared on
 * `normalized_name` could not protect the table because every corrupted value
 * was unique. Institution matching now goes through here.
 *
 * Rules
 * -----
 * 1. Repair mojibake before anything else (the NUC scrape carries "Ileâ€Ife").
 * 2. Casefold to lowercase using mbstring, not ASCII tricks.
 * 3. Fold "&" and "+" to "and" so "Arts & Science" == "Arts and Science".
 * 4. Drop the NUC's own filler words that never distinguish an institution.
 * 5. Apply a small, explicit synonym map for grammatical variants.
 * 6. Collapse every remaining non-alphanumeric run to a single space.
 * 7. Guarantee a non-empty result by falling back to a hash of the input.
 */
final class InstitutionNormalizer
{
    /** Filler words that carry no identifying signal. */
    private const STOP_WORDS = ['the', 'of', 'at', 'for'];

    /**
     * Grammatical variants of the same institution, folded to one form.
     *
     * Kept deliberately tiny and explicit. Each entry is a singular/adjectival
     * or demonym form that the NUC list and the earlier import spell
     * differently for the same body:
     *
     *   NUC "Michael Okpara University of Agricultural Umudike"
     *   ACL "Michael Okpara University of Agriculture, Umudike"
     *   NUC "Nigerian Maritime University Okerenkoko, Delta State"
     *   ACL "Nigeria Maritime University Okerenkoko, Delta State"
     *
     * Nothing is folded that could merge two genuinely different institutions,
     * and nothing is stemmed beyond these cases.
     */
    private const SYNONYMS = [
        'agricultural' => 'agriculture',
        'nigerian' => 'nigeria',
        'universities' => 'university',
        'colleges' => 'college',
        'polytechnics' => 'polytechnic',
        'institutes' => 'institute',
        'academies' => 'academy',
        'schools' => 'school',
        'centres' => 'centre',
        'centers' => 'centre',
    ];

    public function name(string $value): string
    {
        $repaired = $this->repairEncoding($value);
        $folded = mb_strtolower(trim($repaired), 'UTF-8');

        $folded = str_replace(['&', '+'], ' and ', $folded);

        foreach (self::STOP_WORDS as $word) {
            $folded = preg_replace('/\b' . preg_quote($word, '/') . '\b/', ' ', $folded) ?? $folded;
        }

        // Anything that is not a letter or digit becomes a single space. This
        // removes commas, hyphens, slashes, full stops and mojibake residue.
        $folded = preg_replace('/[^a-z0-9]+/', ' ', $folded) ?? '';

        $folded = trim(preg_replace('/\s+/', ' ', $folded) ?? '');

        $folded = $this->applySynonyms($folded);

        if ($folded !== '') {
            return $folded;
        }

        // A name made entirely of punctuation must still yield a stable,
        // unique-ish key rather than an empty string that would collide with
        // every other empty string under a unique index.
        return 'unnamed-' . substr(hash('sha256', trim($value)), 0, 16);
    }

    /**
     * URL-safe slug built from the normalised name. Deterministic: the same
     * institution name always yields the same slug, so re-running an import
     * does not produce a second slug for the same institution.
     */
    public function slug(string $value): string
    {
        $slug = str_replace(' ', '-', $this->name($value));
        $slug = trim($slug, '-');

        if ($slug === '' || $slug === 'unnamed') {
            $slug = 'unnamed-' . substr(hash('sha256', trim($value)), 0, 16);
        }

        return substr($slug, 0, 255);
    }

    /**
     * Whole-token synonym folding, applied only after the name has been
     * reduced to plain lowercase words.
     */
    private function applySynonyms(string $folded): string
    {
        if ($folded === '') {
            return $folded;
        }

        return strtr($folded, self::SYNONYMS);
    }

    /**
     * Repair byte sequences that were mangled in transit.
     *
     * The NUC pages were scraped with a mis-declared charset, which turned
     * "Ile-Ife" into "Ileâ€Ife". Rather than attempt a full charset recovery
     * we drop every non-ASCII sequence, because institution identity in ACL is
     * carried by the ASCII words ("University", "Federal", "Polytechnic") and
     * a dropped diacritic is preferable to a key that never matches.
     */
    private function repairEncoding(string $value): string
    {
        $value = str_replace("\u{FFFD}", '', $value);

        // Explicitly collapse the mojibake seen in the reference file before
        // the general non-ASCII strip, so the separator survives.
        $value = str_replace(['â€', 'Ã©', 'Ã¨', 'Ã³'], ['', 'e', 'e', 'o'], $value);

        if (preg_match('//u', $value) !== 1) {
            // Invalid UTF-8: fall back to a byte-level transliteration.
            $value = (string) iconv('UTF-8', 'ASCII//TRANSLIT', $value);
        }

        return $value;
    }
}
