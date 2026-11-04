<?php

use App\Enums\MunExperience;
use App\Enums\RegistrationStatus;
use App\Enums\RegistrationType;
use App\Models\CampusAmbassador;
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
        'cnic' => sprintf('35202%07d1', $number),
        'email' => "delegate{$number}@gmail.com",
        'phone' => '0300123456'.$number,
        'first_committee_id' => (string) Committee::where('code', 'UNSC')->value('id'),
        'second_committee_id' => (string) Committee::where('code', 'PNA')->value('id'),
        'first_preference' => 'France',
        'second_preference' => 'Maryam Nawaz',
        ...$overrides,
    ];
}

function institutionContact(): array
{
    return [
        'organisation_name' => 'Homebridge College',
        'city' => 'Lahore',
        'name' => 'Ms. Representative',
        'cnic' => '3520276543212',
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
        ->set('newScreenshot', UploadedFile::fake()->image('payment.png'))
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
        ->set('newScreenshot', UploadedFile::fake()->image('payment.png'))
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
        ->set('newScreenshot', UploadedFile::fake()->image('payment.jpg'))
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
        ->assertSet('step', 3)
        ->assertHasErrors('delegates')
        ->assertSeeText('Number of delegates')
        ->call('editStep', 2)
        ->assertSeeText('Choose how many delegates are in your delegation.')
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
    filledDelegationForm([['first_preference' => 'Frances']])
        ->call('submit')
        ->assertHasErrors('delegates.0.first_preference');

    expect(Registration::count())->toBe(0);
});

test('a country preference must be on the chosen committee list', function () {
    filledDelegationForm([['first_preference' => 'Brazil']])
        ->call('submit')
        ->assertHasErrors('delegates.0.first_preference');
});

test('a personality preference must be on the list when the committee shows one', function () {
    filledDelegationForm([['second_preference' => 'Someone Unlisted']])
        ->call('submit')
        ->assertHasErrors('delegates.0.second_preference');
});

test('the special committee list stays secret, so its preference is typed freely', function () {
    $pna = (string) Committee::where('code', 'PNA')->value('id');
    $special = (string) Committee::where('code', 'SPECIAL')->value('id');

    filledDelegationForm([['first_committee_id' => $pna, 'second_committee_id' => $special, 'first_preference' => 'Maryam Nawaz', 'second_preference' => 'Anyone at all']])
        ->call('submit')
        ->assertHasNoErrors(['delegates.0.first_preference', 'delegates.0.second_preference'])
        ->assertDontSeeText('Tony Stark');
});

test('delegates in one delegation need different emails', function () {
    filledDelegationForm([1 => ['email' => 'DELEGATE1@gmail.com']])
        ->call('submit')
        ->assertHasErrors(['delegates.1.email' => 'distinct']);
});

test('an ID number of any length is accepted, so foreign passports and IDs work', function () {
    filledDelegationForm([0 => ['cnic' => '123456'], 1 => ['cnic' => '987654321012345678']])
        ->call('submit')
        ->assertHasNoErrors(['delegates.0.cnic', 'delegates.1.cnic']);
});

test('an ID number can only contain digits', function () {
    filledDelegationForm([0 => ['cnic' => 'AB-1234']])
        ->call('submit')
        ->assertHasErrors(['delegates.0.cnic' => 'regex']);
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
        ->set('paymentScreenshots', [])
        ->set('newScreenshot', UploadedFile::fake()->create('payment.pdf', 100, 'application/pdf'))
        ->assertHasErrors(['newScreenshot'])
        ->assertCount('paymentScreenshots', 0)
        ->call('submit')
        ->assertHasErrors(['paymentScreenshots' => 'required']);

    expect(Registration::count())->toBe(0);
});

test('a delegation can upload one screenshot per receipt, up to six', function () {
    $component = filledDelegationForm();

    foreach (range(2, 6) as $number) {
        $component->set('newScreenshot', UploadedFile::fake()->image("receipt-{$number}.png"));
    }

    $component->set('step', 4)
        ->assertCount('paymentScreenshots', 6)
        ->assertSeeText('That’s the most screenshots you can upload (6).')
        ->set('newScreenshot', UploadedFile::fake()->image('receipt-7.png'))
        ->assertHasErrors(['newScreenshot'])
        ->assertCount('paymentScreenshots', 6)
        ->call('submit')
        ->assertHasNoErrors();

    $registration = Registration::sole();

    expect($registration->paymentScreenshots)->toHaveCount(6)
        ->and($registration->payment_screenshot_path)->toBe($registration->paymentScreenshots->first()->path);

    $registration->paymentScreenshots->each(fn ($screenshot) => Storage::disk('local')->assertExists($screenshot->path));
});

