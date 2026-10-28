<?php

use App\Enums\MunExperience;
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
use Livewire\Features\SupportTesting\Testable;
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

test('the payment step lists the registration heads by name and number', function () {
    config(['conference.contacts.heads' => [
        ['name' => 'Muhammad Muneeb', 'phone' => '03084447764'],
        ['name' => null, 'phone' => '03338460791'],
    ]]);

    Livewire::test('pages::registration.create')
        ->set('step', 4)
        ->assertSeeText('Muhammad Muneeb')
        ->assertSeeHtml('href="https://wa.me/923084447764?text=Hi%2C%20I%20am%20looking%20for%20help')
        ->assertSeeHtml('href="https://wa.me/923338460791?text=');
});

test('the account number has a button that copies it without spaces', function () {
    config(['conference.payment.account_number' => 'PK22 HABB 0010 8879 0065 8301']);

    Livewire::test('pages::registration.create')
        ->set('step', 4)
        ->assertSeeText('PK22 HABB 0010 8879 0065 8301')
        ->assertSeeHtml('title="Copy account number"')
        ->assertSeeHtml('PK22HABB0010887900658301');
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

/**
 * An institution delegation of three with every field filled in.
 *
 * @param  array<int, array<string, string>>  $delegateOverrides
 */
function filledDelegationForm(array $delegateOverrides = []): Testable
{
    return Livewire::test('pages::registration.create')
        ->set('contact', institutionContact())
        ->set('delegates', array_map(fn (int $number) => delegateInput($number, $delegateOverrides[$number - 1] ?? []), range(1, 3)))
        ->set('paymentScreenshot', UploadedFile::fake()->image('payment.png'))
        ->set('confirmed', true);
}

test('individual (private delegate) registration is not offered', function () {
    Livewire::withQueryParams(['type' => 'private_delegate'])
        ->test('pages::registration.create')
        ->assertSet('type', RegistrationType::InstitutionDelegation->value)
        ->assertDontSeeText('Private delegate')
        ->set('type', 'private_delegate')
        ->assertSet('type', RegistrationType::InstitutionDelegation->value);
});

test('a private delegation registers with the head as contact', function () {
    Livewire::withQueryParams(['type' => RegistrationType::PrivateDelegation->value])
        ->test('pages::registration.create')
        ->set('contact', [...institutionContact(), 'organisation_name' => 'Lahore Debaters'])
        ->set('delegates', array_map(fn (int $number) => delegateInput($number), range(1, 3)))
        ->set('paymentScreenshot', UploadedFile::fake()->image('payment.jpg'))
        ->set('confirmed', true)
        ->call('submit')
        ->assertHasNoErrors();

    $registration = Registration::sole();

    expect($registration->type)->toBe(RegistrationType::PrivateDelegation)
        ->and($registration->amount_due)->toBe(4000)
        ->and($registration->organisation_name)->toBe('Lahore Debaters');
});

test('no delegate rows show until a delegate count is chosen', function () {
    Livewire::test('pages::registration.create')
        ->set('contact', institutionContact())
        ->call('next')
        ->assertSet('delegateCount', null)
        ->assertCount('delegates', 0)
        ->assertDontSeeText('Delegate 1')
        ->assertSeeText('Pick how many delegates are coming')
        ->call('next')
        ->assertHasErrors(['delegates' => 'required'])
        ->assertSeeText('Choose how many delegates are in your delegation.')
        ->assertSet('step', 2)
        ->set('delegateCount', 4)
        ->assertCount('delegates', 4)
        ->assertSeeText('Delegate 4');
});

test('the delegate count stays within 3 to 6', function () {
    Livewire::test('pages::registration.create')
        ->set('delegateCount', 9)
        ->assertCount('delegates', 6)
        ->set('delegateCount', 1)
        ->assertCount('delegates', 3);
});

test('a delegation needs at least three delegates', function () {
    filledDelegationForm()
        ->set('delegates', [delegateInput(1), delegateInput(2)])
        ->call('submit')
        ->assertHasErrors(['delegates' => 'min']);

    expect(Registration::count())->toBe(0);
});

test('a delegate must choose two different committees', function () {
    $sameCommittee = (string) Committee::where('code', 'UNSC')->value('id');

    filledDelegationForm([['second_committee_id' => $sameCommittee]])
        ->call('submit')
        ->assertHasErrors(['delegates.0.second_committee_id' => 'different']);
});

test('a country preference must be a country from the list', function () {
    filledDelegationForm([['country_preference' => 'Frances']])
        ->call('submit')
        ->assertHasErrors('delegates.0.country_preference');

    expect(Registration::count())->toBe(0);
});

test('a country preference must be on the chosen committee list', function () {
    filledDelegationForm([['country_preference' => 'Brazil']])
        ->call('submit')
        ->assertHasErrors('delegates.0.country_preference');
});

test('a personality preference must be on the list when the committee shows one', function () {
    filledDelegationForm([['personality_preference' => 'Someone Unlisted']])
        ->call('submit')
        ->assertHasErrors('delegates.0.personality_preference');
});

test('the special committee list stays secret, so its preference is typed freely', function () {
    $pna = (string) Committee::where('code', 'PNA')->value('id');
    $special = (string) Committee::where('code', 'SPECIAL')->value('id');

    filledDelegationForm([['first_committee_id' => $pna, 'second_committee_id' => $special, 'personality_preference' => 'Anyone at all']])
        ->call('submit')
        ->assertHasNoErrors('delegates.0.personality_preference')
        ->assertDontSeeText('Tony Stark');
});

test('delegates in one delegation need different emails', function () {
    filledDelegationForm([1 => ['email' => 'DELEGATE1@gmail.com']])
        ->call('submit')
        ->assertHasErrors(['delegates.1.email' => 'distinct']);
});

test('an email already used in an active registration cannot register again', function () {
    Delegate::factory()->create(['email' => 'delegate1@gmail.com']);

    filledDelegationForm()
        ->call('submit')
        ->assertHasErrors(['delegates.0.email']);
});

test('an email from a rejected registration can register again', function () {
    Delegate::factory()
        ->for(Registration::factory()->rejected())
        ->create(['email' => 'delegate1@gmail.com']);

    filledDelegationForm()
        ->call('submit')
        ->assertHasNoErrors();
});

test('the payment screenshot must be a JPG or PNG image', function () {
    filledDelegationForm()
        ->set('paymentScreenshot', UploadedFile::fake()->create('payment.pdf', 100, 'application/pdf'))
        ->call('submit')
        ->assertHasErrors(['paymentScreenshot']);

    expect(Registration::count())->toBe(0);
});

test('the registrant must confirm their details', function () {
    filledDelegationForm()
        ->set('confirmed', false)
        ->call('submit')
        ->assertHasErrors(['confirmed' => 'accepted']);
});

test('each step must be valid before moving on', function () {
    $component = Livewire::test('pages::registration.create')
        ->assertSet('step', 1)
        ->call('next')
        ->assertHasErrors(['contact.organisation_name', 'contact.email'])
        ->assertHasNoErrors(['delegates.0.name', 'paymentScreenshot'])
        ->assertSet('step', 1);

    $component->set('contact', institutionContact())
        ->call('next')
        ->assertHasNoErrors()
        ->assertSet('step', 2)
        ->set('delegateCount', 3)
        ->call('next')
        ->assertHasErrors(['delegates.0.name'])
        ->assertSet('step', 2)
        ->set('delegates', array_map(fn (int $number) => delegateInput($number), range(1, 3)))
        ->call('next')
        ->assertSet('step', 3)
        ->assertSeeText('Check your details')
        ->assertSeeText('Delegate 2')
        ->assertSeeText('Homebridge College')
        ->call('next')
        ->assertSet('step', 4)
        ->set('paymentScreenshot', UploadedFile::fake()->image('payment.png'))
        ->set('confirmed', true)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submittedReference', 'HB26-0001')
        ->assertDispatched('registration-draft-cleared');
});

test('going back keeps the answers, and edit only jumps to earlier steps', function () {
    Livewire::test('pages::registration.create')
        ->set('contact', institutionContact())
        ->call('next')
        ->call('editStep', 4)
        ->assertSet('step', 2)
        ->call('back')
        ->assertSet('step', 1)
        ->assertSet('contact.city', 'Lahore');
});

test('a failed submit returns to the step with the problem', function () {
    Livewire::test('pages::registration.create')
        ->set('step', 4)
        ->set('contact', [...institutionContact(), 'email' => 'not-an-email'])
        ->set('delegates', array_map(fn (int $number) => delegateInput($number), range(1, 3)))
        ->set('paymentScreenshot', UploadedFile::fake()->image('payment.png'))
        ->set('confirmed', true)
        ->call('submit')
        ->assertHasErrors('contact.email')
        ->assertSet('step', 1);
});

test('a saved draft is restored to the furthest valid step', function () {
    $delegates = array_map(fn (int $number) => delegateInput($number), range(1, 4));

    Livewire::test('pages::registration.create')
        ->call('restoreDraft', [
            'type' => RegistrationType::PrivateDelegation->value,
            'step' => 4,
            'contact' => institutionContact(),
            'delegateCount' => 4,
            'delegates' => $delegates,
        ])
        ->assertSet('type', RegistrationType::PrivateDelegation->value)
        ->assertSet('step', 4)
        ->assertSet('restoredDraft', true)
        ->assertCount('delegates', 4)
        ->assertSet('delegates.3.email', 'delegate4@gmail.com')
        ->assertHasNoErrors();

    Livewire::test('pages::registration.create')
        ->call('restoreDraft', [
            'step' => 4,
            'contact' => institutionContact(),
            'delegates' => [delegateInput(1), ['name' => ['not', 'a', 'string']]],
        ])
        ->assertSet('step', 2)
        ->assertSet('delegates.1.name', '')
        ->assertHasNoErrors();
});

test('starting over clears the form', function () {
    Livewire::test('pages::registration.create')
        ->call('restoreDraft', ['step' => 2, 'contact' => institutionContact()])
        ->assertSet('step', 2)
        ->call('startOver')
        ->assertSet('step', 1)
        ->assertSet('contact.city', '')
        ->assertSet('restoredDraft', false)
        ->assertDispatched('registration-draft-cleared');
});

test('the amount due only appears once the delegate count can be chosen', function () {
    Livewire::test('pages::registration.create')
        ->assertDontSeeText('Amount due')
        ->assertSeeText('You’ll choose how many delegates')
        ->set('contact', institutionContact())
        ->call('next')
        ->assertSeeText('Amount due')
        ->assertSeeText('Choose the number of delegates to see your total.')
        ->set('delegateCount', 5)
        ->assertSeeText('PKR 6,000');
});

test('delegates can pick their previous MUN experience, or leave it blank', function () {
    filledDelegationForm([['mun_experience' => 'first_timer'], ['mun_experience' => 'experienced']])
        ->set('step', 2)
        ->assertSeeText(['First MUN', '1–4 MUNs', '5+ MUNs'])
        ->call('submit')
        ->assertHasNoErrors();

    expect(Delegate::orderBy('id')->pluck('mun_experience')->all())
        ->toBe([MunExperience::FirstTimer, MunExperience::Experienced, null]);
});

test('previous MUN experience must be one of the listed options', function () {
    filledDelegationForm([['mun_experience' => 'LUMUN ’25, UNSC']])
        ->call('submit')
        ->assertHasErrors('delegates.0.mun_experience');
});

test('a registration can include a delegate referral code, or leave it blank', function () {
    filledDelegationForm()
        ->set('contact.referral_code', '  hb-ali26 ')
        ->call('submit')
        ->assertHasNoErrors();

    expect(Registration::sole()->referral_code)->toBe('HB-ALI26');
});

test('the referral code is optional', function () {
    filledDelegationForm()
        ->call('submit')
        ->assertHasNoErrors();

    expect(Registration::sole()->referral_code)->toBeNull();
});

test('the referral code is limited to 50 characters', function () {
    filledDelegationForm()
        ->set('contact.referral_code', str_repeat('A', 51))
        ->call('submit')
        ->assertHasErrors('contact.referral_code');
});
