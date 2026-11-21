<?php

use App\Enums\UserRole;
use App\Models\Committee;
use App\Models\Delegate;
use App\Models\Registration;
use App\Models\User;

function allocateTo(Committee $committee, string $portfolio, string $name): void
{
    Delegate::factory()->for(Registration::factory()->verified())->create([
        'name' => $name,
        'allocated_committee_id' => $committee->id,
        'allocated_portfolio' => $portfolio,
    ]);
}

test('staff download the picked columns of the picked committees as CSV', function () {
    $unw = Committee::factory()->create(['code' => 'UNW']);
    $unsc = Committee::factory()->create(['code' => 'UNSC']);
    allocateTo($unw, 'Pakistan', 'Ayesha Khan');
    allocateTo($unsc, 'France', 'Bilal Ahmed');

    $response = $this->actingAs(User::factory()->role(UserRole::Registrations)->create())
        ->get(route('staff.committees.export', ['format' => 'csv', 'committees' => [$unw->id], 'columns' => ['name', 'portfolio', 'bogus']]))
        ->assertOk();

    $csv = $response->streamedContent();

    // Columns come out in the file's fixed order, unknown ones dropped.
    expect($csv)->toContain('Portfolio,"Delegate name"')
        ->toContain('Pakistan,"Ayesha Khan"')
        ->not->toContain('Bilal Ahmed')
        ->not->toContain('Email');
});

test('the PDF export is a print-ready page of every committee by default', function () {
    $unw = Committee::factory()->create(['code' => 'UNW', 'name' => 'UN Women']);
    allocateTo($unw, 'Pakistan', 'Ayesha Khan');

    $this->actingAs(User::factory()->role(UserRole::Registrations)->create())
        ->get(route('staff.committees.export', ['format' => 'pdf']))
        ->assertOk()
        ->assertSee('Ayesha Khan')
        ->assertSee('Save as PDF')
        ->assertSee('UN Women (UNW)');
});

test('people without the committees page cannot export', function () {
    $this->actingAs(User::factory()->role(UserRole::OtherManagement)->create())
        ->get(route('staff.committees.export'))
        ->assertForbidden();
});

test('both committee pages offer the export', function () {
    $committee = Committee::factory()->create();
    $this->actingAs(User::factory()->role(UserRole::Registrations)->create());

    $this->get(route('staff.committees'))->assertOk()->assertSee('Export delegates');
    $this->get(route('staff.committees.show', $committee))->assertOk()->assertSee('Export delegates');
});