test('a picked screenshot can be removed before submitting', function () {
    filledDelegationForm()
        ->set('newScreenshot', UploadedFile::fake()->image('second.png'))
        ->assertCount('paymentScreenshots', 2)
        ->call('removeScreenshot', 0)
        ->assertCount('paymentScreenshots', 1)
        ->call('submit')
        ->assertHasNoErrors();

    expect(Registration::sole()->paymentScreenshots)->toHaveCount(1);
});

test('the registrant must confirm their details', function () {
    filledDelegationForm()
        ->set('confirmed', false)
        ->call('submit')
        ->assertHasErrors(['confirmed' => 'accepted']);
});

test('continue never blocks, and flags what is missing on the steps left behind', function () {
    $component = Livewire::test('pages::registration.create')
        ->assertSet('step', 1)
        ->assertSeeText('You can move between them freely')
        ->call('next')
        ->assertSet('step', 2)
        ->assertHasErrors(['contact.organisation_name', 'contact.email'])
        ->assertHasNoErrors(['delegates', 'paymentScreenshots'])
        ->assertSeeText('Some details on Step 1 (Details) still need to be filled in.')
        ->call('next')
        ->assertSet('step', 3)
        ->assertHasErrors(['contact.email', 'delegates'])
        ->assertSeeText('Still needed on Step 1 (Details)')
        ->assertSeeText('Still needed on Step 2 (Delegates)')
        ->assertSeeText('Continue to payment')
        ->assertDontSeeText('Looks good, continue to payment')
        ->call('next')
        ->assertSet('step', 4)
        ->assertHasNoErrors('paymentScreenshots');

    // Fixing a flagged field clears it straight away.
    $component->set('contact', institutionContact())
        ->assertHasNoErrors(['contact.organisation_name', 'contact.email'])
        ->assertHasErrors('delegates');

    $component->call('editStep', 2)
        ->set('delegateCount', 3)
        ->assertHasErrors(['delegates.0.name'])
        ->set('delegates', array_map(fn (int $number) => delegateInput($number), range(1, 3)))
        ->assertHasNoErrors()
        ->call('next')
        ->assertSet('step', 3)
        ->assertSeeText('Check your details')
        ->assertSeeText('Delegate 2')
        ->assertSeeText('Homebridge College')
        ->assertSeeText('Looks good, continue to payment')
        ->call('next')
        ->assertSet('step', 4)
        ->set('newScreenshot', UploadedFile::fake()->image('payment.png'))
        ->set('confirmed', true)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submittedReference', 'HB26-0001')
        ->assertDispatched('registration-draft-cleared');
});

test('a step that has not been visited yet shows no errors', function () {
    Livewire::test('pages::registration.create')
        ->call('editStep', 2)
        ->assertSet('step', 2)
        ->assertHasErrors('contact.name')
        ->assertHasNoErrors(['delegates', 'delegates.0.name'])
        ->assertDontSeeText('Choose how many delegates are in your delegation.');
});

test('going back keeps the answers, and any step can be opened from the step list', function () {
    Livewire::test('pages::registration.create')
        ->set('contact', institutionContact())
        ->call('next')
        ->call('editStep', 4)
        ->assertSet('step', 4)
        ->call('editStep', 9)
        ->assertSet('step', 4)
        ->call('back')
        ->assertSet('step', 3)
        ->call('editStep', 1)
        ->assertSet('step', 1)
        ->assertSet('contact.city', 'Lahore');
});

test('a failed submit returns to the step with the problem', function () {
    Livewire::test('pages::registration.create')
        ->set('step', 4)
        ->set('contact', [...institutionContact(), 'email' => 'not-an-email'])
        ->set('delegates', array_map(fn (int $number) => delegateInput($number), range(1, 3)))
        ->set('newScreenshot', UploadedFile::fake()->image('payment.png'))
        ->set('confirmed', true)
        ->call('submit')
        ->assertHasErrors('contact.email')
        ->assertSet('step', 1)
        ->assertSeeText('Please fix the highlighted fields above before submitting.');
});

