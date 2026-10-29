<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\StaffAccessApproved;
use App\Notifications\StaffAccessRequested;
use App\Notifications\StaffInvitation;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(fn () => Notification::fake());

test('someone can request staff access and admins are notified', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    Livewire::test('pages::staff.join')
        ->set('name', 'New Volunteer')
        ->set('email', 'volunteer@example.com')
        ->set('password', 'Password123')
        ->set('password_confirmation', 'Password123')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('requested', true);

    $applicant = User::where('email', 'volunteer@example.com')->sole();

    expect($applicant->role)->toBe(UserRole::Applicant)
        ->and($applicant->requested_role)->toBe(UserRole::Registrations);

    Notification::assertSentTo($admin, StaffAccessRequested::class);
});

test('someone can ask to join the campus ambassador team instead', function () {
    Livewire::test('pages::staff.join')
        ->set('name', 'CA Lead')
        ->set('email', 'ca@example.com')
        ->set('password', 'Password123')
        ->set('password_confirmation', 'Password123')
        ->set('role', UserRole::Ambassadors->value)
        ->call('submit')
        ->assertHasNoErrors();

    expect(User::where('email', 'ca@example.com')->sole()->requested_role)->toBe(UserRole::Ambassadors);
});

test('nobody can ask to join as an admin', function () {
    Livewire::test('pages::staff.join')
        ->set('name', 'Sneaky')
        ->set('email', 'sneaky@example.com')
        ->set('password', 'Password123')
        ->set('password_confirmation', 'Password123')
        ->set('role', UserRole::Admin->value)
        ->call('submit')
        ->assertHasErrors('role');

    expect(User::where('email', 'sneaky@example.com')->exists())->toBeFalse();
});

test('an applicant has no staff access until approved', function () {
    $applicant = User::factory()->role(UserRole::Applicant)->create(['requested_role' => UserRole::Registrations]);

    $this->actingAs($applicant);

    $this->get(route('dashboard'))->assertOk()->assertSeeText('Waiting for approval');
    $this->get(route('staff.registrations.index'))->assertForbidden();
    $this->get(route('staff.team'))->assertForbidden();
});

test('only admins can open the team page', function (UserRole $role) {
    $this->actingAs(User::factory()->role($role)->create())
        ->get(route('staff.team'))
        ->assertForbidden();
})->with([UserRole::Registrations, UserRole::Delegate]);

test('an admin approves a request with the requested role', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $applicant = User::factory()->role(UserRole::Applicant)->create(['requested_role' => UserRole::Registrations]);

    Livewire::actingAs($admin)
        ->test('pages::staff.team')
        ->assertSeeText($applicant->email)
        ->call('approve', $applicant->id);

    $applicant->refresh();

    expect($applicant->role)->toBe(UserRole::Registrations)
        ->and($applicant->requested_role)->toBeNull();

    Notification::assertSentTo($applicant, StaffAccessApproved::class);
});

test('an admin can decline a request', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $applicant = User::factory()->role(UserRole::Applicant)->create(['requested_role' => UserRole::Registrations]);

    Livewire::actingAs($admin)->test('pages::staff.team')->call('decline', $applicant->id);

    expect(User::find($applicant->id))->toBeNull();
});

test('an admin adds a member who is emailed a set-password link', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    Livewire::actingAs($admin)
        ->test('pages::staff.team')
        ->set('newName', 'Allocation Lead')
        ->set('newEmail', 'alloc@example.com')
        ->set('newRole', UserRole::Registrations->value)
        ->call('invite')
        ->assertHasNoErrors();

    $member = User::where('email', 'alloc@example.com')->sole();

    expect($member->role)->toBe(UserRole::Registrations);
    Notification::assertSentTo($member, StaffInvitation::class);
});

test('an admin changes a member’s role', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $member = User::factory()->role(UserRole::Registrations)->create();

    Livewire::actingAs($admin)->test('pages::staff.team')->call('changeRole', $member->id, UserRole::Admin->value);

    expect($member->refresh()->role)->toBe(UserRole::Admin);
});

test('the last admin cannot be demoted', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();

    Livewire::actingAs($admin)->test('pages::staff.team')->call('changeRole', $admin->id, UserRole::Registrations->value);

    expect($admin->refresh()->role)->toBe(UserRole::Admin);
});

test('an admin removes a member but not themselves', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $member = User::factory()->role(UserRole::Registrations)->create();

    Livewire::actingAs($admin)
        ->test('pages::staff.team')
        ->call('remove', $member->id)
        ->call('remove', $admin->id);

    expect(User::find($member->id))->toBeNull()
        ->and(User::find($admin->id))->not->toBeNull();
});

test('team actions are refused for non-admins', function () {
    $registrations = User::factory()->role(UserRole::Registrations)->create();
    $member = User::factory()->role(UserRole::Registrations)->create();

    Livewire::actingAs($registrations)
        ->test('pages::staff.team')
        ->call('changeRole', $member->id, UserRole::Admin->value)
        ->assertForbidden();

    expect($member->refresh()->role)->toBe(UserRole::Registrations);
});
