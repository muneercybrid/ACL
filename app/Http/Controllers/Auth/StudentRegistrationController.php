<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\State;
use App\Models\Lga;
use App\Models\StudentRegistrationVerification;
use App\Models\User;
use App\Services\Jamb\JambMatriculationVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
    ): JsonResponse {
        $minimumYear = (int) config('services.jamb.minimum_year', 1995);

        $validated = $request->validate([
            'jamb_exam_year' => [
                'required',
                'integer',
                'min:' . $minimumYear,
                'max:' . now()->year,
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

        // Check if already registered
        $existingVerification = StudentRegistrationVerification::where(
            'jamb_registration_number_hash', $hash
        )->where('status', 'verified')->first();

        if ($existingVerification && $existingVerification->user_id) {
            return response()->json([
                'outcome' => 'already_registered',
                'message' => 'This JAMB registration number has already been used to create an account. Please sign in instead.',
            ], 422);
        }

        $verification = StudentRegistrationVerification::create([
            'token' => (string) Str::uuid(),
            'status' => 'pending',
            'jamb_exam_year' => $validated['jamb_exam_year'],
            'jamb_exam_type' => 'UTME',
            'jamb_exam_value' => null,
            'jamb_registration_number' => $registrationNumber,
            'jamb_registration_number_hash' => $hash,
            'expires_at' => now()->addMinutes(20),
        ]);

        try {
            $result = $jamb->verify(
                (int) $validated['jamb_exam_year'],
                $registrationNumber,
                'UTME'
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

                return response()->json([
                    'outcome' => 'failed',
                    'message' => $result['message']
                        ?? $result['status']
                        ?? 'JAMB could not verify this registration number for the selected examination year. Please confirm the year and registration number and try again.',
                    'redirect' => route('register.student'),
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

            return response()->json([
                'outcome' => 'verified',
                'name' => $result['name'],
                'institution' => $result['institution'],
                'programme' => $result['programme'],
                'redirect' => route('register.student.confirm'),
            ]);
        } catch (Throwable $e) {
            report($e);

            $verification->update([
                'status' => 'failed',
                'jamb_status' => 'Provider error',
                'verification_metadata' => [
                    'error' => $e->getMessage(),
                ],
            ]);

            return response()->json([
                'outcome' => 'error',
                'message' => 'We could not complete JAMB verification right now. Please try again shortly.',
                'redirect' => route('register.student'),
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

    public function completeForm(Request $request): View|RedirectResponse
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

        $states = \App\Models\State::orderBy('name')->get(['id', 'name']);
        $lgas = \App\Models\Lga::select('id', 'name', 'state_id')->get();

        return view('auth.register-complete', [
            'verification' => $verification,
            'states' => $states,
            'lgas' => $lgas,
        ]);
    }

    public function complete(Request $request): RedirectResponse
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

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^[\d\+\-\s]+$/'],
            'nationality' => ['required', 'string', 'max:80'],
            'state_id' => ['required', 'exists:states,id'],
            'lga_id' => ['required', 'exists:lgas,id'],
            'school_registration_number' => ['nullable', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Update the school registration number if provided.
        if (! empty($validated['school_registration_number'])) {
            $verification->update([
                'school_registration_number' => $validated['school_registration_number'],
            ]);
        }

        DB::transaction(function () use ($verification, $validated) {
            $user = User::create([
                'name' => $verification->verified_name,
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'jamb_registration_number_hash' => $verification->jamb_registration_number_hash,
            ]);

            // Assign student role scoped to the institution
            $studentRole = Role::where('slug', 'student')->firstOrFail();
            RoleAssignment::create([
                'user_id' => $user->id,
                'role_id' => $studentRole->id,
                'entity_type' => get_class($verification->organization),
                'entity_id' => $verification->organization->id,
            ]);

            // Create organization membership
            OrganizationMembership::create([
                'organization_id' => $verification->organization->id,
                'user_id' => $user->id,
                'academic_program_id' => $verification->academic_program_id,
                'matric_number' => $verification->school_registration_number,
                'membership_type' => 'student',
                'status' => 'active',
                'joined_at' => now(),
            ]);

            // Create student record with nationality, state, lga
            \App\Models\Student::create([
                'user_id' => $user->id,
                'verification_method' => 'jamb',
                'verification_status' => 'verified',
                'nationality' => $validated['nationality'],
                'state' => $validated['state_id'], // state name
                'lga' => $validated['lga_id'], // lga name
                'region' => \App\Models\Lga::find($validated['lga_id'])?->state->name ?? '',
                'admission_year' => (int) $verification->jamb_exam_year,
            ]);

            $verification->update([
                'user_id' => $user->id,
                'school_registration_number' => $verification->school_registration_number,
            ]);
        });

        $request->session()->forget('student_verification_token');

        return redirect()->route('dashboard')->with('success', 'Welcome! Your student account has been created successfully.');
    }
}