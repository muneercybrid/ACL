<?php

namespace App\Http\Controllers;

use App\Models\Curriculum\NucDiscipline;
use App\Services\CourseVisibilityService;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function __construct(protected CourseVisibilityService $visibility) {}

    /** Catalogue landing = disciplines (faculty groupings), never curriculum courses. */
    public function index(Request $request)
    {
        $disciplines = NucDiscipline::withCount(['courses as external_count' =>
            fn ($q) => $q->where('courses.is_external', true)])
            ->orderBy('name')
            ->get();

        $student = $request->user()?->student;
        $recommended = $student ? $this->visibility->visibleExternalForStudent($student)->take(6) : collect();

        return view('catalog.index', compact('disciplines', 'recommended', 'student'));
    }

    /** Discipline page = programmes (departments) + external courses, filterable. */
    public function discipline(Request $request, NucDiscipline $discipline)
    {
        $courses = $this->visibility->catalogue(
            $discipline->code,
            $request->query('difficulty'),
            $request->query('q')
        );

        $departments = $programmeList = $discipline->programmes()->orderBy('name')->get();

        return view('catalog.discipline', compact('discipline', 'courses', 'departments'));
    }
}
