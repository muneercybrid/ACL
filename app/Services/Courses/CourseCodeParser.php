<?php

namespace App\Services\Courses;

use Illuminate\Support\Facades\DB;

/**
 * Derives a course's academic level and semester from its code.
 *
 * Nigerian universities number courses by level and semester within a single
 * three-digit block, and the convention is documented rather than arbitrary --
 * the National Open University of Nigeria states it in its benchmark policy on
 * credit units per semester:
 *
 *   "The courses are arranged in progressive order of difficulty or in levels of
 *    academic progress, e.g. Level or year 1 courses are 100, 101 etc. and
 *    Level II or Year II courses are 200, 202 etc. The National Open University
 *    of Nigeria also has a policy of odd number representing first semester and
 *    even number representing second semester."
 *
 * So COS 101 is level 100 (first digit 1), first semester (odd trailing digit);
 * COS 102 is level 100, second semester; CYB 403 is level 400 (first digit 4),
 * first semester.
 *
 * Two honest limits. First, the level is read from the leading digit, but the
 * source data disagrees with itself: of 9,040 CCMAS rows, 502 carry a level that
 * contradicts their own code (OIM 401 stored as level 300, for instance). The
 * code is the stronger signal, and the stored level is left untouched here.
 * Second, parity is a convention, not a guarantee -- a university may sit a
 * course in a semester its code would not imply. That is why the parser also
 * returns what the parity rule predicted, so callers can compare it against an
 * authoritative document such as a course registration form and record a
 * deliberate override rather than silently overwriting one.
 */
class CourseCodeParser
{
    /** First semester. */
    public const FIRST_SEMESTER = 1;

    /** Second semester. */
    public const SECOND_SEMESTER = 2;

    /**
     * Split a course code into its letter and digit parts.
     *
     * Handles the shapes seen in practice and in the CCMAS corpus: "COS 101",
     * "COS101", "NUK-CYB 101", "MTH 101". Separators are ignored entirely,
     * because the same course is written both ways in real documents.
     *
     * @return array{prefix: string, digits: string}|null
     */
    public function split(string $code): ?array
    {
        $letters = strtoupper(preg_replace('/[^A-Za-z]/', '', $code) ?? '');
        $digits = preg_replace('/[^0-9]/', '', $code) ?? '';

        if ($letters === '' || $digits === '') {
            return null;
        }

        return ['prefix' => $letters, 'digits' => $digits];
    }

    /**
     * Normalize a course code for comparison and deduplication.
     *
     * CCMAS stores "GST 111"; a course registration form writes "GST111". These
     * are the same course, and treating them as different is how a shared course
     * ends up duplicated once per programme with the two copies drifting apart.
     */
    public function normalize(string $code): ?string
    {
        $split = $this->split($code);

        if ($split === null) {
            return null;
        }

        return $split['prefix'].$split['digits'];
    }

    /**
     * Derive level and semester from a course code.
     *
     * @return array{level: int, semester: int, parity_predicted: int, prefix: string, digits: string}|null
     */
    public function parse(string $code): ?array
    {
        $split = $this->split($code);

        if ($split === null || strlen($split['digits']) < 3) {
            return null;
        }

        $digits = $split['digits'];

        // Leading digit carries the level: 1 => 100, 2 => 200 ... 6 => 600.
        $level = ((int) $digits[0]) * 100;

        // Trailing digit carries the semester by parity: odd first, even second.
        $trailing = (int) substr($digits, -1);
        $semester = ($trailing % 2 === 1)
            ? self::FIRST_SEMESTER
            : self::SECOND_SEMESTER;

        return [
            'level' => $level,
            'semester' => $semester,
            'parity_predicted' => $semester,
            'prefix' => $split['prefix'],
            'digits' => $digits,
        ];
    }

    /**
     * Semester implied by a course code, or null when the code cannot be parsed.
     */
    public function semester(string $code): ?int
    {
        return $this->parse($code)['semester'] ?? null;
    }

    /**
     * Level implied by a course code, or null when the code cannot be parsed.
     */
    public function level(string $code): ?int
    {
        return $this->parse($code)['level'] ?? null;
    }

    /**
     * Candidate courses for a programme/level dropdown, drawn from the central
     * catalogue and de-duplicated by normalized code.
     *
     * The CCMAS corpus stores one row per programme, so the same course appears
     * up to 177 times ("GST 111"). A dropdown listing those raw rows would be
     * unusable and would let a coordinator add the same course twice under two
     * spellings, which is how semesters get mixed. This collapses to one entry
     * per normalized code, preferring the row whose own code and level agree.
     *
     * @param  int|null  $programme  programme title to filter by, when known
     * @return array<int, array{code: string, title: string, credit_units: int|null, level: int|null, semester: int|null, source: string}>
     */
    public function catalogue(?string $programme = null, ?int $level = null, int $limit = 500): array
    {
        $query = DB::table('ccmas_courses')
            ->select('course_code', 'title', 'credit_units', 'level')
            ->whereNotNull('course_code')
            ->whereNotNull('title')
            ->orderBy('course_code');

        if ($programme !== null) {
            $query->where('programme_title', $programme);
        }

        if ($level !== null) {
            $query->where('level', $level);
        }

        $parser = $this;
        $out = [];

        foreach ($query->limit($limit * 4)->get() as $row) {
            $normalized = $this->normalize($row->course_code);

            if ($normalized === null) {
                continue;
            }

            $parsed = $this->parse($row->course_code);

            // Prefer the row whose stored level agrees with its own code, so a
            // mislabelled duplicate never becomes the canonical spelling.
            $agrees = $parsed !== null && $row->level !== null
                && $parsed['level'] === (int) $row->level;

            $existing = $out[$normalized] ?? null;

            if ($existing === null || ($agrees && ! $existing['level_agrees'])) {
                $out[$normalized] = [
                    'code' => $normalized,
                    'title' => trim($row->title),
                    'credit_units' => $row->credit_units !== null ? (int) $row->credit_units : null,
                    'level' => $row->level !== null ? (int) $row->level : null,
                    'semester' => $parsed['semester'] ?? null,
                    'source' => 'ccmas',
                    'level_agrees' => $agrees,
                ];
            }
        }

        return array_slice(array_values($out), 0, $limit);
    }

    /**
     * Whether a semester assignment came from an authoritative source rather than
     * from the parity convention.
     *
     * A course registration form outranks the convention: when a form places
     * MTH 103 in second semester, that placement stands even though its code
     * ends in 3. The parser's prediction is still returned so the divergence can
     * be reported rather than hidden.
     *
     * @return array{semester: int, source: string, parity_predicted: int|null, conflicts_with_parity: bool}
     */
    public function resolveSemester(string $code, ?int $documentedSemester = null): array
    {
        $parsed = $this->parse($code);
        $predicted = $parsed['semester'] ?? null;

        if ($documentedSemester === null) {
            return [
                'semester' => $predicted,
                'source' => $predicted === null ? 'undetermined' : 'code_parity',
                'parity_predicted' => $predicted,
                'conflicts_with_parity' => false,
            ];
        }

        return [
            'semester' => $documentedSemester,
            'source' => 'course_registration_form',
            'parity_predicted' => $predicted,
            'conflicts_with_parity' => $predicted !== null && $predicted !== $documentedSemester,
        ];
    }
}
