<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\LevelCoordinator;
use Illuminate\Support\Facades\DB;
use App\Services\Auth\RoleHomeResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The level coordinator's area.
 *
 * Scope is deliberately the narrowest one that is still useful: a coordinator
 * sees the levels they were appointed to, for the programmes they were
 * appointed to, and nothing else. The rows that define that scope are
 * level_coordinators entries, and they are read from the database on every
 * request rather than from anything the browser supplies, so there is no URL
 * parameter that can widen what a coordinator sees.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly RoleHomeResolver $roles) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        if (! $this->roles->holdsRole($user, RoleHomeResolver::ROLE_LEVEL_COORDINATOR)) {
            // Not a coordinator: send them to the area that actually belongs to
            // them rather than rendering an empty shell.
            abort(403, 'This account is not appointed as a level coordinator.');
        }

        $appointments = LevelCoordinator::where('user_id', $user->id)
            ->with(['programme' => function ($query) {
                $query->select('id', 'name', 'code');
            }])
            ->orderBy('level')
            ->orderBy('programme_id')
            ->get();

        return view('coordinator.dashboard', [
            'appointments' => $appointments,
            'activeCount' => $appointments->filter->isActive()->count(),
            'levels' => $this->levelsCovered($appointments),
            'students' => $this->studentsInScope($appointments),
        ]);
    }

    /**
     * The distinct levels this coordinator is appointed to.
     */
    private function levelsCovered(Collection $appointments): Collection
    {
        return $appointments
            ->pluck('level')
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }

    /**
     * How many students sit inside this coordinator's appointments.
     *
     * A student's programme and level are recorded on organization_memberships,
     * not on the students row, so the count joins through there. It is bounded
     * by the programme+level pairs the coordinator actually holds, so the
     * number cannot exceed what they are entitled to see.
     */
    private function studentsInScope(Collection $appointments): int
    {
        if ($appointments->isEmpty()) {
            return 0;
        }

        $query = DB::table('organization_memberships')
            ->join('academic_programs', 'academic_programs.id', '=', 'organization_memberships.academic_program_id')
            ->join('students', 'students.user_id', '=', 'organization_memberships.user_id')
            ->select('organization_memberships.id')
            ->distinct();

        $query->where(function ($q) use ($appointments) {
            foreach ($appointments as $appointment) {
                $q->orWhere(function ($inner) use ($appointment) {
                    $inner->where('academic_programs.nuc_programme_id', $appointment->programme_id)
                        ->where('students.level', $appointment->level);
                });
            }
        });

        return $query->count();
    }
}
