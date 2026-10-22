<?php

use App\Enums\RegistrationStatus;
use App\Models\Delegate;
use App\Models\Registration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('a registrant can look up their status with the reference and contact email', function () {
    $registration = Registration::factory()->create(['contact_email' => 'head@example.com']);

    Livewire::test('pages::registration.status')
        ->set('reference', strtolower($registration->reference))
        ->set('email', 'HEAD@example.com')
        ->call('lookup')
        ->assertHasNoErrors()
        ->assertSet('registrationId', $registration->id)
        ->assertSeeText('Pending verification');
});

test('a delegate in the registration can look up its status with their own email', function () {
    $delegate = Delegate::factory()->create(['email' => 'member@gmail.com']);

    Livewire::test('pages::registration.status')
        ->set('reference', $delegate->registration->reference)
        ->set('email', 'member@gmail.com')
        ->call('lookup')
        ->assertSet('registrationId', $delegate->registration_id);
});

test('a reference with the wrong email is not found', function () {
    $registration = Registration::factory()->create(['contact_email' => 'head@example.com']);

    Livewire::test('pages::registration.status')
        ->set('reference', $registration->reference)
        ->set('email', 'someone-else@example.com')
        ->call('lookup')
        ->assertHasErrors('reference')
        ->assertSet('registrationId', null);
});

test('a rejected registration can upload a new payment screenshot', function () {
    Storage::fake('local');
    $registration = Registration::factory()->rejected('Screenshot is blurry.')->create();
    $oldPath = $registration->payment_screenshot_path;

    Livewire::test('pages::registration.status')
        ->set('reference', $registration->reference)
        ->set('email', $registration->contact_email)
        ->call('lookup')
        ->assertSeeText('Screenshot is blurry.')
        ->set('paymentScreenshot', UploadedFile::fake()->image('new-payment.png'))
        ->call('resubmit')
        ->assertHasNoErrors();

    $registration->refresh();

    expect($registration->status)->toBe(RegistrationStatus::Pending)
        ->and($registration->rejection_reason)->toBeNull()
        ->and($registration->payment_screenshot_path)->not->toBe($oldPath)
        ->and($registration->auditLogs()->value('action'))->toBe('payment_resubmitted');

    Storage::disk('local')->assertExists($registration->payment_screenshot_path);
});

test('a pending registration cannot replace its payment screenshot', function () {
    Storage::fake('local');
    $registration = Registration::factory()->create();

    Livewire::test('pages::registration.status')
        ->set('reference', $registration->reference)
        ->set('email', $registration->contact_email)
        ->call('lookup')
        ->set('paymentScreenshot', UploadedFile::fake()->image('new-payment.png'))
        ->call('resubmit')
        ->assertForbidden();
});
