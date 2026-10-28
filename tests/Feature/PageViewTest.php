<?php

use App\Enums\UserRole;
use App\Models\PageView;
use App\Models\Registration;
use App\Models\User;
use Livewire\Livewire;

const PHONE_BROWSER = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Mobile Safari/537.36';

test('a visit to a public page is counted without storing the IP address', function () {
    $this->withHeaders(['User-Agent' => PHONE_BROWSER, 'Referer' => 'https://l.instagram.com/?u=x'])
        ->get(route('register'))
        ->assertOk();

    $view = PageView::sole();

    expect($view->path)->toBe('/register')
        ->and($view->source)->toBe('instagram')
        ->and($view->device)->toBe('mobile')
        ->and($view->browser)->toBe('Chrome')
        ->and($view->visitor)->toHaveLength(32)
        ->and($view->visitor)->not->toContain('127.0.0.1');
});

test('a ref tag on the link is used as the source', function () {
    $this->withHeaders(['User-Agent' => PHONE_BROWSER])->get('/?ref=Insta-Story')->assertOk();

    expect(PageView::sole()->source)->toBe('insta-story');
});

test('the same person opening pages twice is one visitor', function () {
    $this->withHeaders(['User-Agent' => PHONE_BROWSER])->get('/');
    $this->withHeaders(['User-Agent' => PHONE_BROWSER])->get(route('register'));

    expect(PageView::count())->toBe(2)
        ->and(PageView::distinct()->count('visitor'))->toBe(1);
});

test('bots, link previews, prefetches and logged-in users are not counted', function () {
    $this->withHeaders(['User-Agent' => 'Googlebot/2.1 (+http://www.google.com/bot.html)'])->get('/');
    $this->withHeaders(['User-Agent' => 'WhatsApp/2.23.20 A'])->get('/');
    $this->withHeaders(['User-Agent' => PHONE_BROWSER, 'Sec-Purpose' => 'prefetch'])->get('/');
    $this->actingAs(User::factory()->role(UserRole::Admin)->create())
        ->withHeaders(['User-Agent' => PHONE_BROWSER])->get('/');

    expect(PageView::count())->toBe(0);
});

test('only admins can open the visitors page', function () {
    $this->actingAs(User::factory()->role(UserRole::Registrations)->create())
        ->get(route('staff.analytics'))
        ->assertForbidden();

    $this->actingAs(User::factory()->role(UserRole::Admin)->create())
        ->get(route('staff.analytics'))
        ->assertOk();
});

test('the visitors page shows visitor, form and submission counts', function () {
    $today = now('Asia/Karachi')->toDateString();

    foreach (['a', 'b', 'c'] as $visitor) {
        PageView::create(['path' => '/', 'visitor' => $visitor, 'device' => 'mobile', 'viewed_on' => $today]);
    }
    PageView::create(['path' => '/register', 'visitor' => 'a', 'device' => 'mobile', 'viewed_on' => $today]);
    PageView::create(['path' => '/register', 'visitor' => 'b', 'device' => 'desktop', 'viewed_on' => $today]);
    PageView::create(['path' => '/', 'visitor' => 'old', 'device' => 'mobile', 'viewed_on' => now('Asia/Karachi')->subDays(20)->toDateString()]);
    Registration::factory()->create();

    Livewire::actingAs(User::factory()->role(UserRole::Admin)->create())
        ->test('pages::staff.analytics')
        ->assertSet('range', '7')
        ->assertSeeInOrder(['Visitors', '3', 'Page views', '5', 'Opened the form', '2', 'Submitted', '1', '50% of form visitors'])
        ->assertSee('Registration form')
        ->set('range', 'all')
        ->assertSeeInOrder(['Visitors', '4', 'Page views', '6']);
});
