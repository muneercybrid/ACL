<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;

class RegisterController extends Controller
{
    /**
     * Show ACL registration pathway selection.
     */
    public function create()
    {
        return view('auth.register');
    }
}