test('submitting with gaps everywhere lands on the first one and flags every step', function () {
    Livewire::test('pages::registration.create')
        ->call('editStep', 4)
        ->call('submit')
        ->assertSet('step', 1)
        ->assertHasErrors(['contact.name', 'delegates', 'paymentScreenshots', 'confirmed'])
        ->assertSet('submittedReference', null);

    expect(Registration::count())->toBe(0);
});

test('a saved draft is restored to the step it was on, flagging what is missing', function () {
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
        ->assertSet('step', 4)
        ->assertSet('delegates.1.name', '')
        ->assertHasErrors('delegates.1.name')
        ->assertHasNoErrors(['contact.email', 'delegates.0.name', 'paymentScreenshots']);
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

test('a registration with an ambassador’s code is linked to them', function () {
    $ambassador = CampusAmbassador::factory()->create(['code' => 'HB-007']);

    filledDelegationForm()
        ->set('contact.referral_code', 'hb7')
        ->call('submit')
        ->assertHasNoErrors();

    expect(Registration::sole()->campus_ambassador_id)->toBe($ambassador->id);
});

test('a delegate can ask for a different country in each committee', function () {
    $unw = Committee::where('code', 'UNW')->first();
    $unsc = Committee::where('code', 'UNSC')->first();
    $unw->update(['portfolios' => ['Japan', 'Kenya'], 'portfolios_public' => true]);
    $unsc->update(['portfolios' => ['France', 'China'], 'portfolios_public' => true]);

    filledDelegationForm([['first_committee_id' => (string) $unw->id, 'second_committee_id' => (string) $unsc->id, 'first_preference' => 'Japan', 'second_preference' => 'France']])
        ->call('submit')
        ->assertHasNoErrors();

    $delegate = Delegate::where('email', 'delegate1@gmail.com')->first();

    expect($delegate->first_preference)->toBe('Japan')
        ->and($delegate->second_preference)->toBe('France')
        ->and($delegate->preferenceFor($unw))->toBe('Japan')
        ->and($delegate->preferenceFor($unsc))->toBe('France');
});

test('each committee preference is checked against that committee’s own list', function () {
    $unw = Committee::where('code', 'UNW')->first();
    $unsc = Committee::where('code', 'UNSC')->first();
    $unw->update(['portfolios' => ['Japan'], 'portfolios_public' => true]);
    $unsc->update(['portfolios' => ['France'], 'portfolios_public' => true]);

    filledDelegationForm([['first_committee_id' => (string) $unw->id, 'second_committee_id' => (string) $unsc->id, 'first_preference' => 'France', 'second_preference' => 'France']])
        ->call('submit')
        ->assertHasErrors('delegates.0.first_preference')
        ->assertHasNoErrors('delegates.0.second_preference');
});

test('changing a committee clears the preference picked for it', function () {
    $unw = (string) Committee::where('code', 'UNW')->value('id');

    filledDelegationForm()
        ->set('delegates.0.first_committee_id', $unw)
        ->assertSet('delegates.0.first_preference', '')
        ->assertSet('delegates.0.second_preference', 'Maryam Nawaz');
});

test('the Marvel committee asks for a character from its list once the list is public', function () {
    $special = Committee::where('code', 'SPECIAL')->first();
    $special->update(['name' => 'Marvel Civil War Crisis', 'portfolios' => ['Tony Stark / Iron Man', 'Steve Rogers / Captain America'], 'portfolios_public' => true]);
    $unw = (string) Committee::where('code', 'UNW')->value('id');

    $form = filledDelegationForm([['first_committee_id' => (string) $special->id, 'second_committee_id' => $unw, 'first_preference' => 'Thanos', 'second_preference' => '']])
        ->set('step', 2)
        ->assertSeeText('Marvel Civil War Crisis (SPECIAL)')
        ->assertSeeText('Marvel character')
        ->assertSee('Search, e.g. Tony Stark / Iron Man');

    $form->call('submit')->assertHasErrors('delegates.0.first_preference');

    $form->set('delegates.0.first_preference', 'Steve Rogers / Captain America')
        ->call('submit')
        ->assertHasNoErrors();
});
