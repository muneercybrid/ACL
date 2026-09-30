<?php

use App\Http\Controllers\ACLi\AcliChatController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\SetPasswordController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\Auth\StudentRegistrationController;
use App\Http\Controllers\CourseViewerController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\StudentDashboardController;
use App\Http\Controllers\Student;
use App\Http\Controllers\Superadmin;
use Illuminate\Support\Facades\Route;

// Public landing for guests; authenticated users go straight to their dashboard.
Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : view('welcome'))->name('home');

// Public contact form.
//
// Rate limited because it sends mail on behalf of an unauthenticated visitor:
// without a limit one person can use it to send the owner arbitrary amounts of
// mail, or to mail someone else through the server. The POST is throttled
// harder than a normal form because each hit costs an outbound SMTP call.
Route::get('/contact', [ContactController::class, 'create'])->name('contact.create');
// Email confirmation. The link is signed and expiring (see VerificationSuccessMail),
// so a hand-edited URL cannot confirm somebody else's address, and a name-derived
// URL that matches no account is rejected instead of rendering an error.
Route::get('/verify-email-confirm/{user}', [\App\Http\Controllers\VerificationConfirmController::class, 'confirm'])
    ->middleware('signed')
    ->name('email.verify.confirm');
Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('contact.store');

// Professional catalogue — browsable by guests. The controller reads the
// student relation optionally, so recommendations only appear when signed in.
Route::get('/catalogue', [CatalogController::class, 'index'])->name('catalogue');
Route::get('/catalogue/{discipline}', [CatalogController::class, 'discipline'])->name('catalogue.discipline');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);

    // The organizational door. Staff choose their institution first and are then
    // shown that institution's logo above a credentials box. There is no sign-up
    // here on purpose: staff accounts are issued by ACL, not self-registered.
    Route::prefix('organizations')->name('organizations.')->group(function () {
        Route::get('/login', [\App\Http\Controllers\Auth\OrganizationLoginController::class, 'select'])->name('login');
        Route::get('/login/{organization}', [\App\Http\Controllers\Auth\OrganizationLoginController::class, 'show'])->name('login.show');
        Route::post('/login/{organization}', [\App\Http\Controllers\Auth\OrganizationLoginController::class, 'store'])->name('login.store');
    });

    Route::get('/register', [RegisterController::class, 'create'])
        ->name('register');

    Route::get('/register/student', [StudentRegistrationController::class, 'create'])
        ->name('register.student');

    Route::post('/register/student/verify', [StudentRegistrationController::class, 'verify'])
        ->name('register.student.verify');

    Route::get('/register/student/confirm', [StudentRegistrationController::class, 'confirm'])
        ->name('register.student.confirm');

    Route::post('/register/student/confirm', [StudentRegistrationController::class, 'continueToSchoolRegistration'])
        ->name('register.student.confirm.continue');

    Route::get('/register/student/school', [StudentRegistrationController::class, 'completeForm'])
        ->name('register.student.school');

    Route::post('/register/student/complete', [StudentRegistrationController::class, 'complete'])
        ->name('register.student.complete');

    /*
     * External learner registration is suppressed while ACL focuses on
     * university students.
     *
     * The endpoint answers 410 Gone rather than being unregistered. A deleted
     * route returns 404, which reads as a broken link and invites a support
     * ticket; 410 states correctly that the capability was retired and can
     * return. The controller and view are kept for the same reason, and
     * re-enabling is just removing this closure and restoring two view links.
     */
    Route::get('/register/external', function () {
        abort(410, 'External learner registration is currently closed.');
    })->name('register.external');



    // Google OAuth
    Route::get('/auth/google/redirect', [GoogleController::class, 'redirect'])->name('google.redirect');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('google.callback');

    Route::get('/forgot-password', [PasswordResetController::class, 'create'])
        ->name('password.request');

    Route::post('/forgot-password', [PasswordResetController::class, 'store'])
        ->name('password.email');

    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])
        ->name('password.reset');

    Route::post('/reset-password', [PasswordResetController::class, 'update'])
        ->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Level coordinator area. Scoped to the levels this account is appointed
    // to, read from level_coordinators on every request.
    Route::prefix('coordinator')->name('coordinator.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Coordinator\DashboardController::class, 'index'])->name('dashboard');
    });

    // Institution administrator area. One per organization the account
    // administers, from its own scoped assignments.
    Route::prefix('institution')->name('institution.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Institution\DashboardController::class, 'index'])->name('dashboard');
    });

    // Student Dashboard
    Route::prefix('student')->name('student.')->group(function () {
        Route::get('/', [StudentDashboardController::class, 'index'])->name('dashboard');
        Route::get('/profile', [StudentDashboardController::class, 'profile'])->name('profile');
        Route::get('/my-courses', [StudentDashboardController::class, 'myCourses'])->name('my-courses');

        // Re-issue the confirmation link. POST only, throttled, so the button
        // on the dashboard cannot be turned into a mail cannon.
        Route::post('/email/resend', [EmailVerificationController::class, 'resend'])
            ->middleware('throttle:5,1')
            ->name('email.resend');

        // The student is always resolved from the authenticated session, never
        // from a route parameter, so one student can never read another's data.
        // Declared before {curriculumCourse} so the literal path wins.
        Route::get('/course/missing-request', [Student\MissingCourseController::class, 'create'])->name('missing-course.create');
        Route::get('/course-register', [Student\CourseRegistrationController::class, 'index'])->name('course.register');
        // Declared after the GET on the same path. The order is intentional:
        // the flow is server-enforced, so the POST is the only way a
        // selection is recorded and the GET never mutates anything.
        Route::post('/course-register', [Student\CourseRegistrationController::class, 'store'])->name('course.register.store');
        Route::get('/course/{curriculumCourse}', [StudentDashboardController::class, 'showCourse'])->name('course.show');
    });

    // Course Viewer
    Route::get('/courses/{courseOffering}', [CourseViewerController::class, 'show'])->name('courses.show');

    // scopeBindings() resolves {lesson} through {courseOffering}, so a lesson
    // from another offering 404s before the controller runs.
    Route::get('/courses/{courseOffering}/lessons/{lesson}', [CourseViewerController::class, 'showLesson'])
        ->name('courses.lessons.show')
        ->scopeBindings();

    Route::post('/lessons/{lesson}/complete', [CourseViewerController::class, 'completeLesson'])
        ->name('lessons.complete');

    // ACLi - AI Chat
    Route::prefix('acli')->name('acli.')->group(function () {
        Route::get('/chat', [AcliChatController::class, 'page'])->name('chat');
        Route::post('/chat', [AcliChatController::class, 'send'])->name('chat.send');
        Route::get('/conversations', [AcliChatController::class, 'conversations'])->name('conversations');
        Route::get('/conversations/{conversation}', [AcliChatController::class, 'conversation'])->name('conversation.show');
        Route::post('/conversations', [AcliChatController::class, 'newConversation'])->name('conversation.new');
        Route::delete('/conversations/{conversation}', [AcliChatController::class, 'closeConversation'])->name('conversation.close');
    });
});

