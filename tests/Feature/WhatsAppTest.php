<?php

use App\Enums\UserRole;
use App\Models\Committee;
use App\Models\Delegate;
use App\Models\Registration;
use App\Models\User;
use App\Support\WhatsApp;
use Livewire\Livewire;

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

test('staff can send the contact person their status on whatsapp', function () {
    $registration = Registration::factory()->create(['contact_phone' => '0321 7654321']);

    $this->actingAs(User::factory()->role(UserRole::Registrations)->create())
        ->get(route('staff.registrations.show', $registration))
        ->assertOk()
        ->assertSee('https://wa.me/923217654321?text=', false)
        ->assertSee(rawurlencode($registration->reference), false);
});

test('a delegate with an account can be told on whatsapp, without the password link', function () {
    $registration = Registration::factory()->verified()->create();
    $user = User::factory()->create(['email' => 'ayesha@gmail.com']);
    Delegate::factory()->for($registration)->create(['user_id' => $user->id, 'phone' => '03111111111', 'name' => 'Ayesha']);

    $this->actingAs(User::factory()->role(UserRole::Registrations)->create())
        ->get(route('staff.registrations.show', $registration))
        ->assertSee('https://wa.me/923111111111?text=', false)
        ->assertSee(rawurlencode('ayesha@gmail.com'), false)
        ->assertDontSee('reset-password', false);
});

test('a sent allocation can be shared on whatsapp', function () {
    $committee = Committee::factory()->create(['code' => 'UNSC']);
    $delegate = Delegate::factory()->for(Registration::factory()->verified())->create([
        'user_id' => User::factory()->create()->id,
        'phone' => '03222222222',
        'allocated_committee_id' => $committee->id,
        'allocated_portfolio' => 'Pakistan',
        'allocation_published_at' => now(),
    ]);

    Livewire::actingAs(User::factory()->role(UserRole::Registrations)->create())
        ->test('pages::staff.allocations', ['show' => 'sent'])
        ->assertSeeHtml('https://wa.me/923222222222?text=')
        ->assertSeeHtml(rawurlencode('Pakistan'));
});
