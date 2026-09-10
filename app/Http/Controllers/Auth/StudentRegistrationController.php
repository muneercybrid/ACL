<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\StudentRegistrationVerification;
use App\Services\Jamb\JambMatriculationVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class StudentRegistrationController extends Controller
{
    public function create(Request $request): View
    {
        $minimumYear = (int) config('services.jamb.minimum_year', 1995);
        $maximumYear = now()->year;
        $years = range($maximumYear, $minimumYear);

        return view('auth.register-student', [
            'years' => $years,
            'selectedYear' => old(
                'jamb_exam_year',
                $request->session()->get('student_verification_year')
            ),
        ]);
    }

    public function verify(
        Request $request,
        JambMatriculationVerificationService $jamb
    ): RedirectResponse {
        $minimumYear = (int) config('services.jamb.minimum_year', 1995);

        $validated = $request->validate([
            'jamb_exam_year' => [
                'required',
                'integer',
                'min:' . $minimumYear,
                'max:' . now()->year,
            ],
            'jamb_exam_type' => [
                'required',
                'string',
                'in:UTME',
            ],
            'jamb_registration_number' => [
                'required',
                'string',
                'max:15',
                'regex:/^[A-Za-z0-9]+$/',
            ],
        ]);

        $registrationNumber = strtoupper(
            trim($validated['jamb_registration_number'])
        );

        $hash = hash('sha256', $registrationNumber);

        $verification = StudentRegistrationVerification::create([
            'token' => (string) Str::uuid(),
            'status' => 'pending',
            'jamb_exam_year' => $validated['jamb_exam_year'],
            'jamb_exam_type' => $validated['jamb_exam_type'],
            'jamb_exam_value' => null,
            'jamb_registration_number' => $registrationNumber,
            'jamb_registration_number_hash' => $hash,
            'expires_at' => now()->addMinutes(20),
        ]);

        try {
            $result = $jamb->verify(
                (int) $validated['jamb_exam_year'],
                $registrationNumber,
                $validated['jamb_exam_type']
            );

            $metadata = [
                'provider_status_code' => $result['provider_status_code'] ?? null,
                'provider_url' => $result['provider_url'] ?? null,
                'actions' => $result['actions'] ?? [],
                'raw_text' => $result['raw_text'] ?? null,
            ];

            if (! $result['verified']) {
                $verification->update([
                    'status' => 'failed',
                    'jamb_exam_value' => $result['jamb_exam_value'] ?? null,
                    'jamb_status' => $result['status'] ?? 'Verification failed.',
                    'verification_metadata' => $metadata,
                ]);

                return back()
                    ->withInput()
                    ->withErrors([
                        'jamb_registration_number' =>
                            'JAMB could not verify this registration number for the selected examination year. Please confirm the year and registration number and try again.',
                    ]);
            }

            $verification->update([
                'status' => 'verified',
                'jamb_exam_value' => $result['jamb_exam_value'],
                'verified_name' => $result['name'],
                'verified_institution' => $result['institution'],
                'verified_programme' => $result['programme'],
                'jamb_status' => $result['status'] ?? 'verified',
                'verification_metadata' => $metadata,
                'verified_at' => now(),
            ]);

            $request->session()->put(
                'student_verification_token',
                $verification->token
            );

            $request->session()->forget([
                'student_verification_year',
                'student_verification_result',
            ]);

            return redirect()->route('register.student.confirm');
        } catch (Throwable $e) {
            report($e);

            $verification->update([
                'status' => 'failed',
                'jamb_status' => 'Provider error',
                'verification_metadata' => [
                    'error' => $e->getMessage(),
                ],
            ]);

            return back()
                ->withInput()
                ->withErrors([
                    'jamb_registration_number' =>
                        'We could not complete JAMB verification right now. Please try again shortly.',
                ]);
        }
    }

    public function confirm(Request $request): View|RedirectResponse
    {
        $token = $request->session()->get('student_verification_token');

        if (! $token) {
            return redirect()
                ->route('register.student')
                ->withErrors([
                    'jamb_registration_number' =>
                        'Please begin the student registration process again.',
                ]);
        }

        $verification = StudentRegistrationVerification::where('token', $token)
            ->where('status', 'verified')
            ->first();

        if (! $verification || ! $verification->isUsable()) {
            $request->session()->forget('student_verification_token');

            return redirect()
                ->route('register.student')
                ->withErrors([
                    'jamb_registration_number' =>
                        'Your JAMB verification has expired. Please verify again.',
                ]);
        }

        return view('auth.register-student-confirm', [
            'verification' => $verification,
        ]);
    }

    public function continueToSchoolRegistration(
        Request $request
    ): RedirectResponse {
        $token = $request->session()->get('student_verification_token');

        if (! $token) {
            return redirect()
                ->route('register.student')
                ->withErrors([
                    'jamb_registration_number' =>
                        'Please begin the student registration process again.',
                ]);
        }

        $verification = StudentRegistrationVerification::where('token', $token)
            ->where('status', 'verified')
            ->first();

        if (! $verification || ! $verification->isUsable()) {
            $request->session()->forget('student_verification_token');

            return redirect()
                ->route('register.student')
                ->withErrors([
                    'jamb_registration_number' =>
                        'Your JAMB verification has expired. Please verify again.',
                ]);
        }

        return redirect()->route('register.student.school');
    }
}