// ─── Superadmin Command Center ───────────────────────────────────────────────
// Platform-wide operational interface. Every route independently enforces the
// superadmin role — relying on hidden links is never a security control.
Route::middleware(['auth', 'superadmin'])->prefix('superadmin')->name('superadmin.')->group(function () {
    // Command Center
    Route::get('/', [Superadmin\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/activity', [Superadmin\DashboardController::class, 'activityFeed'])->name('activity');

    Route::post('/institution-courses', [\App\Http\Controllers\UniversityCourseController::class, 'store'])->name('institution.courses.store');
    // Institutions
    Route::get('/institutions', [Superadmin\InstitutionController::class, 'index'])->name('institutions.index');
    Route::get('/institutions/create', [Superadmin\InstitutionController::class, 'create'])->name('institutions.create');
    Route::post('/institutions', [Superadmin\InstitutionController::class, 'store'])->name('institutions.store');
    Route::get('/institutions/{organization}', [Superadmin\InstitutionController::class, 'show'])->name('institutions.show');
    Route::get('/institutions/{organization}/edit', [Superadmin\InstitutionController::class, 'edit'])->name('institutions.edit');
    Route::put('/institutions/{organization}', [Superadmin\InstitutionController::class, 'update'])->name('institutions.update');
    Route::post('/institutions/{organization}/toggle', [Superadmin\InstitutionController::class, 'toggleActive'])->name('institutions.toggle');
    Route::post('/institutions/{organization}/assign-admin', [Superadmin\InstitutionController::class, 'assignAdmin'])->name('institutions.assign-admin');

    // Onboarding
    Route::get('/onboarding', [Superadmin\OnboardingController::class, 'index'])->name('onboarding');
    Route::get('/onboarding/{organization}', [Superadmin\OnboardingController::class, 'show'])->name('onboarding.show');

    // Staff
    Route::get('/staff', [Superadmin\StaffController::class, 'index'])->name('staff');
    Route::post('/staff/invite', [Superadmin\StaffController::class, 'invite'])->name('staff.invite');
    Route::post('/staff/{invitation}/revoke', [Superadmin\StaffController::class, 'revokeInvitation'])->name('staff.invitation.revoke');

    // Users
    Route::get('/users', [Superadmin\UserController::class, 'index'])->name('users');
    Route::get('/users/{user}', [Superadmin\UserController::class, 'show'])->name('users.show');
    Route::post('/users/{user}/toggle', [Superadmin\UserController::class, 'toggleActive'])->name('users.toggle');
    Route::post('/users/{user}/roles', [Superadmin\UserController::class, 'updateRoles'])->name('users.roles.update');

    // Academic structure
    Route::get('/academic', [Superadmin\AcademicController::class, 'index'])->name('academic');
    Route::get('/academic/programmes/{programme}', [Superadmin\AcademicController::class, 'programme'])->name('academic.programme');
    Route::get('/academic/structures', [Superadmin\AcademicController::class, 'structures'])->name('academic.structures');

    // Audit
    Route::get('/audit', [Superadmin\AuditController::class, 'index'])->name('audit');
    Route::get('/audit/{log}', [Superadmin\AuditController::class, 'show'])->name('audit.show');

    // Security & auth observability
    Route::get('/security', [Superadmin\SecurityController::class, 'index'])->name('security');

    // Roles & permissions
    Route::get('/roles', [Superadmin\RoleController::class, 'index'])->name('roles');
    Route::get('/roles/{role}', [Superadmin\RoleController::class, 'show'])->name('roles.show');

    // JAMB / registration monitoring
    Route::get('/jamb', [Superadmin\JambController::class, 'index'])->name('jamb');
    Route::get('/registrations', [Superadmin\JambController::class, 'registrations'])->name('registrations');

    // Alerts
    Route::get('/alerts', [Superadmin\AlertController::class, 'index'])->name('alerts');
    Route::post('/alerts/{alert}/acknowledge', [Superadmin\AlertController::class, 'acknowledge'])->name('alerts.acknowledge');
    Route::post('/alerts/{alert}/resolve', [Superadmin\AlertController::class, 'resolve'])->name('alerts.resolve');

    // Reports
    Route::get('/reports', [Superadmin\ReportController::class, 'index'])->name('reports');
    Route::get('/reports/export/{type}', [Superadmin\ReportController::class, 'export'])->name('reports.export');

    // AI activity
    Route::get('/ai', [Superadmin\AiController::class, 'index'])->name('ai');

    // System
    Route::get('/system', [Superadmin\SystemController::class, 'index'])->name('system');
    Route::get('/system/jobs', [Superadmin\SystemController::class, 'jobs'])->name('system.jobs');

    // Global search
    Route::get('/search', [Superadmin\SearchController::class, 'index'])->name('search');
});

/*
 * Setting a password of the user's own choosing.
 *
 * Institution administrators are provisioned with a generated credential and
 * flagged force_password_change. Without these routes the flag was
 * unactionable: the only password flow in ACL was forgot-password, which needs
 * a mailbox the platform does not control, so a provisioned administrator
 * could never replace the generated password.
 *
 * auth is required — the user is changing their own credential, not resetting
 * a forgotten one.
 */
Route::middleware('auth')->group(function () {
    Route::get('/set-password', [SetPasswordController::class, 'create'])->name('password.set');
    Route::post('/set-password', [SetPasswordController::class, 'store'])->name('password.set.store');
});
