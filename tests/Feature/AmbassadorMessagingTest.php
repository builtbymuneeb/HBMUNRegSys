<?php

use App\Enums\UserRole;
use App\Models\CampusAmbassador;
use App\Models\User;
use App\Models\WhatsAppLog;
use App\Notifications\AmbassadorMessage;
use App\Notifications\AmbassadorWelcome;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(fn () => Notification::fake());

test('staff email one ambassador the welcome with their code and personal registration link', function () {
    $ambassador = CampusAmbassador::factory()->create(['code' => 'HB05', 'name' => 'Ayesha Khan', 'email' => 'ayesha@example.com']);

    Livewire::actingAs(staffMember())
        ->test('pages::staff.ambassadors.show', ['ambassador' => $ambassador])
        ->assertSeeText('Not sent yet')
        ->call('sendWelcomeEmail')
        ->assertSeeText('Sent');

    Notification::assertSentOnDemand(AmbassadorWelcome::class, function (AmbassadorWelcome $notification, array $channels, AnonymousNotifiable $notifiable) {
        $mail = $notification->toMail($notifiable);

        return $notifiable->routes['mail'] === 'ayesha@example.com'
            && $mail->greeting === 'Hello Ayesha,'
            && str_contains(implode(' ', $mail->introLines), '**HB05**')
            && $mail->actionUrl === route('register', ['ref' => 'HB05']);
    });

    expect($ambassador->refresh()->welcome_emailed_at)->not->toBeNull();
});

test('a written email goes to the chosen ambassadors, personalised, skipping anyone without an email', function () {
    $ali = CampusAmbassador::factory()->create(['code' => 'HB01', 'name' => 'Ali Raza', 'email' => 'ali@example.com']);
    CampusAmbassador::factory()->create(['code' => 'HB02', 'name' => 'No Email', 'email' => null]);
    $sara = CampusAmbassador::factory()->create(['code' => 'HB03', 'name' => 'Sara Ahmed', 'email' => 'sara@example.com', 'welcome_emailed_at' => now()]);

    Livewire::actingAs(staffMember())
        ->test('pages::staff.ambassadors.index')
        ->call('openMessage')
        ->set('messageKind', 'custom')
        ->set('messageSubject', 'Deadline for {code}')
        ->set('messageBody', "Registrations close Friday.\n\nShare {link} today, {name}!")
        ->assertSeeText(['3 ambassadors selected.', '1 has no email address.'])
        ->call('sendEmails')
        ->assertHasNoErrors();

    Notification::assertSentOnDemandTimes(AmbassadorMessage::class, 2);
    Notification::assertSentOnDemand(AmbassadorMessage::class, function (AmbassadorMessage $notification, array $channels, AnonymousNotifiable $notifiable) use ($sara) {
        $mail = $notification->toMail($notifiable);

        return $notification->ambassador->is($sara)
            && $mail->subject === 'Deadline for HB03'
            && $mail->introLines === ['Registrations close Friday.', 'Share '.route('register', ['ref' => 'HB03']).' today, Sara!'];
    });

    Livewire::actingAs(staffMember())
        ->test('pages::staff.ambassadors.index')
        ->call('openMessage')
        ->set('messageAudience', 'not_welcomed')
        ->assertSeeText('2 ambassadors selected.')
        ->call('sendEmails');

    Notification::assertSentOnDemandTimes(AmbassadorWelcome::class, 1);
    expect($ali->refresh()->welcome_emailed_at)->not->toBeNull();
});

test('a written email needs a subject and message', function () {
    CampusAmbassador::factory()->create();

    Livewire::actingAs(staffMember())
        ->test('pages::staff.ambassadors.index')
        ->call('openMessage')
        ->set('messageKind', 'custom')
        ->call('sendEmails')
        ->assertHasErrors(['messageSubject', 'messageBody']);

    Notification::assertNothingSent();
});

test('the WhatsApp welcome opens the chat with the message typed in and is recorded', function () {
    $ambassador = CampusAmbassador::factory()->create(['code' => 'HB07', 'name' => 'Bilal Shah', 'phone' => '+92 300 1234567']);
    $staff = staffMember();

    $response = $this->actingAs($staff)->get(route('staff.whatsapp.ambassador.welcome', $ambassador));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toStartWith('https://wa.me/923001234567?text=')
        ->and(rawurldecode($response->headers->get('Location')))->toContain('Hello Bilal,', '*HB07*', route('register', ['ref' => 'HB07']))
        ->and(WhatsAppLog::sole())->kind->toBe(WhatsAppLog::AMBASSADOR_WELCOME)
        ->campus_ambassador_id->toBe($ambassador->id)
        ->sent_by->toBe($staff->id)
        ->and($ambassador->refresh()->welcomeWhatsApp())->not->toBeNull();
});

test('a written WhatsApp message is personalised, and only ambassador staff can open one', function () {
    $ambassador = CampusAmbassador::factory()->create(['code' => 'HB08', 'name' => 'Zara Malik', 'phone' => '03001234567']);

    $response = $this->actingAs(staffMember())->get(route('staff.whatsapp.ambassador.message', ['ambassador' => $ambassador, 'text' => 'Your code {code} has 3 sign-ups!']));

    expect(rawurldecode($response->headers->get('Location')))->toEndWith("Hello Zara,\n\nYour code HB08 has 3 sign-ups!")
        ->and(WhatsAppLog::sole()->kind)->toBe(WhatsAppLog::AMBASSADOR_MESSAGE);

    $this->actingAs(User::factory()->role(UserRole::Delegate)->create())
        ->get(route('staff.whatsapp.ambassador.welcome', $ambassador))
        ->assertForbidden();
});

test('the WhatsApp checklist lists everyone selected with a link to open each chat', function () {
    $first = CampusAmbassador::factory()->create(['code' => 'HB01', 'phone' => '03001234567']);
    CampusAmbassador::factory()->create(['code' => 'HB02', 'phone' => '03001234568']);
    CampusAmbassador::factory()->create(['code' => 'HB03', 'phone' => '03001234569']);

    Livewire::actingAs(staffMember())
        ->test('pages::staff.ambassadors.index')
        ->call('openMessage')
        ->set('messageAudience', 'range')
        ->set('messageFrom', 'HB01')
        ->set('messageTo', 'HB02')
        ->call('showChecklist')
        ->assertSeeHtml(e(route('staff.whatsapp.ambassador.welcome', $first)))
        ->assertSeeText(['HB01', 'HB02'])
        ->assertDontSeeText('HB03 ·');
});

test('an ambassador’s registration link fills their code into the form', function () {
    Livewire::withQueryParams(['ref' => 'hb05'])
        ->test('pages::registration.create')
        ->assertSet('contact.referral_code', 'HB05')
        ->call('restoreDraft', ['contact' => ['name' => 'Saved Draft', 'referral_code' => '']])
        ->assertSet('contact.referral_code', 'HB05')
        ->call('startOver')
        ->assertSet('contact.referral_code', 'HB05');
});
