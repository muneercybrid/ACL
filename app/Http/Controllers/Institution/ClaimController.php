<?php
namespace App\Http\Controllers\Institution;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use App\Models\Institution;
use Illuminate\Support\Facades\Auth;
class ClaimController
{
    public function claim(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $institution = $user->institution ?? Institution::where('slug', $request->institution)->first();
        if (! $institution) { abort(403, 'Institution not found'); }
        if ($user->force_password_change) {
            return redirect()->route('institution.profile');
        }
        $institution->update(['onboarding_status' => 'ONBOARDED']);
        return redirect()->route('institution.dashboard');
    }
}
