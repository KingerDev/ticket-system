<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\RegistrationController;

Route::get('/', function () {
    return redirect()->route('register');
});

Route::get('/register', [RegistrationController::class, 'create'])->name('register');
Route::post('/register', [RegistrationController::class, 'store'])->name('register.store');
Route::get('/register/success', [RegistrationController::class, 'success'])->name('register.success');

Route::middleware(['auth', 'verified'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');
    
    // Registrations
    Route::get('/registrations', [\App\Http\Controllers\Admin\RegistrationAdminController::class, 'index'])->name('admin.registrations.index');
    Route::get('/registrations/{id}', [\App\Http\Controllers\Admin\RegistrationAdminController::class, 'show'])->name('admin.registrations.show');
    Route::patch('/registrations/{id}/contact', [\App\Http\Controllers\Admin\RegistrationAdminController::class, 'updateContact'])->name('admin.registrations.update_contact');
    Route::patch('/guests/{id}', [\App\Http\Controllers\Admin\RegistrationAdminController::class, 'updateGuest'])->name('admin.guests.update');
    Route::delete('/guests/{id}', [\App\Http\Controllers\Admin\RegistrationAdminController::class, 'destroyGuest'])->name('admin.guests.destroy');
    Route::post('/registrations/{id}/assign-seat', [\App\Http\Controllers\Admin\RegistrationAdminController::class, 'assignSeat'])->name('admin.registrations.assign');
    Route::post('/guests/{id}/toggle-paid', [\App\Http\Controllers\Admin\RegistrationAdminController::class, 'togglePaid'])->name('admin.guests.toggle_paid');
    Route::post('/guests/{id}/issue-ticket', [\App\Http\Controllers\Admin\RegistrationAdminController::class, 'issueTicket'])->name('admin.guests.issue_ticket');

    // Seating guide
    Route::get('/seating', [\App\Http\Controllers\Admin\SeatingController::class, 'index'])->name('admin.seating');
    Route::get('/seating/lookup', [\App\Http\Controllers\Admin\SeatingController::class, 'lookup'])->name('admin.seating.lookup');
    Route::post('/seating/check-in', [\App\Http\Controllers\Admin\SeatingController::class, 'checkIn'])->name('admin.seating.check_in');

    // Export
    Route::get('/export', [\App\Http\Controllers\Admin\ExportController::class, 'index'])->name('admin.export');
    Route::get('/export/download', [\App\Http\Controllers\Admin\ExportController::class, 'export'])->name('admin.export.download');

    // Check-in
    Route::get('/checkin', [\App\Http\Controllers\Admin\CheckInController::class, 'index'])->name('admin.checkin');
    Route::post('/checkin', [\App\Http\Controllers\Admin\CheckInController::class, 'store'])->name('admin.checkin.store');

    // Používatelia a auditný log – len pre super administrátora
    Route::middleware('super_admin')->group(function () {
        Route::get('/users', [\App\Http\Controllers\Admin\UserController::class, 'index'])->name('admin.users.index');
        Route::post('/users', [\App\Http\Controllers\Admin\UserController::class, 'store'])->name('admin.users.store');
        Route::patch('/users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'update'])->name('admin.users.update');
        Route::delete('/users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'destroy'])->name('admin.users.destroy');

        Route::get('/activity-log', [\App\Http\Controllers\Admin\ActivityLogController::class, 'index'])->name('admin.activity_log');

        // Rozloženie sály mení stoly všetkým naraz, preto len super admin.
        Route::get('/hall', [\App\Http\Controllers\Admin\HallConfigController::class, 'edit'])->name('admin.hall.edit');
        Route::post('/hall', [\App\Http\Controllers\Admin\HallConfigController::class, 'update'])->name('admin.hall.update');
    });

    // Hall Config & Tables Map
    Route::get('/tables/map', [\App\Http\Controllers\Admin\HallConfigController::class, 'map'])->name('admin.tables.map');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

require __DIR__.'/auth.php';
