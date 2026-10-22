<?php

use App\Enums\RegistrationStatus;
use App\Enums\RegistrationType;
use App\Models\Committee;
use App\Models\Delegate;
use App\Models\Registration;
use App\Notifications\RegistrationReceived;
use Database\Seeders\CommitteeSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(CommitteeSeeder::class);
    Storage::fake('local');
    Notification::fake();
});

/**
 * @return array<string, string>
 */
function delegateInput(int $number, array $overrides = []): array
{
    return [
        'name' => "Delegate {$number}",
        'cnic' => sprintf('35202-%07d-1', $number),
        'email' => "delegate{$number}@gmail.com",
        'phone' => '0300123456'.$number,
        'first_committee_id' => (string) Committee::where('code', 'UNSC')->value('id'),
        'second_committee_id' => (string) Committee::where('code', 'PNA')->value('id'),
        'country_preference' => 'France',
        'personality_preference' => 'Maryam Nawaz',
        ...$overrides,
    ];
}

function institutionContact(): array
{
    return [
        'organisation_name' => 'Homebridge College',
        'city' => 'Lahore',
        'name' => 'Ms. Representative',
        'cnic' => '35202-7654321-2',
        'phone' => '03001112223',
        'email' => 'rep@homebridge.edu.pk',
    ];
}

test('the registration page renders for guests', function () {
    $this->get(route('register'))->assertOk()->assertSeeText('How would you like to register?');
});

test('an institution delegation registers with all its delegates and payment screenshot', function () {
    $component = Livewire::test('pages::registration.create')
        ->set('type', RegistrationType::InstitutionDelegation->value)
        ->set('delegateCount', 4)
        ->set('contact', institutionContact())
        ->set('delegates', array_map(fn (int $number) => delegateInput($number), range(1, 4)))
        ->set('paymentScreenshot', UploadedFile::fake()->image('payment.png'))
        ->set('confirmed', true)
        ->call('submit')
        ->assertHasNoErrors();

    $registration = Registration::sole();

    expect($registration->type)->toBe(RegistrationType::InstitutionDelegation)
        ->and($registration->status)->toBe(RegistrationStatus::Pending)
        ->and($registration->amount_due)->toBe(5000)
        ->and($registration->organisation_name)->toBe('Homebridge College')
        ->and($registration->contact_email)->toBe('rep@homebridge.edu.pk')
        ->and($registration->reference)->toBe('HB26-0001')
        ->and($registration->delegates()->count())->toBe(4)
        ->and($registration->auditLogs()->value('action'))->toBe('submitted');

    Storage::disk('local')->assertExists($registration->payment_screenshot_path);
    $component->assertSet('submittedReference', 'HB26-0001');

    Notification::assertSentOnDemand(
        RegistrationReceived::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'rep@homebridge.edu.pk',
    );
});

test('a private delegate is their own contact and pays the delegate fee only', function () {
    Livewire::withQueryParams(['type' => RegistrationType::PrivateDelegate->value])->test('pages::registration.create')
        ->set('contact.city', 'Karachi')
        ->set('delegates', [delegateInput(1)])
        ->set('paymentScreenshot', UploadedFile::fake()->image('payment.jpg'))
        ->set('confirmed', true)
        ->call('submit')
        ->assertHasNoErrors();

    $registration = Registration::sole();

    expect($registration->amount_due)->toBe(1000)
        ->and($registration->contact_name)->toBe('Delegate 1')
        ->and($registration->contact_email)->toBe('delegate1@gmail.com')
        ->and($registration->city)->toBe('Karachi')
        ->and($registration->organisation_name)->toBeNull();
});

test('switching to a delegation keeps the delegate count within 3 to 6', function () {
    Livewire::withQueryParams(['type' => RegistrationType::PrivateDelegate->value])->test('pages::registration.create')
        ->assertCount('delegates', 1)
        ->set('type', RegistrationType::PrivateDelegation->value)
        ->assertCount('delegates', 3)
        ->set('delegateCount', 9)
        ->assertCount('delegates', 6);
});

test('a delegation needs at least three delegates', function () {
    Livewire::test('pages::registration.create')
        ->set('contact', institutionContact())
        ->set('delegates', [delegateInput(1), delegateInput(2)])
        ->set('paymentScreenshot', UploadedFile::fake()->image('payment.png'))
        ->set('confirmed', true)
        ->call('submit')
        ->assertHasErrors(['delegates' => 'min']);

    expect(Registration::count())->toBe(0);
});

test('a delegate must choose two different committees', function () {
    $sameCommittee = (string) Committee::where('code', 'UNSC')->value('id');

    Livewire::withQueryParams(['type' => RegistrationType::PrivateDelegate->value])->test('pages::registration.create')
        ->set('contact.city', 'Lahore')
        ->set('delegates', [delegateInput(1, ['second_committee_id' => $sameCommittee])])
        ->set('paymentScreenshot', UploadedFile::fake()->image('payment.png'))
        ->set('confirmed', true)
        ->call('submit')
        ->assertHasErrors(['delegates.0.second_committee_id' => 'different']);
});

test('delegates in one delegation need different emails', function () {
    Livewire::test('pages::registration.create')
        ->set('contact', institutionContact())
        ->set('delegates', [
            delegateInput(1),
            delegateInput(2, ['email' => 'DELEGATE1@gmail.com']),
            delegateInput(3),
        ])
        ->call('submit')
        ->assertHasErrors(['delegates.1.email' => 'distinct']);
});

test('an email already used in an active registration cannot register again', function () {
    Delegate::factory()->create(['email' => 'delegate1@gmail.com']);

    Livewire::withQueryParams(['type' => RegistrationType::PrivateDelegate->value])->test('pages::registration.create')
        ->set('contact.city', 'Lahore')
        ->set('delegates', [delegateInput(1)])
        ->call('submit')
        ->assertHasErrors(['delegates.0.email']);
});

test('an email from a rejected registration can register again', function () {
    Delegate::factory()
        ->for(Registration::factory()->rejected())
        ->create(['email' => 'delegate1@gmail.com']);

    Livewire::withQueryParams(['type' => RegistrationType::PrivateDelegate->value])->test('pages::registration.create')
        ->set('contact.city', 'Lahore')
        ->set('delegates', [delegateInput(1)])
        ->set('paymentScreenshot', UploadedFile::fake()->image('payment.png'))
        ->set('confirmed', true)
        ->call('submit')
        ->assertHasNoErrors();
});

test('the payment screenshot must be a JPG or PNG image', function () {
    Livewire::withQueryParams(['type' => RegistrationType::PrivateDelegate->value])->test('pages::registration.create')
        ->set('contact.city', 'Lahore')
        ->set('delegates', [delegateInput(1)])
        ->set('paymentScreenshot', UploadedFile::fake()->create('payment.pdf', 100, 'application/pdf'))
        ->set('confirmed', true)
        ->call('submit')
        ->assertHasErrors(['paymentScreenshot']);

    expect(Registration::count())->toBe(0);
});

test('the registrant must confirm their details', function () {
    Livewire::withQueryParams(['type' => RegistrationType::PrivateDelegate->value])->test('pages::registration.create')
        ->set('contact.city', 'Lahore')
        ->set('delegates', [delegateInput(1)])
        ->set('paymentScreenshot', UploadedFile::fake()->image('payment.png'))
        ->call('submit')
        ->assertHasErrors(['confirmed' => 'accepted']);
});
