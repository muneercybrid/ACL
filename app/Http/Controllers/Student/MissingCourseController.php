<?php
namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MissingCourseController extends Controller
{
    public function create(Request $request): View
    {
        return view('student.missing-course-request', [
            'statusOptions' => ['PENDING_REVIEW', 'UNDER_REVIEW', 'APPROVED', 'REJECTED'],
        ]);
    }
}
