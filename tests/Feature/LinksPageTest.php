<?php

test('the links page points to registration and status, with campus ambassador applications closed', function () {
    config([
        'conference.contacts.email' => 'contact@example.com',
        'conference.socials.instagram' => 'https://instagram.com/example',
    ]);

    $this->get(route('links'))
        ->assertOk()
        ->assertSee(route('register'))
        ->assertSee(route('registration.status'))
        ->assertDontSeeText('Apply for Campus Ambassador')
        ->assertDontSee(config('conference.ambassador_forms.private'))
        ->assertDontSee(config('conference.ambassador_forms.hb'))
        ->assertSee('mailto:contact@example.com')
        ->assertSee('https://instagram.com/example')
        ->assertDontSeeText('LinkedIn');
});

test('the landing page offers both campus ambassador forms', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSeeText('Apply for Campus Ambassador')
        ->assertSee(config('conference.ambassador_forms.private'))
        ->assertSee(config('conference.ambassador_forms.hb'));
});
