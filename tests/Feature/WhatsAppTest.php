<?php

use App\Actions\Registrations\VerifyRegistration;
use App\Enums\RegistrationStatus;
use App\Enums\UserRole;
use App\Models\Committee;
use App\Models\Delegate;
use App\Models\Registration;
use App\Models\User;
use App\Models\WhatsAppLog;
use App\Support\WhatsApp;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

beforeEach(fn () => Notification::fake());

test('local pakistani numbers get the country code', function (string $phone, ?string $expected) {
    expect(WhatsApp::number($phone))->toBe($expected);
})->with([
    ['0300 1234567', '923001234567'],
    ['03001234567', '923001234567'],
    ['+92 300 1234567', '923001234567'],
    ['0092-300-1234567', '923001234567'],
    ['3001234567', '923001234567'],
    ['12345', null],
    ['', null],
]);

test('the link carries the message, encoded', function () {
    expect(WhatsApp::link('0300 1234567', 'Hi & bye'))->toBe('https://wa.me/923001234567?text=Hi%20%26%20bye')
        ->and(WhatsApp::link('0300 1234567'))->toBe('https://wa.me/923001234567')
        ->and(WhatsApp::link('nope', 'Hi'))->toBeNull();
});

test('opening whatsapp for the contact person records it and sends them on with the status message', function () {
    $staff = User::factory()->role(UserRole::Registrations)->create();
    $registration = Registration::factory()->create(['contact_phone' => '0321 7654321']);

    $this->actingAs($staff)
        ->get(route('staff.registrations.show', $registration))
        ->assertSee(route('staff.whatsapp.contact', $registration), false)
        ->assertSeeText('Not on WhatsApp yet');

    $response = $this->actingAs($staff)->get(route('staff.whatsapp.contact', $registration));

    expect($response->headers->get('Location'))
        ->toStartWith('https://wa.me/923217654321?text=')
        ->toContain(rawurlencode($registration->reference));

    $log = WhatsAppLog::sole();
    expect($log->kind)->toBe('contact_pending')
        ->and($log->sent_by)->toBe($staff->id)
        ->and($registration->refresh()->contactWhatsApp()?->is($log))->toBeTrue();

    $this->actingAs($staff)
        ->get(route('staff.registrations.show', $registration))
        ->assertSeeText('Send again');
});

test('a status change needs a new whatsapp message', function () {
    $registration = Registration::factory()->create();
    WhatsAppLog::create(['kind' => 'contact_pending', 'phone' => '03001234567', 'registration_id' => $registration->id]);

    $this->travel(1)->minute();
    $registration->update(['status' => RegistrationStatus::Verified, 'reviewed_at' => now()]);

    expect($registration->refresh()->contactWhatsApp())->toBeNull();
});

test('a delegate is told on whatsapp without the password link, and staff see whether they set a password', function () {
    $staff = User::factory()->role(UserRole::Registrations)->create();
    $registration = Registration::factory()->verified()->create();
    $user = User::factory()->create(['email' => 'ayesha@gmail.com', 'password_set_at' => null]);
    $delegate = Delegate::factory()->for($registration)->create(['user_id' => $user->id, 'phone' => '03111111111']);

    $this->actingAs($staff)
        ->get(route('staff.registrations.show', $registration))
        ->assertSeeText('Password not set');

    $location = $this->actingAs($staff)->get(route('staff.whatsapp.account', $delegate))->headers->get('Location');

    expect($location)->toStartWith('https://wa.me/923111111111?text=')
        ->toContain(rawurlencode('ayesha@gmail.com'))
        ->not->toContain('reset-password')
        ->and($delegate->refresh()->accountWhatsApp())->not->toBeNull();

    $user->update(['password' => 'NewPassword123']);

    $this->actingAs($staff)
        ->get(route('staff.registrations.show', $registration))
        ->assertSeeText('Password set');
});

test('setting a password through the reset link records it', function () {
    $user = User::factory()->create(['password_set_at' => null]);
    $token = Password::broker()->createToken($user);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'BrandNew123',
        'password_confirmation' => 'BrandNew123',
    ])->assertSessionHasNoErrors();

    expect($user->refresh()->password_set_at)->not->toBeNull();
});

test('new delegate accounts start without a password set', function () {
    $registration = Registration::factory()->create();
    Delegate::factory()->for($registration)->create();

    app(VerifyRegistration::class)->handle($registration, User::factory()->role(UserRole::Registrations)->create());

    expect($registration->delegates()->sole()->user->password_set_at)->toBeNull();
});

test('a sent allocation can be sent on whatsapp, and is tracked until it changes', function () {
    $staff = User::factory()->role(UserRole::Registrations)->create();
    $committee = Committee::factory()->create(['code' => 'UNSC']);
    $delegate = Delegate::factory()->for(Registration::factory()->verified())->create([
        'user_id' => User::factory()->create()->id,
        'phone' => '03222222222',
        'allocated_committee_id' => $committee->id,
        'allocated_portfolio' => 'Pakistan',
        'allocation_published_at' => now(),
    ]);

    Livewire::actingAs($staff)
        ->test('pages::staff.allocations', ['show' => 'no-whatsapp'])
        ->assertSeeText($delegate->name);

    $location = $this->actingAs($staff)->get(route('staff.whatsapp.allocation', $delegate))->headers->get('Location');
    expect($location)->toStartWith('https://wa.me/923222222222?text=')->toContain('Pakistan');

    Livewire::actingAs($staff)
        ->test('pages::staff.allocations', ['show' => 'no-whatsapp'])
        ->assertDontSeeText($delegate->name);

    $this->travel(1)->minute();
    $delegate->update(['allocated_portfolio' => 'China', 'allocation_published_at' => now()]);

    expect($delegate->refresh()->allocationWhatsApp())->toBeNull();
});

test('only staff who can see the registration can open its whatsapp links', function () {
    $registration = Registration::factory()->create();
    $delegate = Delegate::factory()->for($registration)->create(['allocation_published_at' => now()]);

    $this->actingAs(User::factory()->create())->get(route('staff.whatsapp.contact', $registration))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('staff.whatsapp.allocation', $delegate))->assertForbidden();

    expect(WhatsAppLog::count())->toBe(0);
});
