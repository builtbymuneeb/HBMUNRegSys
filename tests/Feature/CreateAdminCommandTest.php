<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('it creates an admin account', function () {
    $this->artisan('admin:create', ['--email' => 'head@example.com', '--name' => 'Head Organiser'])
        ->expectsQuestion('Password', 'Str0ng-Password!')
        ->expectsQuestion('Confirm password', 'Str0ng-Password!')
        ->assertSuccessful();

    $admin = User::where('email', 'head@example.com')->sole();

    expect($admin->role)->toBe(UserRole::Admin)
        ->and($admin->name)->toBe('Head Organiser')
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and(Hash::check('Str0ng-Password!', $admin->password))->toBeTrue();
});

test('it refuses mismatched passwords', function () {
    $this->artisan('admin:create', ['--email' => 'head@example.com', '--name' => 'Head Organiser'])
        ->expectsQuestion('Password', 'Str0ng-Password!')
        ->expectsQuestion('Confirm password', 'different')
        ->assertFailed();

    expect(User::where('email', 'head@example.com')->exists())->toBeFalse();
});

test('it promotes an existing account after confirmation', function () {
    $user = User::factory()->role(UserRole::Registrations)->create();

    $this->artisan('admin:create', ['--email' => $user->email])
        ->expectsConfirmation("{$user->email} already has an account (Registrations team). Make it an admin?", 'yes')
        ->assertSuccessful();

    expect($user->refresh()->role)->toBe(UserRole::Admin);
});
