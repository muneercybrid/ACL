<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class DashboardController extends Controller
{
    /**
     * The /dashboard URL now serves the dedicated student dashboard.
     *
     * The previous placeholder dashboard (with "coming soon" stat cards)
     * is replaced by the data-driven student experience: categorized
     * profile sections and programme curriculum courses.
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('student.dashboard');
    }
}