<?php

use App\Http\Controllers\PaymentScreenshotController;
use App\Models\Committee;
use App\Models\Registration;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome', [
    'committees' => Committee::ordered()->get(),
]))->name('home');

Route::middleware('guest')->group(function () {
    Route::livewire('register', 'pages::registration.create')->name('register');
});

Route::livewire('status', 'pages::registration.status')->name('registration.status');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
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
});

require __DIR__.'/settings.php';
