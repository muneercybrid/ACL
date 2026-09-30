<?php

namespace App\Services\Courses;

use App\Models\Course;
use App\Models\Curriculum\CcmasCourse;
use App\Models\Organization;
use App\Models\OrganizationCourseCode;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The platform's single course catalogue, and the local codes schools give it.
 *
 * A course exists once here. Schools that run it point at the same row and
 * therefore share its content; they differ only in the code they call it by.
 * "Introduction to Malware and Social Engineering" can be NUK-CYB101 at one
 * university and BUK-CSC 111 at another while remaining one course.
 *
 * Matching is deliberately not automatic. Measured against the NUC corpus,
 * 277 titles carry more than one course code, and 116 of those stay ambiguous
 * even after adding level, credit units and discipline — "Research Project"
 * appears in Medical Microbiology and Veterinary Reproduction with an identical
 * signature. A title-based dedupe would merge those and corrupt them. So
 * `search()` ranks and suggests, and a person decides. The 4,600 unambiguous
 * courses are still found in one click.
 */
class CentralCourseService
{
    /**
     * Search the central catalogue.
     *
     * Results carry the caller's own code for each course when one is already
     * registered, so a coordinator recognises a course another school already
     * teaches without having to know it exists.
     */
    public function search(?string $term, ?int $organizationId = null, int $limit = 25): Collection
    {
        $query = Course::query()->where('is_active', true);

        if (filled($term)) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($term)).'%';

            // Matches code or title. A code is often the fastest way in, but it
            // is the school's code and differs between schools, so the title
            // match is what actually makes sharing reachable.
            $query->where(function ($q) use ($like) {
                $q->where('code', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhere('normalized_title', 'like', mb_strtolower($like));
            });
        }

        $courses = $query->orderBy('title')->limit($limit)->get();

