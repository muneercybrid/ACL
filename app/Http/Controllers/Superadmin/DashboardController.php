<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\InstitutionOnboarding;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Student;
use App\Models\StudentRegistrationVerification;
use App\Models\SuperadminAuditLog;
use App\Models\SystemAlert;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Superadmin Command Center - Main Dashboard
     */
    public function index(): View
    {
        $now = now();
        $todayStart = $now->copy()->startOfDay();
        $weekStart = $now->copy()->startOfWeek();
        $monthStart = $now->copy()->startOfMonth();

        // Twenty-two separate round trips to TiDB Cloud cost roughly seven
        // seconds on every dashboard load -- a trivial count() alone takes
        // 0.2-0.9s over the network. None of this needs to be exact to the
        // second, so the panel is cached briefly and rebuilt in one go.
        //
        // 60 seconds: short enough that the numbers stay honest, long enough
        // that navigating the Command Center does not pay for it every click.
        $platformStats = \Illuminate\Support\Facades\Cache::remember(
            'superadmin.dashboard.platform-stats',
            now()->addSeconds(60),
            fn () => $this->computePlatformStats($now, $todayStart, $weekStart, $monthStart),
        );


        // The same reasoning as the stats panel, and the same round trips: the
        // activity counters, system health, alerts and the activity feed are all
        // read-only views of tables nobody expects to change by the second.
        // Grouped into a single cached payload so a warm dashboard is one read
        // rather than a dozen.
        $dashboardData = \Illuminate\Support\Facades\Cache::remember(
            'superadmin.dashboard.panels',
            now()->addSeconds(60),
            function () use ($todayStart, $weekStart, $monthStart) {
            // Activity Overview
            $activity = [
                'registrations' => [
                    'today' => StudentRegistrationVerification::where('created_at', '>=', $todayStart)->count(),
                    'this_week' => StudentRegistrationVerification::where('created_at', '>=', $weekStart)->count(),
                    'this_month' => StudentRegistrationVerification::where('created_at', '>=', $monthStart)->count(),
                ],
                'jamb' => [
                    'requests_today' => StudentRegistrationVerification::where('created_at', '>=', $todayStart)->count(),
                    'successful' => StudentRegistrationVerification::where('status', 'verified')->where('verified_at', '>=', $todayStart)->count(),
                    'failed' => StudentRegistrationVerification::whereIn('status', ['not_found', 'invalid_input', 'provider_timeout', 'provider_unavailable', 'temporary_failure'])->where('created_at', '>=', $todayStart)->count(),
                    'manual_required' => StudentRegistrationVerification::where('status', 'manual_verification_required')->count(),
                ],
                'logins' => [
                    // Would need login tracking table - placeholder for now
                    'today' => 0,
                    'failed' => 0,
                ],
                'content_changes' => [
                    'today' => SuperadminAuditLog::where('action', 'like', '%content%')->where('created_at', '>=', $todayStart)->count(),
                    'this_week' => SuperadminAuditLog::where('action', 'like', '%content%')->where('created_at', '>=', $weekStart)->count(),
                ],
            ];

            // System Health
            $health = $this->getSystemHealth();

            // Recent Alerts
            $alerts = SystemAlert::active()
                ->orderBy('severity', 'desc')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            // Recent Activity Feed
            $recentActivity = SuperadminAuditLog::with(['actor', 'organization'])
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();

            // Institutions needing attention
            $institutionsNeedingAttention = $this->getInstitutionsNeedingAttention();

                // Only the scalar aggregates are cached. Eloquent collections do
                // not survive a cache round trip as objects -- they come back as
                // arrays, and the view reads $alert->severity and builds route
                // keys from the models. Those are fetched fresh below instead.
                return [
                    'activity' => $activity,
                    'health' => $health,
                ];
            },
        );

        // Not cached: alerts and the activity feed are models, not numbers.
        // These are small, indexed reads and cheap next to the counts above.
        $alerts = SystemAlert::active()
            ->orderBy('severity', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $recentActivity = SuperadminAuditLog::with(['actor', 'organization'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $institutionsNeedingAttention = $this->getInstitutionsNeedingAttention();

        return view('superadmin.dashboard', [
            'platformStats' => $platformStats,
            'activity' => $dashboardData['activity'],
            'health' => $dashboardData['health'],
            'alerts' => $alerts,
            'recentActivity' => $recentActivity,
            'institutionsNeedingAttention' => $institutionsNeedingAttention,
        ]);
    }

    /**
     * Get system health metrics
     */
    protected function getSystemHealth(): array
    {
        $health = [];

        // Database
        try {
            DB::connection()->getPdo();
            $health['database'] = ['status' => 'healthy', 'message' => 'Connected'];
        } catch (\Throwable $e) {
            $health['database'] = ['status' => 'critical', 'message' => $e->getMessage()];
        }

        // Queue
        $failedJobs = DB::table('failed_jobs')->count();
        $health['queue'] = [
            'status' => $failedJobs > 100 ? 'warning' : ($failedJobs > 10 ? 'degraded' : 'healthy'),
            'message' => "{$failedJobs} failed jobs",
        ];

        // Scheduled tasks
        $health['scheduler'] = [
            'status' => 'healthy',
            'message' => 'Running',
        ];

        // External providers
        $health['jamb'] = [
            'status' => 'healthy',
            'message' => 'Operational',
        ];

        // Storage
        $diskFree = disk_free_space(storage_path());
        $diskTotal = disk_total_space(storage_path());
        $diskUsage = $diskTotal > 0 ? (1 - ($diskFree / $diskTotal)) * 100 : 0;
        $health['storage'] = [
            'status' => $diskUsage > 90 ? 'critical' : ($diskUsage > 75 ? 'warning' : 'healthy'),
            'message' => sprintf('%.1f%% used', $diskUsage),
        ];

        return $health;
    }

    /**
     * Get institutions needing attention
     */
    protected function getInstitutionsNeedingAttention(): array
    {
        // Student counts are aggregated once with a GROUP BY and read from the
        // result in memory.
        //
        // Two earlier shapes both hurt here. An inline count() inside the map
        // issued one query per organization, so 126 organizations meant 126
        // round trips to a remote TiDB instance. Replacing it with withCount()
        // kept it to one query but expressed it as a correlated subquery, which
        // TiDB executed in about a second — barely better, because the
        // subquery is re-evaluated per row. One grouped aggregate is a single
        // scan and costs one round trip regardless of how many organizations
        // the platform has.
        $activeStudentCounts = OrganizationMembership::where('membership_type', 'student')
            ->where('status', 'active')
            ->select('organization_id', DB::raw('count(*) as student_count'))
            ->groupBy('organization_id')
            ->pluck('student_count', 'organization_id');

        return Organization::with(['onboarding', 'memberships' => fn ($q) => $q->where('membership_type', 'administrator')->where('status', 'active')])
            ->where('is_active', true)
            ->get()
            ->map(function ($org) {
                $issues = [];

                $onboarding = $org->onboarding;
                if ($onboarding && !$onboarding->isCompleted()) {
                    $issues[] = 'Onboarding incomplete (' . $onboarding->getCompletionPercentage() . '%)';
                }

                $admins = $org->memberships->filter(fn ($m) => $m->membership_type === 'administrator');
                if ($admins->isEmpty()) {
                    $issues[] = 'No administrator assigned';
                }

                // Read from the aggregate rather than issuing a query per
                // organization. See the note on $activeStudentCounts above.
                $students = $activeStudentCounts[$org->id] ?? 0;
                if ($students === 0) {
                    $issues[] = 'No active students';
                }

                return [
                    'organization' => $org,
                    'issues' => $issues,
                    'severity' => count($issues) > 2 ? 'high' : (count($issues) > 0 ? 'medium' : 'low'),
                ];
            })
            ->filter(fn ($item) => !empty($item['issues']))
            ->take(10)
            ->values()
            ->toArray();
    }

    /**
     * Live activity feed (for HTMX/polling)
     */
    public function activityFeed(): View
    {
        $activities = SuperadminAuditLog::with(['actor', 'organization', 'targetUser'])
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return view('superadmin.partials.activity-feed', compact('activities'));
    }

    /**
     * System health detail (for HTMX/polling)
     */
    public function healthDetail(): View
    {
        $health = $this->getSystemHealth();
        return view('superadmin.partials.health-detail', compact('health'));
    }

    /**
     * Builds the platform statistics panel.
     *
     * Split out of index() so the cache can wrap it. The queries are unchanged;
     * they simply no longer run on every request.
     */
    private function computePlatformStats($now, $todayStart, $weekStart, $monthStart): array
    {
        // Platform Overview
        $platformStats = [
            'institutions' => [
                'total' => Organization::count(),
                'active' => Organization::where('is_active', true)->count(),
                'pending_onboarding' => InstitutionOnboarding::whereIn('status', ['pending', 'invited', 'started', 'partially_completed'])->count(),
            ],
            'users' => [
                'students' => Student::count(),
                'active_students' => Student::where('verification_status', 'verified')->count(),
                'external_learners' => OrganizationMembership::where('membership_type', 'external_learner')->where('status', 'active')->count(),
                'staff' => OrganizationMembership::where('membership_type', 'staff')->where('status', 'active')->count(),
                'administrators' => OrganizationMembership::where('membership_type', 'administrator')->where('status', 'active')->count(),
            ],
            'academic' => [
                'courses' => \App\Models\Course::where('is_active', true)->count(),
                'active_courses' => CourseOffering::where('is_active', true)->count(),
                'programmes' => \App\Models\Curriculum\Programme::where('status', 'active')->count(),
                'departments' => \App\Models\Department::where('is_active', true)->count(),
                'faculties' => \App\Models\Faculty::where('is_active', true)->count(),
            ],
        ];

        return $platformStats;
    }

}
