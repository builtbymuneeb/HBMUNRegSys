<?php

use App\Enums\RegistrationStatus;
use App\Enums\UserRole;
use App\Models\Committee;
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
    $this->get(route('staff.registrations.payment-screenshot', [$registration, $registration->paymentScreenshots()->first()]))->assertForbidden();
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
        ->get(route('staff.registrations.payment-screenshot', [$registration, $registration->paymentScreenshots()->first()]))
        ->assertOk();
});

test('a payment screenshot can only be opened through its own registration', function () {
    Storage::fake('local');
    $registration = Registration::factory()->create();
    $other = Registration::factory()->create();

    $this->actingAs(User::factory()->role(UserRole::Admin)->create())
        ->get(route('staff.registrations.payment-screenshot', [$registration, $other->paymentScreenshots()->first()]))
        ->assertNotFound();
});

test('reviewers can add more payment slips to a registration', function () {
    Storage::fake('local');
    $reviewer = User::factory()->role(UserRole::Registrations)->create();
    $registration = Registration::factory()->verified()->create();

    Livewire::actingAs($reviewer)
        ->test('pages::staff.registrations.show', ['registration' => $registration])
        ->set('newSlip', UploadedFile::fake()->image('second-receipt.png'))
        ->assertHasNoErrors()
        ->assertSeeText('Slip 2 of 2')
        ->assertSeeText('Added by '.$reviewer->name);

    $added = $registration->paymentScreenshots()->get()->last();

    expect($registration->paymentScreenshots()->count())->toBe(2)
        ->and($added->uploaded_by)->toBe($reviewer->id)
        ->and($registration->auditLogs()->value('action'))->toBe('payment_screenshot_added');

    Storage::disk('local')->assertExists($added->path);
});

