<?php

use App\Enums\UserRole;
use App\Http\Middleware\PreviewRole;
use App\Models\Delegate;
use App\Models\User;

test('an admin can view the site as the campus ambassador team', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    $this->actingAs($admin)
        ->post(route('role-preview.store'), ['role' => UserRole::Ambassadors->value])
        ->assertRedirect(route('dashboard'));

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Viewing as Campus ambassador management')
        ->assertSee(route('staff.ambassadors'))
        ->assertDontSee(route('staff.team'));

    $this->get(route('staff.ambassadors'))->assertOk();
    $this->get(route('staff.registrations.index'))->assertForbidden();
    $this->get(route('staff.team'))->assertForbidden();

    expect($admin->fresh()->role)->toBe(UserRole::Admin);
});

test('an admin can switch roles while previewing and go back to admin', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    $this->actingAs($admin)->post(route('role-preview.store'), ['role' => UserRole::Ambassadors->value]);
    $this->post(route('role-preview.store'), ['role' => UserRole::Registrations->value])->assertRedirect();

    $this->get(route('staff.registrations.index'))->assertOk();

    $this->delete(route('role-preview.destroy'))->assertRedirect(route('dashboard'));

    $this->get(route('staff.team'))->assertOk();
    expect(session()->has(PreviewRole::SESSION_ROLE))->toBeFalse();
});

test('an admin can view a specific delegate’s dashboard', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $delegate = Delegate::factory()->create(['name' => 'Ayesha Preview']);

    $this->actingAs($admin)->post(route('role-preview.store'), [
        'role' => UserRole::Delegate->value,
        'delegate' => $delegate->id,
    ]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Viewing as Delegate')
        ->assertSee('Ayesha Preview');
});

test('admin cannot be chosen as a preview role', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    $this->actingAs($admin)
        ->post(route('role-preview.store'), ['role' => UserRole::Admin->value])
        ->assertSessionHasErrors('role');
});

test('only admins can view the site as another role', function () {
    $staff = User::factory()->role(UserRole::Registrations)->create();

    $this->actingAs($staff)
        ->post(route('role-preview.store'), ['role' => UserRole::Ambassadors->value])
        ->assertForbidden();

    $this->withSession([PreviewRole::SESSION_ROLE => UserRole::Ambassadors->value])
        ->get(route('staff.registrations.index'))
        ->assertOk();
});
