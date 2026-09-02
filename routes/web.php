<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CourseViewerController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    
    // Course Viewer
    Route::get('/courses/{courseOffering}', [CourseViewerController::class, 'show'])->name('courses.show');
    // scopeBindings() resolves {lesson} through {courseOffering}, so a lesson
    // from another offering 404s before the controller runs.
    Route::get('/courses/{courseOffering}/lessons/{lesson}', [CourseViewerController::class, 'showLesson'])->name('courses.lessons.show')->scopeBindings();
    Route::post('/lessons/{lesson}/complete', [CourseViewerController::class, 'completeLesson'])->name('lessons.complete');
});
