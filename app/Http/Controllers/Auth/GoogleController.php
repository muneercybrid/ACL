<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\StudentRegistrationVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class GoogleController extends Controller
{
    /**
     * Redirect the user to Google's OAuth consent screen.
     */
    public function redirect(): RedirectResponse
    {
        $params = [
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google.redirect'),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'access_type' => 'offline',
            'prompt' => 'select_account',
            'state' => Str::random(40),
        ];

        return redirect('https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params));
    }

    /**
     * Handle the callback from Google after user consent.
     */
    public function callback(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        try {
            // Exchange authorization code for tokens.
            $tokenResponse = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'code' => $request->input('code'),
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri' => config('services.google.redirect'),
                'grant_type' => 'authorization_code',
            ]);

            if (! $tokenResponse->successful()) {
                return redirect()->route('login')
                    ->withErrors(['google' => 'Google authentication failed. Please try again.']);
            }

            $accessToken = $tokenResponse->json('access_token');

            // Fetch user info from Google.
            $userInfoResponse = Http::withToken($accessToken)
                ->get('https://www.googleapis.com/oauth2/v2/userinfo');

            if (! $userInfoResponse->successful()) {
                return redirect()->route('login')
                    ->withErrors(['google' => 'Could not retrieve your Google profile. Please try again.']);
            }

            $googleUser = $userInfoResponse->json();

            $email = $googleUser['email'] ?? null;

            if (! $email) {
                return redirect()->route('login')
                    ->withErrors(['google' => 'Google did not provide an email address.']);
            }

            // Find or create user by email.
            $user = User::where('email', $email)->first();

            if ($user) {
                // Link Google provider if not already linked.
                if (! $user->provider) {
                    $user->update([
                        'provider' => 'google',
                        'provider_id' => $googleUser['id'] ?? null,
                    ]);
                }
            } else {
                // Create a new user.
                $user = User::create([
                    'name' => $googleUser['name'] ?? $email,
                    'email' => $email,
                    'password' => null,
                    'provider' => 'google',
                    'provider_id' => $googleUser['id'] ?? null,
                    'email_verified_at' => now(),
                ]);
            }

            // Check if student is registered via JAMB verification.
            $verification = StudentRegistrationVerification::where('user_id', $user->id)
                ->where('status', 'verified')
                ->first();

            Auth::login($user, true);

            if ($verification) {
                return redirect()->route('dashboard')
                    ->with('success', 'Welcome back, ' . $user->name . '!');
            }

            // New user without verification — redirect to student registration
            // to complete their JAMB verification and school details.
            return redirect()->route('register.student')
                ->with('info', 'Please complete your student verification to access courses.');

        } catch (Throwable $e) {
            report($e);

            return redirect()->route('login')
                ->withErrors(['google' => 'Google sign-in encountered an error. Please try again.']);
        }
    }
}