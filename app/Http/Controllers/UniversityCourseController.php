<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use App\Services\UniversityCourseService;
use App\Models\Course;

class UniversityCourseController extends Controller
{
    public function __construct(protected UniversityCourseService $service) {}

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('institution.admin');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:255'],
            'credit_units' => ['nullable', 'integer', 'min:1', 'max:10'],
            'description' => ['nullable', 'string', 'max:2000'],
            'institution_id' => ['nullable', 'integer'],
            'source_url' => ['nullable', 'string', 'url'],
        ]);

        $course = $this->service->createUniversityCourse($validated, $validated['institution_id'] ?? null);

        return redirect()->route('superadmin.institutions.index')
            ->with('success', 'University course submitted: '.$course->code.' — verification status: unverified');
    }
}
