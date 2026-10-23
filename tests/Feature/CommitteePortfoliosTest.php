<?php

use App\Enums\UserRole;
use App\Models\Committee;
use App\Models\Delegate;
use App\Models\Registration;
use App\Models\User;
use Database\Seeders\CommitteeSeeder;
use Livewire\Livewire;

function staffMember(): User
{
    return User::factory()->role(UserRole::Registrations)->create();
}

test('the committees start with their portfolio lists, the special committee kept hidden', function () {
    $this->seed(CommitteeSeeder::class);

    $unsc = Committee::where('code', 'UNSC')->sole();
    $special = Committee::where('code', 'SPECIAL')->sole();

    expect($unsc->portfolios)->toHaveCount(15)->toContain('Pakistan')
        ->and($unsc->portfolios_public)->toBeTrue()
        ->and(Committee::where('code', 'PNA')->sole()->portfolios)->toContain('Maryam Nawaz')
        ->and($special->portfolios)->not->toBeEmpty()
        ->and($special->portfolios_public)->toBeFalse()
        ->and($special->publicPortfolioOptions())->toBeNull();
});

test('reseeding never overwrites a list the team has edited', function () {
    $this->seed(CommitteeSeeder::class);
    Committee::where('code', 'UNSC')->update(['portfolios' => json_encode(['France'])]);

    $this->seed(CommitteeSeeder::class);

    expect(Committee::where('code', 'UNSC')->sole()->portfolios)->toBe(['France']);
});

test('staff save a country list, with loose names matched and repeats dropped', function () {
    $committee = Committee::factory()->create();

    Livewire::actingAs(staffMember())
        ->test('pages::staff.committees.show', ['committee' => $committee])
        ->call('editPortfolios')
        ->set('portfolioList', "usa\nFrance\n  france \n\nturkey")
        ->set('portfoliosPublic', false)
        ->call('savePortfolios')
        ->assertHasNoErrors();

    $committee->refresh();

    expect($committee->portfolios)->toBe(['United States', 'France', 'Türkiye'])
        ->and($committee->portfolios_public)->toBeFalse();
});

test('a country list rejects names that are not countries', function () {
    $committee = Committee::factory()->create(['portfolios' => ['France']]);

    Livewire::actingAs(staffMember())
        ->test('pages::staff.committees.show', ['committee' => $committee])
        ->set('portfolioList', "France\nFrances\nNarnia")
        ->call('savePortfolios')
        ->assertHasErrors('portfolioList');

    expect($committee->refresh()->portfolios)->toBe(['France']);
});

test('a character list takes any names', function () {
    $committee = Committee::factory()->characters()->create();

    Livewire::actingAs(staffMember())
        ->test('pages::staff.committees.show', ['committee' => $committee])
        ->set('portfolioList', "Shehbaz Sharif\nBilawal Bhutto Zardari\nshehbaz sharif")
        ->call('savePortfolios')
        ->assertHasNoErrors();

    expect($committee->refresh()->portfolios)->toBe(['Shehbaz Sharif', 'Bilawal Bhutto Zardari']);
});

test('only names on the committee list can be allocated', function () {
    $committee = Committee::factory()->characters()->create(['portfolios' => ['Shehbaz Sharif', 'Imran Khan']]);
    $delegate = Delegate::factory()->for(Registration::factory()->verified())->create(['user_id' => User::factory()->create()->id]);

    Livewire::actingAs(staffMember())
        ->test('pages::staff.allocations')
        ->call('edit', $delegate->id)
        ->set('committeeId', (string) $committee->id)
        ->set('portfolio', 'Someone Unlisted')
        ->call('save')
        ->assertHasErrors(['portfolio' => 'in'])
        ->set('portfolio', 'Imran Khan')
        ->call('save')
        ->assertHasNoErrors();

    expect($delegate->refresh()->allocated_portfolio)->toBe('Imran Khan');
});

test('a loosely typed preference is matched to the committee list when allocating', function () {
    $committee = Committee::factory()->characters()->create(['portfolios' => ['Maryam Nawaz']]);
    $delegate = Delegate::factory()->for(Registration::factory()->verified())->create([
        'user_id' => User::factory()->create()->id,
        'first_committee_id' => $committee->id,
        'personality_preference' => '  maryam   NAWAZ ',
    ]);

    Livewire::actingAs(staffMember())
        ->test('pages::staff.allocations')
        ->call('edit', $delegate->id)
        ->assertSet('portfolio', 'Maryam Nawaz');
});
