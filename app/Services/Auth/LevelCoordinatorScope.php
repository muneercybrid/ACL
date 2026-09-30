<?php

namespace App\Services\Auth;

use App\Models\Course;
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
     * Add a course to an offering at a level.
     *
     * The course is named by its central `course_id`, not by its own text, so
     * the offering shares the platform's content rather than keeping a private
     * copy. `courseCode` is recorded alongside as this school's label for it,
     * which is how two universities can run the same course under different
     * codes and still be looking at one set of chapters.
     *
     * @throws \RuntimeException when the code is already on that list
     */
    public function addCourse(
        User $user,
        int $academicProgramId,
        int $level,
        Course $course,
        string $courseCode,
        ?string $semester = null,
    ): ProgrammeLevelCourse {
        if (! $this->administers($user, $academicProgramId, $level)) {
            throw new \RuntimeException('You are not appointed to that programme and level.');
        }

        $code = trim($courseCode);

        if ($code === '') {
            // A course with no code cannot be timetabled, examined or referred
            // to by anyone, so it is refused rather than stored blank.
            throw new \RuntimeException('Give the course a code for your institution.');
        }

        // The same central course may not be listed twice at one level under two
        // different codes. That is one course on the timetable twice, and it is
        // also the failure mode of sharing: a coordinator adds a course, fails
        // to see it in the list, and adds it again believing it was missing.
        $alreadyListed = ProgrammeLevelCourse::where('academic_program_id', $academicProgramId)
            ->where('level', $level)
            ->where('course_id', $course->id)
            ->exists();

        if ($alreadyListed) {
            throw new \RuntimeException(
                "\"{$course->title}\" is already on this programme's level {$level} list."
            );
        }

        // Another central course may already hold this code at this level. The
        // codes are independent between schools, so only a clash here is a
        // problem — and it means the same code means two things on one
        // programme.
        $codeClash = ProgrammeLevelCourse::where('academic_program_id', $academicProgramId)
            ->where('level', $level)
            ->where('course_code', $code)
            ->where('course_id', '!=', $course->id)
            ->exists();

        if ($codeClash) {
            throw new \RuntimeException("Code {$code} is already used by a different course at level {$level}.");
        }

        return ProgrammeLevelCourse::create([
            'academic_program_id' => $academicProgramId,
            'level' => $level,
            'course_id' => $course->id,
            'course_code' => $code,
            'title' => $course->title,
            'credit_units' => $course->credit_units,
            'source' => $course->ccmas_course_id ? 'ccmas' : 'manual',
            'semester' => $semester,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    /**
     * Remove a course from an offering at a level.
     *
     * Removes this school's offering only. The central course and its content
     * are untouched, because another school is almost certainly using them.
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
