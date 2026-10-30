<?php

use App\Http\Controllers\AmbassadorListController;
use App\Http\Controllers\DelegateExportController;
use App\Http\Controllers\PaymentScreenshotController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\RolePreviewController;
use App\Http\Controllers\StudyGuideDownloadController;
use App\Http\Controllers\WhatsAppController;
use App\Http\Middleware\RecordPageView;
use App\Models\Committee;
use App\Models\Registration;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome', [
    'committees' => Committee::ordered()->get(),
]))->middleware(RecordPageView::class)->name('home');

Route::view('links', 'links')->middleware(RecordPageView::class)->name('links');

Route::middleware('guest')->group(function () {
    Route::livewire('register', 'pages::registration.create')->middleware(RecordPageView::class)->name('register');
    Route::livewire('staff/join', 'pages::staff.join')->name('staff.join');
});

Route::livewire('status', 'pages::registration.status')->middleware(RecordPageView::class)->name('registration.status');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::get('study-guides/{guide}', StudyGuideDownloadController::class)->name('study-guides.download');

    Route::post('push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
    Route::delete('push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');

    Route::post('view-as', [RolePreviewController::class, 'store'])->name('role-preview.store');
    Route::delete('view-as', [RolePreviewController::class, 'destroy'])->name('role-preview.destroy');
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

    Route::get('whatsapp/contact/{registration}', [WhatsAppController::class, 'contact'])->name('whatsapp.contact');
    Route::get('whatsapp/account/{delegate}', [WhatsAppController::class, 'account'])->name('whatsapp.account');
    Route::get('whatsapp/allocation/{delegate}', [WhatsAppController::class, 'allocation'])->name('whatsapp.allocation');
    Route::get('whatsapp/ambassador/{ambassador}/welcome', [WhatsAppController::class, 'ambassadorWelcome'])->name('whatsapp.ambassador.welcome');
    Route::get('whatsapp/ambassador/{ambassador}/message', [WhatsAppController::class, 'ambassadorMessage'])->name('whatsapp.ambassador.message');

    Route::get('delegates/export', DelegateExportController::class)
        ->can('manage-conference')
        ->name('delegates.export');

    Route::middleware('can:manage-conference')->group(function () {
        Route::livewire('allocations', 'pages::staff.allocations')->name('allocations');
        Route::livewire('committees', 'pages::staff.committees.index')->name('committees');
        Route::livewire('committees/{committee}', 'pages::staff.committees.show')->name('committees.show');
        Route::livewire('guides', 'pages::staff.guides')->name('guides');
        Route::livewire('announcements', 'pages::staff.announcements')->name('announcements');
        Route::livewire('emails', 'pages::staff.emails')->name('emails');
    });

    Route::middleware('can:manage-ambassadors')->group(function () {
        Route::livewire('ambassadors', 'pages::staff.ambassadors.index')->name('ambassadors');
        Route::get('ambassadors/list', AmbassadorListController::class)->name('ambassadors.list');
        Route::livewire('ambassadors/{ambassador}', 'pages::staff.ambassadors.show')->name('ambassadors.show');
    });

    Route::livewire('team', 'pages::staff.team')
        ->can('manage-staff')
        ->name('team');

    Route::livewire('analytics', 'pages::staff.analytics')
        ->can('manage-staff')
        ->name('analytics');
});

require __DIR__.'/settings.php';
