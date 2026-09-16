<?php

use App\Http\Controllers\ACLi\AcliChatController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\StudentRegistrationController;
use App\Http\Controllers\Auth\ExternalLearnerRegistrationController;
use App\Http\Controllers\CourseViewerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\StudentDashboardController;
use Illuminate\Support\Facades\Route;

// Public landing for guests; authenticated users go straight to their dashboard.
Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : view('welcome'))->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);

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

    Route::get('/register/external', [ExternalLearnerRegistrationController::class, 'create'])
        ->name('register.external');



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

    // Student Dashboard
    Route::prefix('student')->name('student.')->group(function () {
        Route::get('/', [StudentDashboardController::class, 'index'])->name('dashboard');
        Route::get('/profile', [StudentDashboardController::class, 'profile'])->name('profile');
        Route::get('/my-courses', [StudentDashboardController::class, 'myCourses'])->name('my-courses');
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