<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class VerificationConfirmController extends Controller
{
    public function confirm(Request $request, string $email)
    {
        $user = User::where('email', $email)->first();

        if ($user && ! $user->email_verified_at) {
            $user->email_verified_at = now();
            $user->save();
        }

        return view('student.verification-success', [
            'student' => $user ? (object) [
                'user' => $user,
                'level' => $user->level ?? 'N/A',
            ] : null,
        ]);
    }
}
