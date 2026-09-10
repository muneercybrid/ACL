<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;

class ExternalLearnerRegistrationController extends Controller
{
    /**
     * Show external learner registration.
     */
    public function create()
    {
        return view('auth.register-external');
    }
}
