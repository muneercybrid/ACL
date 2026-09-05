<?php

namespace App\Services;

use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class EntitlementService
{
    /**
     * Resolve every course offering the student is institutionally entitled to.
     *
     * Rule (ACL Master Brief §3):
     * Active student membership -> programme + level -> matching offering targets
     * within active semesters of active academic sessions.
     *
     * Computed strictly from server-side membership data. Never from client input.
     */
    public function resolveEntitledOfferings(User $user): Collection
    {
        $memberships = $user->organizationMemberships()
            ->where('membership_type', 'student')
            ->where('status', 'active')
            ->whereNotNull('academic_program_id')
            ->get();

        if ($memberships->isEmpty()) {
            return new Collection();
        }

        $offeringIds = collect();

        foreach ($memberships as $membership) {
            $ids = CourseOffering::query()
                ->where('is_active', true)
                ->whereHas('semester', function ($query) {
                    $query->where('is_active', true)
                        ->whereHas('academicSession', fn ($sessionQuery) => $sessionQuery->where('is_active', true));
                })
                ->whereHas('targets', function ($query) use ($membership) {
                    $query->where('academic_program_id', $membership->academic_program_id)
                        ->where(function ($levelQuery) use ($membership) {
                            $levelQuery->where('level_id', $membership->current_level_id)
                                ->orWhereNull('level_id'); // Null level = all levels in programme
                        });
                })
                ->pluck('id');

            $offeringIds = $offeringIds->merge($ids);
        }

        return CourseOffering::query()
            ->whereIn('id', $offeringIds->unique()->values())
            ->with(['course', 'semester'])
            ->get();
    }

    /**
     * Idempotently grant free institutional access to every entitled offering.
     *
     * Safe to run repeatedly: existing enrollments are never duplicated
     * (application-level firstOrCreate + database-level unique constraint).
     *
     * Returns the number of NEW enrollments created.
     */
    public function syncInstitutionalEnrollments(User $user): int
    {
        return DB::transaction(function () use ($user) {
            $created = 0;

            foreach ($this->resolveEntitledOfferings($user) as $offering) {
                $enrollment = Enrollment::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'course_offering_id' => $offering->id,
                    ],
                    [
                        'source' => 'institutional_free',
                        'status' => 'active',
                        'enrolled_at' => now(),
                        'expires_at' => $offering->semester?->end_date?->endOfDay(),
                    ]
                );

                if ($enrollment->wasRecentlyCreated) {
                    $created++;
                }
            }

            return $created;
        });
    }
}
