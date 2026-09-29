<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Superadmin\InstitutionController;
use App\Http\Controllers\Institution\DashboardController;

// Institution discovery (public/guest view — no admin access)
Route::get('/institutions', [InstitutionController::class, 'index'])->name('institutions.index');
Route::get('/institutions/{institution:slug}', [InstitutionController::class, 'show'])->name('institutions.show');

// Institution portal — admin-scoped
Route::middleware(['auth', 'institution_admin'])->prefix('institution')->name('institution.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/staff', [DashboardController::class, 'staff'])->name('staff');
    Route::get('/curriculum', [DashboardController::class, 'curriculum'])->name('curriculum');
    Route::post('/onboarding/claim', [DashboardController::class, 'claim'])->name('claim');
    Route::post('/staff/invite', [DashboardController::class, 'inviteStaff'])->name('staff.invite');
    Route::post('/staff/{staff}/reset-password', [DashboardController::class, 'resetStaffPassword'])->name('staff.reset');
});
