<?php

use App\Enums\RegistrationStatus;
use App\Models\Delegate;
use App\Models\Registration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('a registration can be found by any one detail', function (string $identifier) {
    $registration = Registration::factory()->create(['contact_email' => 'head@example.com', 'contact_phone' => '0300 1234567']);
    Delegate::factory()->for($registration)->create(['email' => 'member@gmail.com']);

    Livewire::test('pages::registration.status')
        ->set('identifier', str_replace('{ref}', $registration->reference, $identifier))
        ->call('lookup')
        ->assertHasNoErrors()
        ->assertSet('registrationId', $registration->id)
        ->assertSeeText('Pending verification');
})->with([
    'reference' => ['{ref}'],
    'lowercase reference' => [' hb26-0001 '],
    'contact email' => ['HEAD@example.com'],
    'delegate email' => ['member@gmail.com'],
    'phone as typed' => ['03001234567'],
    'phone with country code' => ['+92 300-1234567'],
]);

test('an unknown detail is not found', function (string $identifier) {
    Registration::factory()->create(['contact_email' => 'head@example.com', 'contact_phone' => '03001234567']);

    Livewire::test('pages::registration.status')
        ->set('identifier', $identifier)
        ->call('lookup')
        ->assertHasErrors('identifier')
        ->assertSet('registrationId', null);
})->with(['someone@example.com', 'HB26-9999', '03119999999', 'hello']);

test('the newest active registration is shown when a detail matches several', function () {
    Registration::factory()->rejected()->create(['contact_email' => 'head@example.com']);
    $current = Registration::factory()->create(['contact_email' => 'head@example.com']);

    Livewire::test('pages::registration.status')
        ->set('identifier', 'head@example.com')
        ->call('lookup')
        ->assertSet('registrationId', $current->id);
});

test('a rejected registration can upload a new screenshot after confirming a second detail', function () {
    Storage::fake('local');
    $registration = Registration::factory()->rejected('Screenshot is blurry.')->create(['contact_phone' => '03001234567']);
    $oldPath = $registration->payment_screenshot_path;

    $component = Livewire::test('pages::registration.status')
        ->set('identifier', $registration->reference)
        ->call('lookup')
        ->assertSeeText('Screenshot is blurry.')
        ->set('paymentScreenshot', UploadedFile::fake()->image('new-payment.png'));

    $component->set('confirmation', 'wrong@example.com')->call('resubmit')->assertHasErrors('confirmation');
    $component->set('confirmation', $registration->reference)->call('resubmit')->assertHasErrors('confirmation');

    expect($registration->refresh()->status)->toBe(RegistrationStatus::Rejected);

    $component->set('confirmation', '0300 1234567')->call('resubmit')->assertHasNoErrors();

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
        ->set('identifier', $registration->contact_email)
        ->call('lookup')
        ->set('confirmation', $registration->reference)
        ->set('paymentScreenshot', UploadedFile::fake()->image('new-payment.png'))
        ->call('resubmit')
        ->assertForbidden();
});
