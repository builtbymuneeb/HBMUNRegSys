<?php

use App\Enums\PageAccess;
use App\Enums\StaffPage;
use App\Enums\UserRole;
use App\Models\ScheduleItem;
use App\Models\User;
use Livewire\Livewire;

function otherManagement(array $pages = []): User
{
    $user = User::factory()->role(UserRole::OtherManagement)->create();
    $user->forceFill(['page_access' => $pages ?: null])->save();

    return $user->fresh();
}

test('other management sees no extra staff pages until one is granted', function () {
    $member = otherManagement();

    $this->actingAs($member)->get(route('staff.schedule'))->assertForbidden();
    $this->actingAs($member)->get(route('staff.registrations.index'))->assertForbidden();
    $this->actingAs($member)->get(route('staff.inbox'))->assertOk();
});

test('a view only page opens with a banner, and changes are refused', function () {
    $member = otherManagement(['schedule' => 'view']);
    $item = ScheduleItem::create(['day' => 1, 'starts_at' => '09:00', 'activity' => 'Opening', 'link_visible' => false]);

    $this->actingAs($member)->get(route('staff.schedule'))
        ->assertOk()
        ->assertSee('View only', false);

    $this->actingAs($member)->get(route('staff.allocations'))->assertForbidden();

    Livewire::actingAs($member)->test('pages::staff.schedule')
        ->call('toggleLink', $item->id)
        ->call('delete', $item->id);

    expect($item->fresh())->not->toBeNull()
        ->and($item->fresh()->link_visible)->toBeFalse();
});

test('a page with edit access lets changes through', function () {
    $member = otherManagement(['announcements' => 'edit']);

    $this->actingAs($member)->get(route('staff.announcements'))
        ->assertOk()
        ->assertDontSee('View only: you can look around', false);

    expect($member->canEditPage(StaffPage::Announcements))->toBeTrue()
        ->and($member->canViewPage(StaffPage::Schedule))->toBeFalse();
});

test('view only registrations hides review and refuses verifying', function () {
    $member = otherManagement(['registrations' => 'view']);

    expect($member->canViewRegistrations())->toBeTrue()
        ->and($member->canReviewRegistrations())->toBeFalse();

    $this->actingAs($member)->get(route('staff.registrations.index'))->assertOk();
});

test('the email log can only ever be view only', function () {
    $member = otherManagement(['emails' => 'edit']);

    expect($member->pageAccess(StaffPage::Emails))->toBe(PageAccess::View);
});

test('roles keep their own pages without grants', function () {
    $registrations = User::factory()->role(UserRole::Registrations)->create();
    $ambassadors = User::factory()->role(UserRole::Ambassadors)->create();

    expect($registrations->canEditPage(StaffPage::Schedule))->toBeTrue()
        ->and($ambassadors->canEditPage(StaffPage::Ambassadors))->toBeTrue()
        ->and($ambassadors->canViewPage(StaffPage::Schedule))->toBeFalse();
});

test('grants never apply to delegates', function () {
    $delegate = User::factory()->create();
    $delegate->forceFill(['page_access' => ['schedule' => 'edit']])->save();

    expect($delegate->fresh()->canViewPage(StaffPage::Schedule))->toBeFalse();
});

test('an admin grants pages from the team page', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $member = otherManagement();

    Livewire::actingAs($admin)->test('pages::staff.team')
        ->call('openAccess', $member->id)
        ->set('access.schedule', 'edit')
        ->set('access.registrations', 'view')
        ->call('saveAccess')
        ->assertHasNoErrors();

    $member->refresh();

    expect($member->page_access)->toBe(['registrations' => 'view', 'schedule' => 'edit'])
        ->and($member->canEditPage(StaffPage::Schedule))->toBeTrue()
        ->and($member->canEditPage(StaffPage::Registrations))->toBeFalse();
});

test('only admins can grant pages, and not to the registrations team', function () {
    $member = otherManagement();
    $registrations = User::factory()->role(UserRole::Registrations)->create();
    $admin = User::factory()->role(UserRole::Admin)->create();

    $this->actingAs($registrations)->get(route('staff.team'))->assertForbidden();

    Livewire::actingAs($admin)->test('pages::staff.team')
        ->call('openAccess', $registrations->id)
        ->assertStatus(422);
});