test('an added payment slip must be an image', function () {
    Storage::fake('local');
    $registration = Registration::factory()->create();

    Livewire::actingAs(User::factory()->role(UserRole::Admin)->create())
        ->test('pages::staff.registrations.show', ['registration' => $registration])
        ->set('newSlip', UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf'))
        ->assertHasErrors('newSlip');

    expect($registration->paymentScreenshots()->count())->toBe(1);
});

test('reviewers can remove a payment slip, but not the last one', function () {
    Storage::fake('local');
    $registration = Registration::factory()->create();
    $second = $registration->paymentScreenshots()->create(['path' => 'payment-screenshots/second.png']);
    $first = $registration->paymentScreenshots()->first();

    $component = Livewire::actingAs(User::factory()->role(UserRole::Admin)->create())
        ->test('pages::staff.registrations.show', ['registration' => $registration])
        ->call('removeSlip', $first->id);

    expect($registration->paymentScreenshots()->pluck('id')->all())->toBe([$second->id])
        ->and($registration->refresh()->payment_screenshot_path)->toBe('payment-screenshots/second.png');

    $component->call('removeSlip', $second->id);

    expect($registration->paymentScreenshots()->count())->toBe(1);
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

test('the registrations list picks up new submissions without a reload', function () {
    $component = Livewire::actingAs(User::factory()->role(UserRole::Registrations)->create())
        ->test('pages::staff.registrations.index');

    $registration = Registration::factory()->create();

    $component->call('poll')
        ->assertSeeText($registration->reference)
        ->assertSet('pendingCount', 1);
});

test('staff can fix a delegate’s wrong email and a new set-password email goes to the new address', function () {
    Notification::fake();
    $reviewer = User::factory()->role(UserRole::Registrations)->create();
    $registration = Registration::factory()->institutionDelegation(3)->create();

    $component = Livewire::actingAs($reviewer)
        ->test('pages::staff.registrations.show', ['registration' => $registration])
        ->call('verify');

    $delegate = $registration->delegates()->first();
    $oldUser = $delegate->user;
    Notification::fake();

    $component->call('editDelegate', $delegate->id)
        ->set('delegateForm.email', 'fixed.address@example.com')
        ->call('saveDelegate')
        ->assertHasNoErrors();

    $delegate->refresh();
    $user = $delegate->user;

    expect($delegate->email)->toBe('fixed.address@example.com')
        ->and($user->id)->toBe($oldUser->id)
        ->and($user->email)->toBe('fixed.address@example.com')
        ->and($registration->auditLogs()->value('action'))->toBe('delegate_updated');

    Notification::assertSentTo($user, SetPassword::class);
    Notification::assertCount(1);
});

test('staff can change a delegate’s committees and preferences without sending email', function () {
    Notification::fake();
    $registration = Registration::factory()->institutionDelegation(3)->create();
    $delegate = $registration->delegates()->first();
    $committees = Committee::factory()->count(2)->create();

    Livewire::actingAs(User::factory()->role(UserRole::Admin)->create())
        ->test('pages::staff.registrations.show', ['registration' => $registration])
        ->call('editDelegate', $delegate->id)
        ->set('delegateForm.first_committee_id', (string) $committees[0]->id)
        ->set('delegateForm.second_committee_id', (string) $committees[1]->id)
        ->set('delegateForm.first_preference', 'Japan')
        ->call('saveDelegate')
        ->assertHasNoErrors();

    $delegate->refresh();

    expect($delegate->first_committee_id)->toBe($committees[0]->id)
        ->and($delegate->second_committee_id)->toBe($committees[1]->id)
        ->and($delegate->first_preference)->toBe('Japan')
        ->and($delegate->preferenceFor($committees[0]))->toBe('Japan');

    Notification::assertNothingSent();
});

test('a delegate email already used by another delegate is refused', function () {
    $registration = Registration::factory()->institutionDelegation(3)->create();
    [$first, $second] = $registration->delegates()->get();

    Livewire::actingAs(User::factory()->role(UserRole::Admin)->create())
        ->test('pages::staff.registrations.show', ['registration' => $registration])
        ->call('editDelegate', $first->id)
        ->set('delegateForm.email', $second->email)
        ->call('saveDelegate')
        ->assertHasErrors('delegateForm.email');

    expect($first->refresh()->email)->not->toBe($second->email);
});

test('staff can resend a set-password email', function () {
    Notification::fake();
    $reviewer = User::factory()->role(UserRole::Registrations)->create();
    $registration = Registration::factory()->institutionDelegation(3)->create();

    $component = Livewire::actingAs($reviewer)
        ->test('pages::staff.registrations.show', ['registration' => $registration])
        ->call('verify');

    $delegate = $registration->delegates()->first();
    Notification::fake();

    $component->call('resendSetPassword', $delegate->id)->assertHasNoErrors();

    Notification::assertSentTo($delegate->user, SetPassword::class);
});

test('delegates from another registration cannot be edited through this one', function () {
    $registration = Registration::factory()->institutionDelegation(3)->create();
    $other = Registration::factory()->institutionDelegation(3)->create()->delegates()->first();

    Livewire::actingAs(User::factory()->role(UserRole::Admin)->create())
        ->test('pages::staff.registrations.show', ['registration' => $registration])
        ->call('editDelegate', $other->id)
        ->assertNotFound();
});

test('a delegate’s preference must come from the chosen committees’ lists, like on the registration form', function () {
    $registration = Registration::factory()->institutionDelegation(3)->create();
    $delegate = $registration->delegates()->first();
    $first = Committee::factory()->create(['portfolios' => ['France', 'Japan'], 'portfolios_public' => true]);
    $second = Committee::factory()->characters()->create(['portfolios' => null]);

    $component = Livewire::actingAs(User::factory()->role(UserRole::Admin)->create())
        ->test('pages::staff.registrations.show', ['registration' => $registration])
        ->call('editDelegate', $delegate->id)
        ->set('delegateForm.first_committee_id', (string) $first->id)
        ->set('delegateForm.second_committee_id', (string) $second->id)
        ->assertSee('Search, e.g. France');

    $component->set('delegateForm.first_preference', 'Atlantis')
        ->set('delegateForm.second_preference', 'Anyone at all')
        ->call('saveDelegate')
        ->assertHasErrors('delegateForm.first_preference')
        ->assertHasNoErrors('delegateForm.second_preference');

    $component->set('delegateForm.first_preference', 'Japan')
        ->call('saveDelegate')
        ->assertHasNoErrors();

    expect($delegate->refresh())
        ->first_preference->toBe('Japan')
        ->second_preference->toBe('Anyone at all');
});
