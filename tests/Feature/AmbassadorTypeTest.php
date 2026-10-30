<?php

use App\Actions\Ambassadors\ImportAmbassadors;
use App\Enums\AmbassadorType;
use App\Models\CampusAmbassador;
use App\Notifications\AmbassadorWelcome;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('ambassadors are HB (Homebridge) unless marked private', function () {
    $ambassador = CampusAmbassador::create(['code' => 'HB01', 'name' => 'Existing Student']);

    expect($ambassador->refresh()->type)->toBe(AmbassadorType::Homebridge);
});

test('staff choose the type when adding or editing an ambassador', function () {
    Livewire::actingAs(staffMember())
        ->test('pages::staff.ambassadors.index')
        ->call('add')
        ->assertSet('form.type', 'hb')
        ->set('form.name', 'Other School Student')
        ->set('form.type', 'private')
        ->call('save')
        ->assertHasNoErrors();

    expect(CampusAmbassador::where('name', 'Other School Student')->sole()->type)->toBe(AmbassadorType::Private);

    Livewire::actingAs(staffMember())
        ->test('pages::staff.ambassadors.index')
        ->call('add')
        ->set('form.name', 'Bad Type')
        ->set('form.type', 'teacher')
        ->call('save')
        ->assertHasErrors('form.type');
});

test('private ambassadors get their own PC code series', function () {
    CampusAmbassador::factory()->create(['code' => 'HB34']);
    CampusAmbassador::factory()->private()->create(['code' => 'PC01']);

    Livewire::actingAs(staffMember())
        ->test('pages::staff.ambassadors.index')
        ->call('add')
        ->assertSet('form.code', 'HB35')
        ->set('form.type', 'private')
        ->assertSet('form.code', 'PC02')
        ->set('form.type', 'hb')
        ->assertSet('form.code', 'HB35');

    app(ImportAmbassadors::class)->import([['Full Name'], ['Outside Student']], staffMember(), AmbassadorType::Private);

    expect(CampusAmbassador::where('name', 'Outside Student')->sole()->code)->toBe('PC02')
        ->and(CampusAmbassador::nextCode())->toBe('HB35');
});

test('the list filters by type and shows how many of each there are', function () {
    CampusAmbassador::factory()->count(2)->create(['name' => 'Homebridge Person']);
    CampusAmbassador::factory()->private()->create(['name' => 'Private Person']);

    Livewire::actingAs(staffMember())
        ->test('pages::staff.ambassadors.index')
        ->assertSeeText(['2 HB · 1 Private', 'Homebridge Person', 'Private Person'])
        ->set('type', 'private')
        ->assertSeeText('Private Person')
        ->assertDontSeeText('Homebridge Person');
});

test('a sheet is imported as the type chosen for it', function () {
    $rows = [['Full Name', 'Email'], ['Outside Student', 'outside@example.com']];

    app(ImportAmbassadors::class)->import($rows, staffMember(), AmbassadorType::Private);

    expect(CampusAmbassador::sole()->type)->toBe(AmbassadorType::Private);
});

test('the exported list and the messages can be limited to one type', function () {
    CampusAmbassador::factory()->create(['code' => 'HB01', 'name' => 'Homebridge Person', 'email' => 'hb@example.com']);
    CampusAmbassador::factory()->private()->create(['code' => 'HB02', 'name' => 'Private Person', 'email' => 'private@example.com']);

    $this->actingAs(staffMember())
        ->get(route('staff.ambassadors.list', ['type' => 'private']))
        ->assertOk()
        ->assertSeeText(['Private Person', '1 Private Campus Ambassador'])
        ->assertDontSeeText('Homebridge Person');

    Livewire::actingAs(staffMember())
        ->test('pages::staff.ambassadors.index')
        ->call('openExport')
        ->set('exportType', 'hb')
        ->assertSet('exportFrom', 'HB01')
        ->assertSet('exportTo', 'HB01');

    Notification::fake();

    Livewire::actingAs(staffMember())
        ->test('pages::staff.ambassadors.index')
        ->call('openMessage')
        ->set('messageType', 'private')
        ->assertSeeText('1 ambassador selected.')
        ->call('sendEmails');

    Notification::assertSentOnDemandTimes(AmbassadorWelcome::class, 1);
    Notification::assertSentOnDemand(AmbassadorWelcome::class, fn (AmbassadorWelcome $notification) => $notification->ambassador->name === 'Private Person');
});
