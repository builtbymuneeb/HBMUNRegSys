<?php

test('the links page points to registration, status and the campus ambassador forms', function () {
    config([
        'conference.contacts.email' => 'contact@example.com',
        'conference.socials.instagram' => 'https://instagram.com/example',
    ]);

    $this->get(route('links'))
        ->assertOk()
        ->assertSee(route('register'))
        ->assertSee(route('registration.status'))
        ->assertSee('https://forms.gle/r7Lk6X6m5gQYrUgp7')
        ->assertSee('https://forms.gle/JC5Kqun1i1weDtk7A')
        ->assertSee('mailto:contact@example.com')
        ->assertSee('https://instagram.com/example')
        ->assertDontSee('wa.me')
        ->assertDontSeeText('LinkedIn');
});
