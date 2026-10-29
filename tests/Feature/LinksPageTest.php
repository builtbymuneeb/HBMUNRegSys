<?php

test('the links page points to registration, status and the helplines', function () {
    config([
        'conference.contacts.heads' => [['name' => null, 'phone' => '0300-1234567']],
        'conference.contacts.email' => 'contact@example.com',
        'conference.socials.instagram' => 'https://instagram.com/example',
    ]);

    $this->get(route('links'))
        ->assertOk()
        ->assertSee(route('register'))
        ->assertSee(route('registration.status'))
        ->assertSee('https://wa.me/923001234567')
        ->assertSee('mailto:contact@example.com')
        ->assertSee('https://instagram.com/example')
        ->assertDontSeeText('LinkedIn');
});
