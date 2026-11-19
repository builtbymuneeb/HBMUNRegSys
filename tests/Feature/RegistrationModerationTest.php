<?php

use App\Actions\Registrations\ModerateRegistration;
use App\Enums\RegistrationStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Delegate;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * A verified private delegate whose delegate has a login.
 *
 * @return array{0: Registration, 1: User}
 */
function verifiedDelegateWithLogin(): array
{
    $registration = Registration::factory()->privateDelegate()->verified()->create();
    $user = User::factory()->create();
    $registration->delegates()->first()->update(['user_id' => $user->id]);

    return [$registration->fresh(), $user];
}

function registrationsStaff(): User
{
    return User::factory()->role(UserRole::Registrations)->create();
}

test('staff can block a registration and its delegate is sent to the on-hold page', function () {
    [$registration, $delegate] = verifiedDelegateWithLogin();

    Livewire::actingAs(registrationsStaff())
        ->test('pages::staff.registrations.show', ['registration' => $registration])
        ->call('startModeration', 'blocked')
        ->set('moderationReason', 'Screenshot is a blank image.')
        ->call('moderate')
        ->assertHasNoErrors();

    $registration->refresh();
    expect($registration->status)->toBe(RegistrationStatus::Blocked)
        ->and($registration->status_before_moderation)->toBe('verified')
        ->and($registration->moderation_reason)->toBe('Screenshot is a blank image.')
        ->and(AuditLog::where('action', 'blocked')->exists())->toBeTrue();

    $this->actingAs($delegate)->get(route('dashboard'))->assertRedirect(route('account.restricted'));
    $this->actingAs($delegate)->get(route('account.restricted'))->assertOk()->assertSee('Screenshot is a blank image.');
});

test('a reason is required to block, archive or bin', function () {
    $registration = Registration::factory()->privateDelegate()->create();

    Livewire::actingAs(registrationsStaff())
        ->test('pages::staff.registrations.show', ['registration' => $registration])
        ->call('startModeration', 'archived')
        ->call('moderate')
        ->assertHasErrors('moderationReason');

    expect($registration->fresh()->status)->toBe(RegistrationStatus::Pending);
});

test('only the registrations team and admins can moderate registrations', function (UserRole $role) {
    $registration = Registration::factory()->privateDelegate()->create();

    Livewire::actingAs(User::factory()->role($role)->create())
        ->test('pages::staff.registrations.show', ['registration' => $registration])
        ->call('startModeration', 'blocked')
        ->assertForbidden();

    $this->actingAs(User::factory()->role($role)->create())
        ->get(route('staff.registrations.show', $registration))
        ->assertForbidden();

    expect($registration->fresh()->status)->toBe(RegistrationStatus::Pending);
})->with([UserRole::Ambassadors, UserRole::OtherManagement, UserRole::Delegate]);

test('a blocked delegate can appeal, and staff restoring it lets them back in', function () {
    [$registration, $delegate] = verifiedDelegateWithLogin();
    $staff = registrationsStaff();
    app(ModerateRegistration::class)->moderate($registration, $staff, RegistrationStatus::Blocked, 'Fake receipt.');

    Livewire::actingAs($delegate)
        ->test('pages::account.restricted')
        ->set('message', 'I paid on 12 Nov from my father’s account, here is the TID.')
        ->call('appeal')
        ->assertHasNoErrors();

    expect($registration->fresh()->hasOpenAppeal())->toBeTrue();

    $this->actingAs($staff)->get(route('staff.registrations.index', ['tag' => 'appeals']))
        ->assertOk()->assertSeeText($registration->reference);

    Livewire::actingAs($staff)
        ->test('pages::staff.registrations.show', ['registration' => $registration->fresh()])
        ->assertSee('I paid on 12 Nov')
        ->set('restoreNote', 'Checked with bank.')
        ->call('restore')
        ->assertHasNoErrors();

    $registration->refresh();
    expect($registration->status)->toBe(RegistrationStatus::Verified)
        ->and($registration->appealed_at)->toBeNull()
        ->and($registration->status_before_moderation)->toBeNull();

    $this->actingAs($delegate)->get(route('dashboard'))->assertOk();
});

test('restoring after moving from blocked to the bin goes back to the original status', function () {
    $registration = Registration::factory()->privateDelegate()->create();
    $staff = registrationsStaff();
    $moderate = app(ModerateRegistration::class);

    $moderate->moderate($registration, $staff, RegistrationStatus::Blocked, 'Suspicious.');
    $moderate->appeal($registration->fresh(), 'Please check again.');
    $moderate->moderate($registration->fresh(), $staff, RegistrationStatus::Deleted, 'Spam.');

    expect($registration->fresh()->appealed_at)->toBeNull();

    $moderate->restore($registration->fresh(), $staff);

    expect($registration->fresh()->status)->toBe(RegistrationStatus::Pending);
});

