<?php

use Database\Seeders\CommitteeSeeder;

test('the landing page lists the committees and links to registration', function () {
    $this->seed(CommitteeSeeder::class);

    $this->get(route('home'))
        ->assertOk()
        ->assertSeeText(['United Nations Women', 'United Nations Security Council', 'Pakistan National Assembly', 'Special Committee'])
        ->assertSee(route('register'));
});

test('the landing page has the Homebridge delegation discount popup', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('data-test="hb-discount-dialog"', false)
        ->assertSeeText('40% off for HB delegations')
        ->assertSeeText('A team of 3 from HB pays PKR 2,400 instead of PKR 4,000.');
});