        return $this->withLocalCodes($courses, $organizationId);
    }

    /**
     * Suggest central courses that plausibly match a course being created.
     *
     * This is what turns "Introduction to Malware and Social Engineering" from
     * a second, separate course into a shared one. It never creates anything —
     * it returns candidates for a person to confirm, because the 116 ambiguous
     * cases cannot be told apart from the text alone.
     */
    public function suggestFor(string $title, ?int $organizationId = null, int $limit = 10): Collection
    {
        $normalized = $this->normalize($title);

        if ($normalized === '') {
            return new Collection();
        }

        $courses = Course::query()
            ->where('is_active', true)
            ->where('normalized_title', $normalized)
            ->orderBy('title')
            ->limit($limit)
            ->get();

        return $this->withLocalCodes($courses, $organizationId);
    }

    /**
     * The central course for a NUC row, creating it the first time.
     *
     * The link is recorded on the course itself, so two schools adding the same
     * NUC course get the same central row rather than one each. This is the
     * single most important line in the sharing model.
     */
    public function centralForCcmas(CcmasCourse $ccmas): Course
    {
        $existing = Course::where('ccmas_course_id', $ccmas->id)->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($ccmas) {
            // Re-checked inside the transaction: two schools picking the same
            // NUC course at the same moment would otherwise both miss and both
            // insert, and the unique index would turn the second into an
            // error the coordinator sees rather than a shared course.
            $existing = Course::where('ccmas_course_id', $ccmas->id)->lockForUpdate()->first();

            if ($existing) {
                return $existing;
            }

            $central = Course::create([
                'code' => $this->uniqueCodeFor($ccmas->course_code),
                'title' => $ccmas->title,
                'slug' => $this->uniqueSlug($ccmas->title),
                'normalized_title' => $this->normalize($ccmas->title),
                'credit_units' => $ccmas->credit_units ?? 0,
                'description' => null,
                'scope' => 'university',
                'source_type' => 'nuc_ccmas',
                'verification_status' => 'verified',
                'status' => 'active',
                'is_active' => true,
                'ccmas_course_id' => $ccmas->id,
                'nuc_discipline_id' => $ccmas->nuc_discipline_id,
                'source_document' => $ccmas->source_document,
            ]);

            return $central;
        });
    }

    /**
     * Create a central course that no NUC row covers.
     *
     * Reached when a coordinator has searched, found nothing suitable, and is
     * recording a course their programme genuinely requires.
     */
    public function createCentral(
        string $title,
        string $code,
        ?float $creditUnits = null,
        ?int $organizationId = null,
    ): Course {
        return Course::create([
            'code' => $this->uniqueCodeFor($code),
            'title' => trim($title),
            'slug' => $this->uniqueSlug($title),
            'normalized_title' => $this->normalize($title),
            'credit_units' => $creditUnits ?? 0,
            'scope' => 'university',
            'source_type' => 'institution',
            'verification_status' => 'pending',
            'status' => 'active',
            'is_active' => true,
            'institution_id' => $organizationId,
        ]);
    }

    /**
     * Record a school's own code for a central course.
     *
     * Returns the existing mapping when the school already uses this code for
     * this course, and refuses when the code means something else at that
     * school — a code is a promise to the people reading it.
     */
    public function registerLocalCode(Organization $organization, Course $course, string $localCode): OrganizationCourseCode
    {
        $localCode = trim($localCode);

        $existing = OrganizationCourseCode::where('organization_id', $organization->id)
            ->where('local_code', $localCode)
            ->first();

        if ($existing) {
            if ((int) $existing->course_id === (int) $course->id) {
                return $existing;
            }

            $school = $organization->short_name ?: $organization->name;

            throw new \RuntimeException(
                "{$school} already uses \"{$localCode}\" for a different course."
            );
        }

        return OrganizationCourseCode::create([
            'organization_id' => $organization->id,
            'course_id' => $course->id,
            'local_code' => $localCode,
        ]);
    }

    /**
     * The school's code for a central course, if it has one.
     */
    public function localCodeFor(int $courseId, int $organizationId): ?string
    {
        return OrganizationCourseCode::where('course_id', $courseId)
            ->where('organization_id', $organizationId)
            ->value('local_code');
    }

    /**
     * How many schools run a course — shown to the coordinator who picks it, so
     * the effect of sharing is visible rather than theoretical.
     */
    public function usageCount(int $courseId): int
    {
        return OrganizationCourseCode::where('course_id', $courseId)->distinct()->count('organization_id');
    }

    /**
     * Attach the caller's local code and sharing count to a set of courses.
     */
    private function withLocalCodes(Collection $courses, ?int $organizationId): Collection
    {
        if ($courses->isEmpty()) {
            return $courses;
        }

        $courseIds = $courses->pluck('id');

        $localCodes = $organizationId
            ? OrganizationCourseCode::whereIn('course_id', $courseIds)
                ->where('organization_id', $organizationId)
                ->pluck('local_code', 'course_id')
            : collect();

        // get() before grouping: map() is a collection method, not a builder
        // one, and calling it on the query forwards to the builder and fails.
        $usage = OrganizationCourseCode::whereIn('course_id', $courseIds)
            ->get()
            ->groupBy('course_id')
            ->map(fn ($rows) => $rows->pluck('organization_id')->unique()->count());

        return $courses->map(function (Course $course) use ($localCodes, $usage) {
            $course->local_code = $localCodes[$course->id] ?? null;
            $course->usage_count = $usage[$course->id] ?? 0;

            return $course;
        });
    }

    /**
     * Lowercase, collapsed, punctuation removed.
     *
     * Deliberately mild. Aggressive stemming would treat genuinely different
     * courses as one; this only collapses formatting differences, which is the
     * class of difference that actually occurs between schools.
     *
     * "&" is expanded to "and" rather than dropped, because "&" and "and" mean
     * the same thing in a course title and dropping it would produce
     * "malware social engineering" against a stored "malware & social
     * engineering" — two spellings of one course that then never match.
     */
    public function normalize(string $title): string
    {
        $expanded = str_ireplace(['&', '+'], [' and ', ' and '], $title);
        $collapsed = Str::lower(trim($expanded));
        $collapsed = preg_replace('/[^a-z0-9]+/', ' ', $collapsed) ?? '';

        return trim(preg_replace('/\s+/', ' ', $collapsed) ?? $collapsed);
    }

    private function uniqueCodeFor(string $preferred): string
    {
        $base = strtoupper(trim($preferred)) ?: 'COURSE';
        $code = $base;
        $suffix = 2;

        while (Course::where('code', $code)->exists()) {
            $code = $base.'-'.$suffix;
            $suffix++;
        }

        return $code;
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'course';
        $slug = $base;
        $suffix = 2;

        while (Course::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
