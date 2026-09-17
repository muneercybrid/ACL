<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceEditProfile
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check()) {
            $user = auth()->user();
            $roles = $user->roles ? $user->roles()->get() : collect();
            $institutionRoles = $roles->filter(fn($r) => str_contains($r->slug, 'institution.'));
            if ($institutionRoles->isNotEmpty()) {
                // Force edit if using default password (check via session or profile edited flag)
                if (! session('profile_edited')) {
                    if ($request->route()->getName() !== 'institution.profile.edit') {
                        session()->put('force_edit', true);
                        return redirect()->route('institution.profile.edit');
                    }
                }
            }
        }
        return $next($request);
    }
}