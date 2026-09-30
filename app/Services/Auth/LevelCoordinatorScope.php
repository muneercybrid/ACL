<?php

namespace App\Services\Auth;

use App\Models\Curriculum\CcmasCourse;
use App\Models\LevelCoordinator;
use App\Models\ProgrammeLevelCourse;
use App\Models\User;
// The Support collection, not the Eloquent one: appointmentsFor() returns an
// Eloquent collection and offeringsFor() returns a query-builder one, and the
// Eloquent class extends this one, so it satisfies both signatures.
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What a level coordinator is allowed to change, and the courses they may add.
 *
 * Scope is never taken from a request. Every method starts from the account's
 * own `LevelCoordinator` rows — the appointments that bind a person to one
 * school, one programme and one level — and a submitted id is only accepted if
 * it matches one of those. That is the whole of the authorization here: there is
 * no path by which a coordinator reaches a programme or level they were not
 * appointed to, and so cannot reach another school's list by editing a URL or
 * a hidden field.
 */
class LevelCoordinatorScope
{
    /**
     * The appointments held by this account, with their programme resolved.
     */
    public function appointmentsFor(User $user): Collection
    {
        return LevelCoordinator::where('user_id', $user->id)
            ->where('status', 'active')
            ->with(['organization' => fn ($q) => $q->select('id', 'name', 'short_name', 'slug')])
            ->get();
    }

    /**
     * Whether this account administers the given programme at the given level.
     *
     * Consulted on every read and every write. Returns false rather than
     * throwing, so a caller can refuse quietly; the controllers turn a false
     * into a 403.
     */
    public function administers(User $user, int $academicProgramId, int $level): bool
    {
        $academicProgram = DB::table('academic_programs')
            ->where('id', $academicProgramId)
            ->first();

        if (! $academicProgram) {
            return false;
        }

        // The appointment names a curriculum programme; the offering row names
        // the same programme plus the school. Both halves have to line up, so
        // holding a level at one school never grants a level at another.
        return LevelCoordinator::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('programme_id', $academicProgram->nuc_programme_id)
            ->where('level', $level)
            ->whereIn('organization_id', function ($query) use ($academicProgram) {
                $query->select('organization_id')
                    ->from('academic_programs')
                    ->where('id', $academicProgram->id);
            })
            ->exists();
    }

    /**
     * The school offerings this coordinator may work on.
     */
    public function offeringsFor(User $user): Collection
    {
        $appointments = $this->appointmentsFor($user);

        if ($appointments->isEmpty()) {
            return new Collection();
        }

        $organizationIds = $appointments->pluck('organization_id')->unique();
        $programmeIds = $appointments->pluck('programme_id')->unique();

        // Every column is table-qualified: `programmes` also carries an
        // organization_id, so a bare 'organization_id' is ambiguous once the
        // two are joined.
        return DB::table('academic_programs')
            ->whereIn('academic_programs.organization_id', $organizationIds)
            ->whereIn('academic_programs.nuc_programme_id', $programmeIds)
            ->join('programmes', 'programmes.id', '=', 'academic_programs.nuc_programme_id')
            ->select([
                'academic_programs.id',
                'academic_programs.name',
                'academic_programs.code',
                'academic_programs.organization_id',
                'academic_programs.nuc_programme_id',
                'programmes.name as curriculum_name',
            ])
            ->get()
            ->map(function ($row) use ($appointments) {
                $row->levels = $appointments
                    ->filter(fn ($a) => (int) $a->programme_id === (int) $row->nuc_programme_id
                        && (int) $a->organization_id === (int) $row->organization_id)
                    ->pluck('level')
                    ->sort()
                    ->values();

                return $row;
            })
            // Only offerings that match an actual appointment. Without this a
            // school that runs a programme it does not staff would appear.
            ->filter(fn ($row) => $row->levels->isNotEmpty())
            ->values();
    }

    /**
     * The courses chosen for one offering at one level.
     */
    public function coursesFor(int $academicProgramId, int $level)
    {
        return ProgrammeLevelCourse::where('academic_program_id', $academicProgramId)
            ->where('level', $level)
            ->orderBy('course_code')
            ->get();
    }

    /**
     * Search the NUC CCMAS list for courses to offer.
     *
     * Filtering by level is offered but not required: the corpus only carries
     * an explicit level on 93% of its rows, so insisting on one would hide
     * roughly one course in fourteen from a coordinator who knows it belongs.
     * The level is returned either way so the caller can show it.
     */
    public function searchCcmas(?string $term, ?int $level = null, int $limit = 25): Collection
    {
        $query = CcmasCourse::query()->where('is_active', true);

        if (filled($term)) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

            // Matches the code or the title, so "COS 101" and "Introduction to
            // Computing" both find the same row.
            $query->where(function ($q) use ($like) {
                $q->where('course_code', 'like', $like)
                    ->orWhere('title', 'like', $like);
            });
        }

        if ($level) {
            $query->where('level', $level);
        }

        // The corpus lists a course once per programme that offers it, so a
        // plain search returns COS 101 a dozen times over. Over-fetch and then
        // collapse on the code, or the coordinator scrolls past the same row
        // instead of the courses they do not already run.
        $rows = $query->orderBy('course_code')
            ->orderBy('source_line')
            ->limit($limit * 4)
            ->get();

        return $rows->unique('course_code')->take($limit)->values();
    }

    /**
     * Add a course to an offering at a level.
     *
     * The title, code and units are copied onto the row rather than joined
     * through, so a school's list still reads correctly if the CCMAS import is
     * ever rebuilt underneath it.
     *
     * @throws \RuntimeException when the code is already on that list
     */
    public function addCourse(
        User $user,
        int $academicProgramId,
        int $level,
        ?int $ccmasCourseId,
        string $courseCode,
        string $title,
        ?float $creditUnits = null,
        ?string $semester = null,
        string $source = 'ccmas',
    ): ProgrammeLevelCourse {
        if (! $this->administers($user, $academicProgramId, $level)) {
            throw new \RuntimeException('You are not appointed to that programme and level.');
        }

        $code = strtoupper(trim($courseCode));

        $exists = ProgrammeLevelCourse::where('academic_program_id', $academicProgramId)
            ->where('level', $level)
            ->where('course_code', $code)
            ->exists();

        if ($exists) {
            // Spelled out rather than a bare constraint violation: the
            // administrator needs to know which of the two things collided.
            throw new \RuntimeException("{$code} is already on this programme's level {$level} list.");
        }

        return ProgrammeLevelCourse::create([
            'academic_program_id' => $academicProgramId,
            'level' => $level,
            'ccmas_course_id' => $ccmasCourseId,
            'course_code' => $code,
            'title' => trim($title),
            'credit_units' => $creditUnits,
            'source' => $source,
            'semester' => $semester,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    /**
     * Remove a course from an offering at a level.
     */
    public function removeCourse(User $user, int $courseId): void
    {
        $course = ProgrammeLevelCourse::findOrFail($courseId);

        if (! $this->administers($user, $course->academic_program_id, $course->level)) {
            throw new \RuntimeException('You are not appointed to that programme and level.');
        }

        $course->delete();
    }
}
