<?php

use App\Enums\RegistrationStatus;
use App\Enums\UserRole;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\RegistrationRejected;
use App\Notifications\RegistrationVerified;
use App\Notifications\SetPassword;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('guests are redirected to login from the staff registrations page', function () {
    $this->get(route('staff.registrations.index'))->assertRedirect(route('login'));
});

test('delegates cannot open the staff registrations pages', function () {
    $registration = Registration::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->get(route('staff.registrations.index'))->assertForbidden();
    $this->get(route('staff.registrations.show', $registration))->assertForbidden();
    $this->get(route('staff.registrations.payment-screenshot', $registration))->assertForbidden();
});

test('registrations staff and admins can browse registrations', function (UserRole $role) {
    $registration = Registration::factory()->institutionDelegation()->create();
    $this->actingAs(User::factory()->role($role)->create());

    $this->get(route('staff.registrations.index'))->assertOk()->assertSeeText($registration->reference);
    $this->get(route('staff.registrations.show', $registration))->assertOk()->assertSeeText($registration->organisation_name);
})->with([UserRole::Registrations, UserRole::Admin]);

test('staff can view a payment screenshot', function () {
    Storage::fake('local');
    $path = UploadedFile::fake()->image('payment.png')->store('payment-screenshots', 'local');
    $registration = Registration::factory()->create(['payment_screenshot_path' => $path]);

    $this->actingAs(User::factory()->role(UserRole::Admin)->create())
        ->get(route('staff.registrations.payment-screenshot', $registration))
        ->assertOk();
});

test('verifying a registration creates delegate accounts and emails set-password links', function () {
    Notification::fake();
    $reviewer = User::factory()->role(UserRole::Registrations)->create();
    $registration = Registration::factory()->institutionDelegation(3)->create();

    Livewire::actingAs($reviewer)
        ->test('pages::staff.registrations.show', ['registration' => $registration])
        ->call('verify')
        ->assertHasNoErrors();

    $registration->refresh();

    expect($registration->status)->toBe(RegistrationStatus::Verified)
        ->and($registration->reviewed_by)->toBe($reviewer->id)
        ->and($registration->auditLogs()->value('action'))->toBe('verified');

    $registration->delegates->each(function ($delegate) {
        expect($delegate->user)->not->toBeNull()
            ->and($delegate->user->email)->toBe($delegate->email)
            ->and($delegate->user->role)->toBe(UserRole::Delegate);

        Notification::assertSentTo($delegate->user, SetPassword::class);
    });

    Notification::assertSentOnDemand(RegistrationVerified::class);
});

test('a delegate can set their password from the verification email and log in', function () {
    Notification::fake();
    $registration = Registration::factory()->institutionDelegation(3)->create();

    Livewire::actingAs(User::factory()->role(UserRole::Registrations)->create())
        ->test('pages::staff.registrations.show', ['registration' => $registration])
        ->call('verify');

    auth()->logout();
    $user = $registration->delegates()->first()->user;

    Notification::assertSentTo($user, SetPassword::class, function (SetPassword $notification) use ($user) {
        $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'a-new-Password-123',
            'password_confirmation' => 'a-new-Password-123',
        ])->assertSessionHasNoErrors();

        return true;
    });

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'a-new-Password-123']);
    $this->assertAuthenticatedAs($user);
});

test('admins can verify registrations', function () {
    Notification::fake();
    $registration = Registration::factory()->institutionDelegation()->create();

    Livewire::actingAs(User::factory()->role(UserRole::Admin)->create())
        ->test('pages::staff.registrations.show', ['registration' => $registration])
        ->assertSeeText('Verify payment')
        ->call('verify')
        ->assertHasNoErrors();

    expect($registration->refresh()->status)->toBe(RegistrationStatus::Verified);
});

test('delegates cannot verify or reject registrations', function () {
    $registration = Registration::factory()->institutionDelegation()->create();

    Livewire::actingAs(User::factory()->create())
        ->test('pages::staff.registrations.show', ['registration' => $registration])
        ->call('verify')
        ->assertForbidden();

    expect($registration->refresh()->status)->toBe(RegistrationStatus::Pending);
});

test('rejecting a registration requires a reason and emails the contact', function () {
    Notification::fake();
    $registration = Registration::factory()->create();
    $component = Livewire::actingAs(User::factory()->role(UserRole::Registrations)->create())
        ->test('pages::staff.registrations.show', ['registration' => $registration]);

    $component->call('reject')->assertHasErrors(['rejectionReason' => 'required']);

    $component->set('rejectionReason', 'The amount paid does not match.')
        ->call('reject')
        ->assertHasNoErrors();

    $registration->refresh();

    expect($registration->status)->toBe(RegistrationStatus::Rejected)
        ->and($registration->rejection_reason)->toBe('The amount paid does not match.');

    Notification::assertSentOnDemand(
        RegistrationRejected::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === $registration->contact_email,
    );
});

test('a registration that has already been reviewed cannot be verified again', function () {
    Notification::fake();
    $registration = Registration::factory()->rejected()->create();

    Livewire::actingAs(User::factory()->role(UserRole::Registrations)->create())
        ->test('pages::staff.registrations.show', ['registration' => $registration])
        ->call('verify');

    expect($registration->refresh()->status)->toBe(RegistrationStatus::Rejected);
    Notification::assertNothingSent();
});
