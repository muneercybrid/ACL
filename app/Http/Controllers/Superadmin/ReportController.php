<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\InstitutionOnboarding;
use App\Models\Organization;
use App\Models\Student;
use App\Models\StudentRegistrationVerification;
use App\Models\SuperadminAuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(): View
    {
        return view('superadmin.reports.index', [
            'reportTypes' => [
                'institutions' => 'Institution Report',
                'registrations' => 'Student Registration Report',
                'staff' => 'Staff Report',
                'academic' => 'Academic Structure Report',
                'activity' => 'Activity Report',
                'authentication' => 'Authentication Report',
                'jamb' => 'JAMB Verification Report',
                'audit' => 'Audit Report',
            ],
        ]);
    }

    /**
     * Export a CSV report for a given type. Filterable by date range.
     */
    public function export(Request $request, string $type): StreamedResponse
    {
        $from = $request->date('from', now()->subDays(30))->startOfDay();
        $to = $request->date('to', now())->endOfDay();

        $filename = Str::slug($type) . '-report-' . now()->format('Ymd-His') . '.csv';

        $columns = [];
        $rows = collect();

        switch ($type) {
            case 'institutions':
                $columns = ['ID', 'Name', 'Type', 'Code', 'State', 'Status', 'Active', 'Created', 'Onboarding'];
                $rows = Organization::with('onboarding')->get()->map(fn ($org) => [
                    $org->id, $org->name, $org->type, $org->code ?? '', $org->state ?? '',
                    $org->status ?? 'active', $org->is_active ? 'yes' : 'no',
                    $org->created_at?->toDateTimeString(),
                    $org->onboarding?->status ?? 'n/a',
                ]);
                break;

            case 'registrations':
                $columns = ['ID', 'User Email', 'JAMB Number', 'Verified Name', 'Institution', 'Programme', 'Status', 'Verified At', 'Created'];
                $rows = StudentRegistrationVerification::with('user')
                    ->whereBetween('created_at', [$from, $to])
                    ->get()
                    ->map(fn ($v) => [
                        $v->id, $v->user?->email ?? '', $v->jamb_registration_number ?? '',
                        $v->verified_name ?? '', $v->verified_institution ?? '', $v->verified_programme ?? '',
                        $v->status, $v->verified_at?->toDateTimeString() ?? '', $v->created_at?->toDateTimeString() ?? '',
                    ]);
                break;

            case 'staff':
                $columns = ['User ID', 'Name', 'Email', 'Organization', 'Membership', 'Status', 'Joined'];
                $rows = \App\Models\OrganizationMembership::with(['user', 'organization'])
                    ->where('membership_type', 'staff')
                    ->whereBetween('joined_at', [$from, $to])
                    ->get()
                    ->map(fn ($m) => [
                        $m->user_id, $m->user?->name ?? '', $m->user?->email ?? '',
                        $m->organization?->name ?? '', $m->membership_type, $m->status,
                        $m->joined_at?->toDateTimeString() ?? '',
                    ]);
                break;

            case 'academic':
                $columns = ['Curriculum Version ID', 'Programme', 'Session', 'Label', 'Scope', 'Source', 'Active', 'Courses'];
                $rows = \App\Models\Curriculum\CurriculumVersion::withCount('curriculumCourses')
                    ->with(['programme', 'academicSession'])
                    ->get()
                    ->map(fn ($v) => [
                        $v->id, $v->programme?->name ?? '', $v->academicSession?->name ?? '',
                        $v->version_label, $v->scope, $v->source_type, $v->is_active ? 'yes' : 'no',
                        $v->curriculum_courses_count,
                    ]);
                break;

            case 'activity':
                $columns = ['Event ID', 'Action', 'Description', 'Actor', 'Organization', 'Severity', 'Result', 'Created'];
                $rows = SuperadminAuditLog::with(['actor', 'organization'])
                    ->whereBetween('created_at', [$from, $to])
                    ->get()
                    ->map(fn ($log) => [
                        $log->id, $log->action, $log->description ?? '',
                        $log->actor?->name ?? 'System', $log->organization?->name ?? '',
                        $log->severity, $log->result, $log->created_at?->toDateTimeString() ?? '',
                    ]);
                break;

            case 'authentication':
                $columns = ['Event ID', 'Action', 'Actor', 'Target User', 'Severity', 'Description', 'Created'];
                $rows = SuperadminAuditLog::with(['actor', 'targetUser'])
                    ->whereBetween('created_at', [$from, $to])
                    ->where(fn ($q) => $q->where('action', 'like', '%login%')->orWhere('action', 'user.suspended')->orWhere('action', 'user.restored'))
                    ->get()
                    ->map(fn ($log) => [
                        $log->id, $log->action, $log->actor?->name ?? 'System',
                        $log->targetUser?->name ?? '', $log->severity, $log->description ?? '',
                        $log->created_at?->toDateTimeString() ?? '',
                    ]);
                break;

            case 'jamb':
                $columns = ['ID', 'JAMB Number', 'Status', 'Verified Name', 'Institution', 'Programme', 'Verified At', 'Created'];
                $rows = StudentRegistrationVerification::whereBetween('created_at', [$from, $to])
                    ->get()
                    ->map(fn ($v) => [
                        $v->id, $v->jamb_registration_number ?? '', $v->status,
                        $v->verified_name ?? '', $v->verified_institution ?? '', $v->verified_programme ?? '',
                        $v->verified_at?->toDateTimeString() ?? '', $v->created_at?->toDateTimeString() ?? '',
                    ]);
                break;

            case 'audit':
                $columns = ['Event ID', 'Action', 'Description', 'Actor', 'Target', 'Org', 'Severity', 'Result', 'Created'];
                $rows = SuperadminAuditLog::with(['actor', 'organization', 'targetUser'])
                    ->whereBetween('created_at', [$from, $to])
                    ->get()
                    ->map(fn ($log) => [
                        $log->id, $log->action, $log->description ?? '',
                        $log->actor?->name ?? 'System', $log->targetUser?->name ?? '',
                        $log->organization?->name ?? '', $log->severity, $log->result,
                        $log->created_at?->toDateTimeString() ?? '',
                    ]);
                break;

            default:
                abort(404, 'Unknown report type.');
        }

        return response()->streamDownload(function () use ($columns, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);
            foreach ($rows as $row) {
                fputcsv($handle, array_values($row));
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}