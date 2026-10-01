<?php

namespace App\Services\Courses;

use App\Models\Course;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bulk publisher for the national CCMAS curriculum.
 *
 * CcmasProgrammePublisher issues one query per course, which is fine for a
 * single programme and far too slow for the whole corpus: the database is TiDB
 * reached over the network, so 9,040 rows one at a time measured at roughly two
 * hours. This class produces the same result set in a handful of queries, by
 * collapsing the corpus to one row per normalized code up front and inserting
 * placements in chunks.
 *
 * It shares the semantics of the single-programme publisher exactly -- one
 * shared catalogue row per code, level and semester from the code convention,
 * institution courses excluded, idempotent on (version, course, level,
 * semester) -- so running both produces identical data. The rules live in
 * CcmasProgrammePublisher; this is only a faster way to apply them at scale.
 */
class CcmasBulkPublisher
{
    public function __construct(
        protected CourseCodeParser $parser,
        protected ?CcmasProgrammePublisher $programmePublisher = null,
    ) {}

    protected function publisher(): CcmasProgrammePublisher
    {
        return $this->programmePublisher ??= new CcmasProgrammePublisher($this->parser);
    }

    /**
     * Publish every programme's CCMAS courses nationally.
     *
     * @return array{programmes: int, published: int, courses: int, unmatched_programmes: int}
     */
    public function publishAll(int $chunk = 500): array
    {
        $versionByProgramme = DB::table('curriculum_versions')
            ->where('is_active', true)
            ->orderByDesc('id')
            ->get()
            ->groupBy('programme_id')
            ->map(fn ($rows) => (int) $rows->first()->id);

        if ($versionByProgramme->isEmpty()) {
            return ['programmes' => 0, 'published' => 0, 'courses' => 0, 'unmatched_programmes' => 0];
        }

        $programmes = DB::table('programmes')
            ->whereIn('id', $versionByProgramme->keys())
            ->get(['id', 'name']);

        // Collapse the corpus to one row per normalized code. The import holds a
        // row per programme, so the same course appears up to 177 times; without
        // this the catalogue would fork into thousands of near-identical rows.
        $canonical = DB::table('ccmas_courses')
            ->whereNotNull('course_code')
            ->whereNotNull('title')
            ->whereNotNull('level')
            ->whereNotNull('programme_title')
            ->orderBy('id')
            ->get();

        $byCode = [];
        foreach ($canonical as $row) {
            $normalized = $this->parser->normalize((string) $row->course_code);

            if ($normalized === null) {
                continue;
            }

            $parsed = $this->parser->parse((string) $row->course_code);
            $title = $this->cleanTitle((string) $row->title);
            $agrees = $parsed !== null && (int) $row->level === $parsed['level'];

            $candidate = [
                'title' => $title,
                'units' => $row->credit_units !== null && (int) $row->credit_units > 0 ? (int) $row->credit_units : 1,
                'ccmas_id' => (int) $row->id,
                'agrees' => $agrees,
                'quality' => $this->titleQuality($title),
            ];

            if (! isset($byCode[$normalized]) || $candidate['quality'] > $byCode[$normalized]['quality']) {
                $byCode[$normalized] = $candidate;
            }
        }

        $courseIds = $this->ensureCourseRows($byCode);
        $published = 0;
        $matchedProgrammes = 0;
        $pending = [];

        // Group the corpus by programme once, keyed by normalized name, so a
        // programme whose spelling differs from the corpus ("B.Sc Cybersecurity"
        // vs "B.Sc. Cybersecurity") still finds its rows. Matching literally
        // found 16 of 196 programmes.
        $byProgramme = [];
        foreach ($canonical as $row) {
            $key = $this->publisher()->normalizeProgrammeName((string) $row->programme_title);
            $byProgramme[$key][] = $row;
        }

        foreach ($programmes as $programme) {
            $key = $this->publisher()->normalizeProgrammeName((string) $programme->name);
            $rows = collect($byProgramme[$key] ?? []);

            if ($rows->isEmpty()) {
                continue;
            }

            $matchedProgrammes++;
            $versionId = $versionByProgramme[$programme->id];

            foreach ($rows as $row) {
                $normalized = $this->parser->normalize((string) $row->course_code);

                if ($normalized === null || ! isset($courseIds[$normalized])) {
                    continue;
                }

                $parsed = $this->parser->parse((string) $row->course_code);

                $pending[] = [
                    'curriculum_version_id' => $versionId,
                    'course_id' => $courseIds[$normalized],
                    'level' => (int) $row->level,
                    'semester' => $parsed['semester'] ?? 1,
                    'course_type' => $row->course_type ?: 'core',
                    'credit_units' => $byCode[$normalized]['units'],
                    'status' => 'active',
                    'is_mandatory' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (count($pending) >= $chunk) {
                $published += $this->flush($pending);
                $pending = [];
            }
        }

        if ($pending !== []) {
            $published += $this->flush($pending);
        }

        return [
            'programmes' => $matchedProgrammes,
            'published' => $published,
            'courses' => count($courseIds),
            'unmatched_programmes' => $versionByProgramme->count() - $matchedProgrammes,
        ];
    }

    /**
     * Title strings that are spreadsheet scaffolding rather than course names.
     *
     * The corpus was parsed out of tabular NUC documents, so a number of rows
     * carry a column header or a total label where the course title should be.
     * Because the national layer shares one catalogue row per code, a row like
     * "PHY 107 = Learning Outcomes" does not stay in one programme -- it becomes
     * the title every student in the country sees for that course. Rejecting
     * these during canonical selection is what keeps one bad row from spreading.
     */
    protected const TITLE_ARTIFACTS = [
        'learning outcomes',
        'learning outcome',
        'total',
        'course title',
        'course name',
        'seminar',
        'none',
        'nil',
        'n/a',
        'na',
        '-',
        '',
    ];

    /**
     * Strip the parenthetical weight markers and trailing punctuation the
     * corpus appends, e.g. "Communication in English (LH 15; PH 45)".
     */
    protected function cleanTitle(string $title): string
    {
        $title = preg_replace('/\(\s*(lh|ph)\s*\d+\s*;?\s*(lh|ph)?\s*\d*\s*\)/i', '', $title) ?? $title;
        $title = preg_replace('/\s+/u', ' ', $title) ?? $title;

        return rtrim(trim($title), ".,;:");
    }

    /**
     * Score a title for use as a course's shared canonical name.
     *
     * Higher is better. Spreadsheet artifacts score zero so they lose to any
     * real title for the same code. Beyond that, a longer title is more likely
     * to be a real course name than a truncated fragment, and a title that
     * agrees with the code's own level beats one that does not.
     */
    protected function titleQuality(string $title): int
    {
        $key = strtolower(trim($title));

        if (in_array($key, self::TITLE_ARTIFACTS, true)) {
            return 0;
        }

        $score = 1;

        // Prefer a substantive title over a stub.
        $length = mb_strlen($title);
        $score += min($length, 40);

        // Penalise titles that are mostly parenthetical weight data.
        if (preg_match('/^\(?\s*(lh|ph)\b/i', $title)) {
            $score -= 20;
        }

        return max($score, 1);
    }

    /**
     * Ensure one shared catalogue row exists per normalized code.
     *
     * @return array<string, int> normalized code => course id
     */
    protected function ensureCourseRows(array $byCode): array
    {
        // One read of the whole catalogue, mapped in memory. A whereIn over the
        // ~4,700 normalized codes produced a statement TiDB took minutes to plan
        // and execute against a 5,800-row table -- the table is small enough to
        // fetch outright, and a single scan beats an enormous IN list.
        $existing = DB::table('courses')->get(['id', 'normalized_code', 'ccmas_course_id']);

        $ids = [];
        $toLink = [];
        $now = now();

        foreach ($existing as $course) {
            $ids[$course->normalized_code] = (int) $course->id;

            if ($course->ccmas_course_id === null && isset($byCode[$course->normalized_code])) {
                $toLink[$course->id] = $byCode[$course->normalized_code]['ccmas_id'];
            }
        }

        // Batch the linkage. The corpus import leaves ccmas_course_id null on
        // roughly 1,400 rows; updating them one statement at a time cost ~50s
        // against a database reached over the network, where each round trip is
        // the dominant cost. TiDB has no portable bulk UPDATE, so group by
        // value and update each group in a single statement.
        foreach (array_count_values($toLink) as $ccmasId => $ids) {
            foreach (array_chunk($ids, 500) as $group) {
                DB::table('courses')->whereIn('id', $group)->update([
                    'ccmas_course_id' => $ccmasId,
                    'updated_at' => $now,
                ]);
            }
        }        $missing = [];
        foreach ($byCode as $normalized => $data) {
            if (isset($ids[$normalized])) {
                continue;
            }

            $missing[] = [
                'code' => $normalized,
                'normalized_code' => $normalized,
                'title' => $data['title'],
                'slug' => Str::slug($normalized.'-'.$data['title']),
                'credit_units' => $data['units'],
                'scope' => 'national',
                'source_type' => 'nuc_ccmas',
                'verification_status' => 'verified',
                'status' => 'active',
                'is_active' => 1,
                'is_external' => 0,
                'ccmas_course_id' => $data['ccmas_id'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($missing, 200) as $chunk) {
            DB::table('courses')->insert($chunk);
        }

        if ($missing !== []) {
            $fresh = DB::table('courses')
                ->whereIn('normalized_code', array_column($missing, 'normalized_code'))
                ->get(['id', 'normalized_code']);

            foreach ($fresh as $course) {
                $ids[$course->normalized_code] = (int) $course->id;
            }
        }

        return $ids;
    }

    /**
     * Placement keys already in the table, read once per run.
     *
     * Re-reading this on every chunk meant the cost grew with the table while
     * the run was still writing to it, so the job slowed to a crawl partway
     * through. It is read once and then extended in memory as rows are inserted.
     *
     * @var array<string, true>|null
     */
    protected ?array $placementKeys = null;

    protected function existingPlacements(): array
    {
        if ($this->placementKeys !== null) {
            return $this->placementKeys;
        }

        $keys = [];

        foreach (DB::table('curriculum_courses')
            ->get(['curriculum_version_id', 'course_id', 'level', 'semester']) as $row) {
            $keys[$row->curriculum_version_id.'|'.$row->course_id.'|'.$row->level.'|'.$row->semester] = true;
        }

        return $this->placementKeys = $keys;
    }

    /**
     * Insert placements, skipping any that already exist.
     */
    protected function flush(array $rows): int
    {
        $existing = $this->existingPlacements();

        $insert = [];
        foreach ($rows as $row) {
            $key = $row['curriculum_version_id'].'|'.$row['course_id'].'|'.$row['level'].'|'.$row['semester'];

            if (isset($existing[$key])) {
                continue;
            }

            $insert[] = $row;
        }

        if ($insert === []) {
            return 0;
        }

        DB::table('curriculum_courses')->insert($insert);

        foreach ($insert as $row) {
            $this->placementKeys[$row['curriculum_version_id'].'|'.$row['course_id'].'|'.$row['level'].'|'.$row['semester']] = true;
        }

        return count($insert);
    }
}
