<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicProgram;
use App\Models\Curriculum\CurriculumCourse;
use App\Models\Curriculum\CurriculumVersion;
use App\Models\Curriculum\Programme;
use App\Models\Department;
use App\Models\Faculty;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AcademicController extends Controller
{
    /**
     * Academic structure explorer — the drill-down entry point.
     * Institutions → Faculties → Departments → Programmes → Levels/Semesters → Courses.
     */
    public function index(Request $request): View
    {
        $faculties = Faculty::withCount('departments')->with('organization')->orderBy('name')->get();
        $departments = Department::with(['faculty', 'academicPrograms'])->orderBy('name')->get();
        $programmes = Programme::with('nucDiscipline')->where('status', 'active')->orderBy('name')->get();

        return view('superadmin.academic.index', [
            'faculties' => $faculties,
            'departments' => $departments,
            'programmes' => $programmes,
        ]);
    }

    /**
     * Single programme drill-down: curriculum versions, level→semester→course map,
     * and course readiness.
     */
    public function programme(Programme $programme): View
    {
        $programme->load('nucDiscipline', 'curriculumVersions.academicSession');

        $versions = $programme->curriculumVersions()
            ->with(['academicSession'])
            ->orderByDesc('is_active')
            ->orderByDesc('created_at')
            ->get();

        $activeVersion = $versions->firstWhere('is_active', true) ?? $versions->first();

        $coursesByLevel = collect();
        if ($activeVersion) {
            $coursesByLevel = CurriculumCourse::with('course')
                ->where('curriculum_version_id', $activeVersion->id)
                ->get()
                ->groupBy('level')
                ->map(fn ($levelCourses) => $levelCourses->groupBy('semester'));
        }

        return view('superadmin.academic.programme', [
            'programme' => $programme,
            'versions' => $versions,
            'activeVersion' => $activeVersion,
            'coursesByLevel' => $coursesByLevel,
        ]);
    }

    /**
     * All curriculum versions across programmes (academic structures registry).
     */
    public function structures(Request $request): View
    {
        $query = CurriculumVersion::with(['programme', 'academicSession', 'curriculumCourses']);

        if ($request->filled('programme_id')) {
            $query->where('programme_id', $request->integer('programme_id'));
        }

        if ($request->filled('scope')) {
            $query->where('scope', $request->string('scope'));
        }

        $versions = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();
        $programmes = Programme::orderBy('name')->get();

        return view('superadmin.academic.structures', [
            'versions' => $versions,
            'programmes' => $programmes,
            'filters' => $request->only(['programme_id', 'scope']),
        ]);
    }
}