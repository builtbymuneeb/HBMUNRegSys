<?php

use Database\Seeders\CommitteeSeeder;

test('the landing page lists the committees and links to registration', function () {
    $this->seed(CommitteeSeeder::class);

    $this->get(route('home'))
        ->assertOk()
        ->assertSeeText(['United Nations Women', 'United Nations Security Council', 'Pakistan National Assembly', 'Special Committee'])
        ->assertSee(route('register'));
});
