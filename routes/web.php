<?php

use App\Http\Controllers\DelegateExportController;
use App\Http\Controllers\PaymentScreenshotController;
use App\Http\Controllers\StudyGuideDownloadController;
use App\Models\Committee;
use App\Models\Registration;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome', [
    'committees' => Committee::ordered()->get(),
]))->name('home');

Route::middleware('guest')->group(function () {
    Route::livewire('register', 'pages::registration.create')->name('register');
    Route::livewire('staff/join', 'pages::staff.join')->name('staff.join');
});

Route::livewire('status', 'pages::registration.status')->name('registration.status');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::get('study-guides/{guide}', StudyGuideDownloadController::class)->name('study-guides.download');
});

Route::middleware(['auth', 'verified'])->prefix('staff')->name('staff.')->group(function () {
    Route::livewire('registrations', 'pages::staff.registrations.index')
        ->can('viewAny', Registration::class)
        ->name('registrations.index');

    Route::livewire('registrations/{registration}', 'pages::staff.registrations.show')
        ->can('view', 'registration')
        ->name('registrations.show');

    Route::get('registrations/{registration}/payment-screenshot', PaymentScreenshotController::class)
        ->name('registrations.payment-screenshot');

    Route::get('delegates/export', DelegateExportController::class)
        ->can('manage-conference')
        ->name('delegates.export');

    Route::middleware('can:manage-conference')->group(function () {
        Route::livewire('allocations', 'pages::staff.allocations')->name('allocations');
        Route::livewire('committees', 'pages::staff.committees')->name('committees');
        Route::livewire('guides', 'pages::staff.guides')->name('guides');
        Route::livewire('announcements', 'pages::staff.announcements')->name('announcements');
    });

    Route::livewire('team', 'pages::staff.team')
        ->can('manage-staff')
        ->name('team');
});

require __DIR__.'/settings.php';
