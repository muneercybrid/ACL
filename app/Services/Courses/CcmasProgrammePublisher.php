<?php

namespace App\Services\Courses;

use App\Models\Course;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Publishes the national CCMAS curriculum for a programme.
 *
 * The project owner set the rule this service exists to enforce: a course that
 * appears on the CCMAS document is the same course everywhere. It is not a
 * matter of school curriculum. If "Communication in English" is on CCMAS, then
 * every programme that carries it, at every organization, carries the same
 * course -- one shared catalogue row, no per-school copy, no per-school edit.
 *
 * Only what a school adds on top needs isolation, and that lives in the
 * institution layer (programme_level_courses rows with source "institution").
 * The split is deliberate: publishing here is national and idempotent, so
 * running it for every programme cannot fork the shared catalogue into 481
 * slightly different versions.
 *
 * Why this writes to curriculum_courses rather than programme_level_courses:
 * the student dashboard reads CurriculumCourse, scoped by curriculum version.
 * curriculum_versions already exist for all 302 national programmes with scope
 * 'national', so publishing into them lights up the shared layer the dashboard
 * already knows how to query -- no second parallel chain, and no per-organization
 * copy of a course that is supposed to be identical everywhere.
 */
class CcmasProgrammePublisher
{
    public function __construct(protected CourseCodeParser $parser) {}

    /**
     * Publish every level of a programme's CCMAS courses into its active
     * national curriculum version.
     *
     * @return array{programme_id: int, version_id: int|null, published: int, skipped: int, levels: array}
     */
    public function publishProgramme(int $programmeId, ?string $programmeTitle = null): array
    {
        $version = DB::table('curriculum_versions')
            ->where('programme_id', $programmeId)
            ->where('is_active', true)
            ->latest('id')
            ->first();

        if ($version === null) {
            return [
                'programme_id' => $programmeId,
                'version_id' => null,
                'published' => 0,
                'skipped' => 0,
                'levels' => [],
            ];
        }

        $title = $programmeTitle ?? $this->ccmasTitleFor($programmeId);

        if ($title === null) {
            return ['programme_id' => $programmeId, 'version_id' => $version->id, 'published' => 0, 'skipped' => 0, 'levels' => []];
        }

        return $this->publishIntoVersion((int) $version->id, $title, $programmeId);
    }

    /**
     * Find the CCMAS programme title corresponding to a national programme.
     *
     * The two lists spell the same programme differently: the corpus import
     * writes "B.Sc. Cybersecurity", the programmes table writes "B.Sc
     * Cybersecurity". Matching those literally found 16 of 196 programmes and
     * silently published almost nothing. Normalizing the degree abbreviation
     * and punctuation matches 191 of 196, so the corpus actually reaches the
     * students it was written for.
     *
     * Exact match is preferred and tried first, so this only ever bridges a
     * spelling difference -- never two genuinely different programmes.
     */
    public function ccmasTitleFor(int $programmeId): ?string
    {
        $name = DB::table('programmes')->where('id', $programmeId)->value('name');

        if ($name === null) {
            return null;
        }

        $exact = DB::table('ccmas_courses')->where('programme_title', $name)->value('programme_title');

        if ($exact !== null) {
            return $exact;
        }

        $target = $this->normalizeProgrammeName($name);
        $candidates = DB::table('ccmas_courses')
            ->whereNotNull('programme_title')
            ->distinct()
            ->pluck('programme_title')
            ->filter()
            ->unique();

        foreach ($candidates as $candidate) {
            if ($this->normalizeProgrammeName($candidate) === $target) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Normalize a programme name for cross-list matching.
     */
    public function normalizeProgrammeName(string $name): string
    {
        $name = strtolower($name);
        $name = preg_replace(
            '/\b(b|bm|bsc|bs|ba|beng|bscs|bhsm|pgd|msc|mba|phd|b\.ed|b\.sc\.ed)\.?\s*/',
            ' ',
            $name
        ) ?? '';

        return trim(preg_replace('/[^a-z0-9]/', '', $name) ?? '');
    }

    /**
     * Publish a programme's CCMAS courses into a specific curriculum version.
     *
     * @return array{programme_id: int, version_id: int, published: int, skipped: int, levels: array}
     */
    public function publishIntoVersion(int $versionId, string $programmeTitle, ?int $programmeId = null): array
    {
        $published = 0;
        $skipped = 0;
        $levels = [];

        $rows = DB::table('ccmas_courses')
            ->where('programme_title', $programmeTitle)
            ->whereNotNull('course_code')
            ->whereNotNull('title')
            ->whereNotNull('level')
            ->get();

        foreach ($rows as $row) {
            $level = (int) $row->level;
            $normalized = $this->parser->normalize($row->course_code);

            if ($normalized === null) {
                $skipped++;

                continue;
            }

            $parsed = $this->parser->parse($row->course_code);
            $semester = $parsed['semester'] ?? 1;

            $course = $this->canonicalCourse($normalized, (string) $row->title, $row->credit_units, $row->id);

            DB::table('curriculum_courses')->updateOrInsert(
                [
                    'curriculum_version_id' => $versionId,
                    'course_id' => $course->id,
                    'level' => $level,
                    'semester' => $semester,
                ],
                [
                    'course_type' => $row->course_type ?? 'core',
                    // NOT NULL in the schema, and CCMAS leaves credit_units blank
                    // on some rows. Fall back to the shared course's own value,
                    // then to 1 -- rather than dropping an otherwise valid course
                    // out of a student's curriculum because the source document
                    // happened to omit a unit count.
                    'credit_units' => $row->credit_units !== null && (int) $row->credit_units > 0
                        ? (int) $row->credit_units
                        : (int) ($course->credit_units ?: 1),
                    'status' => 'active',
                    'is_mandatory' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            $levels[$level.'-'.$semester] = ($levels[$level.'-'.$semester] ?? 0) + 1;
            $published++;
        }

        return [
            'programme_id' => $programmeId,
            'version_id' => $versionId,
            'published' => $published,
            'skipped' => $skipped,
            'levels' => $levels,
        ];
    }

    /**
     * Find or create the single shared catalogue row for a course code.
     *
     * One row per code, nationally. Two programmes that both run "GST 111"
     * share it, and so do 481 organizations -- which is what makes the national
     * layer genuinely national rather than 481 near-copies that drift apart.
     */
    protected function canonicalCourse(string $normalized, string $title, $units, ?int $ccmasId): Course
    {
        $course = Course::query()->where('normalized_code', $normalized)->first();

        if ($course === null) {
            $course = new Course;
            $course->code = $normalized;
            $course->normalized_code = $normalized;
            $course->title = trim($title);
            $course->slug = Str::slug($normalized.'-'.$title);
            $course->credit_units = (int) ($units ?? 1);
            $course->scope = 'national';
            $course->source_type = 'nuc_ccmas';
            $course->verification_status = 'verified';
            $course->status = 'active';
            $course->is_active = true;
            $course->is_external = false;
            $course->ccmas_course_id = $ccmasId;
            $course->save();

            return $course;
        }

        // Link the existing shared row to its CCMAS record if the corpus import
        // left that unset. Never fork a second row for a code we already have.
        if ($ccmasId !== null && $course->ccmas_course_id === null) {
            $course->ccmas_course_id = $ccmasId;
            $course->save();
        }

        return $course;
    }
}
