<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();

        $enrollments = $user->enrollments()
            ->with(['courseOffering.course', 'courseOffering.semester'])
            ->orderByDesc('enrolled_at')
            ->get();

        return view('dashboard', [
            'enrollments' => $enrollments,
        ]);
    }
}