test('blocked registrations leave the verified totals, and archived and binned ones leave the list', function () {
    $staff = registrationsStaff();
    $blocked = Registration::factory()->privateDelegate()->verified()->create(['amount_paid' => 1000]);
    $archived = Registration::factory()->privateDelegate()->create();
    $binned = Registration::factory()->privateDelegate()->create();
    $kept = Registration::factory()->privateDelegate()->verified()->create(['amount_paid' => 1500]);
    $moderate = app(ModerateRegistration::class);
    $moderate->moderate($blocked, $staff, RegistrationStatus::Blocked, 'Fake.');
    $moderate->moderate($archived, $staff, RegistrationStatus::Archived, 'Duplicate.');
    $moderate->moderate($binned, $staff, RegistrationStatus::Deleted, 'Spam.');

    Livewire::actingAs($staff)
        ->test('pages::staff.registrations.index', ['status' => ''])
        ->assertSet('verifiedAmount', 1500)
        ->assertSeeText($blocked->reference)
        ->assertSeeText($kept->reference)
        ->assertDontSeeText($archived->reference)
        ->assertDontSeeText($binned->reference);

    Livewire::actingAs($staff)
        ->test('pages::staff.registrations.index')
        ->call('showStatus', 'deleted')
        ->assertSeeText($binned->reference)
        ->assertDontSeeText($kept->reference);
});

test('the flagged, appeals, blocked, archived and bin buttons call their filters', function () {
    $this->actingAs(registrationsStaff())
        ->get(route('staff.registrations.index'))
        ->assertOk()
        ->assertSee("wire:click=\"showTag('flagged')\"", false)
        ->assertSee("wire:click=\"showTag('appeals')\"", false)
        ->assertSee("wire:click=\"showStatus('blocked')\"", false)
        ->assertSee("wire:click=\"showStatus('archived')\"", false)
        ->assertSee("wire:click=\"showStatus('deleted')\"", false);
});

test('staff can flag and unflag a registration without changing its status', function () {
    $registration = Registration::factory()->privateDelegate()->verified()->create();
    $staff = registrationsStaff();

    Livewire::actingAs($staff)
        ->test('pages::staff.registrations.show', ['registration' => $registration])
        ->set('flagNote', 'Same black photo as HB26-0132.')
        ->call('flag')
        ->assertHasNoErrors();

    expect($registration->fresh()->isFlagged())->toBeTrue()
        ->and($registration->fresh()->status)->toBe(RegistrationStatus::Verified);

    $this->actingAs($staff)->get(route('staff.registrations.index', ['tag' => 'flagged', 'status' => '']))
        ->assertSeeText($registration->reference);

    Livewire::actingAs($staff)
        ->test('pages::staff.registrations.show', ['registration' => $registration->fresh()])
        ->call('unflag');

    expect($registration->fresh()->isFlagged())->toBeFalse();
});

test('someone without a login can appeal a blocked registration from the status page', function () {
    $registration = Registration::factory()->privateDelegate()->create(['contact_phone' => '0300 1234567']);
    app(ModerateRegistration::class)->moderate($registration, registrationsStaff(), RegistrationStatus::Blocked, 'Fake receipt.');

    Livewire::test('pages::registration.status')
        ->set('identifier', $registration->reference)
        ->call('lookup')
        ->assertSee('Fake receipt.')
        ->set('appealMessage', 'This was a mistake, I can share the bank statement.')
        ->set('confirmation', $registration->reference)
        ->call('appeal')
        ->assertHasErrors('confirmation')
        ->set('confirmation', $registration->contact_email)
        ->call('appeal')
        ->assertHasNoErrors()
        ->assertSet('appealed', true);

    expect($registration->fresh()->appeal_message)->toBe('This was a mistake, I can share the bank statement.');
});

test('a binned registration is not found on the status page', function () {
    $registration = Registration::factory()->privateDelegate()->create();
    app(ModerateRegistration::class)->moderate($registration, registrationsStaff(), RegistrationStatus::Deleted, 'Spam.');

    Livewire::test('pages::registration.status')
        ->set('identifier', $registration->reference)
        ->call('lookup')
        ->assertHasErrors('identifier');
});

test('only admins can delete a binned registration permanently, and only after typing its reference', function () {
    Storage::fake('local');
    $path = UploadedFile::fake()->image('slip.png')->store('payment-screenshots', 'local');
    [$registration, $delegate] = verifiedDelegateWithLogin();
    $registration->update(['payment_screenshot_path' => $path]);
    $registration->paymentScreenshots()->update(['path' => $path]);
    app(ModerateRegistration::class)->moderate($registration, registrationsStaff(), RegistrationStatus::Deleted, 'Spam.');

    Livewire::actingAs(registrationsStaff())
        ->test('pages::staff.registrations.show', ['registration' => $registration->fresh()])
        ->set('purgeConfirmation', $registration->reference)
        ->call('purge')
        ->assertForbidden();

    $admin = User::factory()->role(UserRole::Admin)->create();

    Livewire::actingAs($admin)
        ->test('pages::staff.registrations.show', ['registration' => $registration->fresh()])
        ->set('purgeConfirmation', 'wrong')
        ->call('purge')
        ->assertHasErrors('purgeConfirmation')
        ->set('purgeConfirmation', $registration->reference)
        ->call('purge')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect(Registration::find($registration->id))->toBeNull()
        ->and(Delegate::where('registration_id', $registration->id)->exists())->toBeFalse()
        ->and(User::find($delegate->id))->toBeNull()
        ->and(AuditLog::where('action', 'purged')->exists())->toBeTrue();
    Storage::disk('local')->assertMissing($path);
});

test('staff are never sent to the on-hold page', function () {
    $staff = registrationsStaff();

    $this->actingAs($staff)->get(route('dashboard'))->assertOk();
    $this->actingAs($staff)->get(route('account.restricted'))->assertRedirect(route('dashboard'));
});
