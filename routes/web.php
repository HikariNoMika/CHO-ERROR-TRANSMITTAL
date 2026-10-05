<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentGenerationController;
use App\Http\Controllers\PatientRecordController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

// Public so it is reachable both before and after signing in. Static page, so a
// closure is enough - no controller needed.
Route::get('/docs', fn () => view('docs'))->name('docs');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Self-service profile. Inside auth but outside the admin group, so every
    // signed-in user can reach their own details. The controller resolves the
    // record from the session rather than from a route parameter, so there is no
    // user id in the URL for one user to tamper with another's.
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Patient Records (explicit paths must precede the resource so they
    // aren't read as a record id)
    Route::get('records/export', [PatientRecordController::class, 'export'])
        ->name('records.export');

    Route::get('records/error', [PatientRecordController::class, 'index'])
        ->defaults('type', 'error')
        ->name('records.error');

    Route::get('records/success', [PatientRecordController::class, 'index'])
        ->defaults('type', 'success')
        ->name('records.success');

    Route::get('records/mission', [PatientRecordController::class, 'index'])
        ->defaults('type', 'mission')
        ->name('records.mission');

    Route::get('records/error/create', [PatientRecordController::class, 'create'])
        ->defaults('type', 'error')
        ->name('records.error.create');

    Route::get('records/success/create', [PatientRecordController::class, 'create'])
        ->defaults('type', 'success')
        ->name('records.success.create');

    Route::get('records/mission/create', [PatientRecordController::class, 'create'])
        ->defaults('type', 'mission')
        ->name('records.mission.create');

    Route::resource('records', PatientRecordController::class)
        ->names('records')
        ->except(['destroy']);

    Route::delete('records/{record}', [PatientRecordController::class, 'destroy'])
        ->name('records.destroy');

    Route::get('records/{record}/image/{type}', [PatientRecordController::class, 'image'])
        ->name('records.image')
        ->where('type', 'id|error');

    Route::get('records/{record}/print', [DocumentGenerationController::class, 'printView'])
        ->name('records.print');

    Route::get('records/{record}/generate', [DocumentGenerationController::class, 'generate'])
        ->name('records.generate');

    // Export of just the rows ticked in the record table, as one workbook. POST,
    // and placed here so the literal segment is registered before any {record}
    // pattern.
    Route::post('records/bulk-export', [PatientRecordController::class, 'bulkExport'])
        ->name('records.bulk-export');

    Route::get('records/{record}/download', [DocumentGenerationController::class, 'download'])
        ->name('records.download');

    Route::post('records/{record}/mark-printed', [DocumentGenerationController::class, 'markAsPrinted'])
        ->name('records.mark-printed');

    Route::get('generations/{generation}/download', [DocumentGenerationController::class, 'downloadGeneration'])
        ->name('generations.download');

    // Settings (Admin only, includes the single Excel template)
    Route::middleware('role:admin')->group(function () {
        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');

        // User accounts. Admin only; the policy additionally refuses the two
        // changes that would lock everyone out.
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    });
});
