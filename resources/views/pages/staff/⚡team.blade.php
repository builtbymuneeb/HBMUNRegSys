<?php

use App\Actions\Staff\ApproveStaffAccess;
use App\Actions\Staff\InviteStaffMember;
use App\Enums\PageAccess;
use App\Enums\StaffPage;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Team')] class extends Component {
    public string $newName = '';

    public string $newEmail = '';

    public string $newRole = 'registrations';

    /** The member whose page access is being edited. */
    public ?int $accessUserId = null;

    /** @var array<string, string> page => '' (no access), 'view' or 'edit' */
    public array $access = [];

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function pendingRequests(): Collection
    {
        return User::where('role', UserRole::Applicant)->oldest()->get();
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function members(): Collection
    {
        return User::whereIn('role', UserRole::staffRoles())->orderBy('role')->orderBy('name')->get();
    }

    public function approve(int $userId, ApproveStaffAccess $approveStaffAccess): void
    {
        Gate::authorize('manage-staff');

        $applicant = User::where('role', UserRole::Applicant)->findOrFail($userId);
        $approveStaffAccess->handle($applicant, Auth::user());

        Flux::toast(variant: 'success', text: __(':name approved as :role.', ['name' => $applicant->name, 'role' => $applicant->role->label()]));
    }

    public function decline(int $userId): void
    {
        Gate::authorize('manage-staff');

        $applicant = User::where('role', UserRole::Applicant)->findOrFail($userId);
        AuditLog::record('staff_access_declined', $applicant, Auth::user(), ['email' => $applicant->email]);
        $applicant->delete();

        Flux::toast(text: __('Request from :name declined.', ['name' => $applicant->name]));
    }

    public function changeRole(int $userId, string $role): void
    {
        Gate::authorize('manage-staff');

        $member = User::whereIn('role', UserRole::staffRoles())->findOrFail($userId);
        $newRole = UserRole::from($role);

        abort_unless($newRole->isStaff(), 422);

        if ($member->isAdmin() && $newRole !== UserRole::Admin && $this->isLastAdmin($member)) {
            Flux::toast(variant: 'danger', text: __('There must be at least one admin.'));
            unset($this->members);

            return;
        }

        $member->forceFill(['role' => $newRole])->save();
        AuditLog::record('staff_role_changed', $member, Auth::user(), ['role' => $newRole->value]);

        Flux::toast(variant: 'success', text: __(':name is now :role.', ['name' => $member->name, 'role' => $newRole->label()]));
    }

    public function remove(int $userId): void
    {
        Gate::authorize('manage-staff');

        $member = User::whereIn('role', UserRole::staffRoles())->findOrFail($userId);

        if ($member->is(Auth::user())) {
            Flux::toast(variant: 'danger', text: __('You can’t remove yourself.'));

            return;
        }

        AuditLog::record('staff_removed', $member, Auth::user(), ['email' => $member->email, 'role' => $member->role->value]);
        $member->delete();

        Flux::toast(text: __(':name was removed from the team.', ['name' => $member->name]));
    }

    public function invite(InviteStaffMember $inviteStaffMember): void
    {
        Gate::authorize('manage-staff');

        $validated = $this->validate([
            'newName' => ['required', 'string', 'max:255'],
            'newEmail' => ['required', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'newRole' => ['required', Rule::in(array_map(fn (UserRole $role) => $role->value, UserRole::staffRoles()))],
        ], attributes: ['newName' => 'name', 'newEmail' => 'email', 'newRole' => 'role']);

        $member = $inviteStaffMember->handle($validated['newName'], $validated['newEmail'], UserRole::from($validated['newRole']), Auth::user());

        $this->reset('newName', 'newEmail', 'newRole');
        Flux::modal('add-member')->close();
        Flux::toast(variant: 'success', text: __(':name added. They’ve been emailed a link to set their password.', ['name' => $member->name]));
    }

    public function openAccess(int $userId): void
    {
        Gate::authorize('manage-staff');

        $member = $this->grantableMember($userId);

        $this->accessUserId = $member->id;
        $this->access = collect(StaffPage::cases())
            ->mapWithKeys(fn (StaffPage $page) => [$page->value => $member->grantedPageAccess($page)?->value ?? ''])
            ->all();

        $this->resetValidation();
        Flux::modal('page-access')->show();
    }

    public function saveAccess(): void
    {
        Gate::authorize('manage-staff');

        $member = $this->grantableMember((int) $this->accessUserId);

        $grants = [];

        foreach (StaffPage::cases() as $page) {
            $level = PageAccess::tryFrom((string) ($this->access[$page->value] ?? ''));

            if ($level === null) {
                continue;
            }

            // The email log has nothing to edit, so the most it can be is view only.
            $grants[$page->value] = $page->isEditable() ? $level->value : PageAccess::View->value;
        }

        $member->forceFill(['page_access' => $grants ?: null])->save();
        AuditLog::record('staff_page_access_changed', $member, Auth::user(), ['pages' => $grants]);

        Flux::modal('page-access')->close();
        unset($this->members);
        Flux::toast(variant: 'success', text: $grants
            ? __(':name can now open :count extra :pages.', ['name' => $member->name, 'count' => count($grants), 'pages' => count($grants) === 1 ? __('page') : __('pages')])
            : __(':name has no extra pages now.', ['name' => $member->name]));
    }

    #[Computed]
    public function accessMember(): ?User
    {
        return $this->accessUserId ? User::find($this->accessUserId) : null;
    }

    /**
     * Admins and the registrations team already have every page, so only the other
     * roles get pages opened up one by one.
     */
    private function grantableMember(int $userId): User
    {
        $member = User::whereIn('role', UserRole::staffRoles())->findOrFail($userId);

        abort_unless(self::canGrant($member), 422);

        return $member;
    }

    public static function canGrant(User $member): bool
    {
        return in_array($member->role, [UserRole::OtherManagement, UserRole::Ambassadors], true);
    }

    private function isLastAdmin(User $admin): bool
    {
        return User::where('role', UserRole::Admin)->whereKeyNot($admin->id)->doesntExist();
    }
}; ?>

<div class="flex w-full flex-col gap-8 p-6 lg:p-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Team') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Approve staff requests, add members and choose what each person can do.') }}</flux:text>
        </div>
        <flux:modal.trigger name="add-member">
            <flux:button variant="primary" icon="user-plus" data-test="open-add-member">{{ __('Add member') }}</flux:button>
        </flux:modal.trigger>
    </div>

    {{-- Pending requests --}}
    <section class="flex flex-col gap-3">
        <div class="flex items-center gap-2">
            <flux:heading size="lg" level="2">{{ __('Access requests') }}</flux:heading>
            @if ($this->pendingRequests->isNotEmpty())
                <flux:badge size="sm" color="amber">{{ $this->pendingRequests->count() }}</flux:badge>
            @endif
        </div>

        @forelse ($this->pendingRequests as $applicant)
            <flux:card wire:key="request-{{ $applicant->id }}" class="flex flex-wrap items-center justify-between gap-4">
                <div class="min-w-0">
                    <div class="font-medium text-zinc-800 dark:text-white">{{ $applicant->name }}</div>
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ $applicant->email }} · {{ __('asked :when', ['when' => $applicant->created_at->diffForHumans()]) }}</div>
                    <flux:badge size="sm" class="mt-2">{{ $applicant->requested_role?->label() }}</flux:badge>
                </div>
                <div class="flex gap-2">
                    <flux:button size="sm" variant="ghost" wire:click="decline({{ $applicant->id }})" wire:confirm="{{ __('Decline and delete this request?') }}">{{ __('Decline') }}</flux:button>
                    <flux:button size="sm" variant="primary" wire:click="approve({{ $applicant->id }})" data-test="approve-{{ $applicant->id }}">{{ __('Approve') }}</flux:button>
                </div>
            </flux:card>
        @empty
            <flux:text>{{ __('No pending requests. People can ask to join at :url', ['url' => route('staff.join')]) }}</flux:text>
        @endforelse
    </section>

    {{-- Members --}}
    <section class="flex flex-col gap-3">
        <flux:heading size="lg" level="2">{{ __('Members (:count)', ['count' => $this->members->count()]) }}</flux:heading>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Email') }}</flux:table.column>
                <flux:table.column>{{ __('Role') }}</flux:table.column>
                <flux:table.column>{{ __('Extra pages') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->members as $member)
                    <flux:table.row :key="$member->id">
                        <flux:table.cell variant="strong">
                            {{ $member->name }}
                            @if ($member->is(auth()->user()))
                                <flux:badge size="sm" class="ms-1">{{ __('You') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $member->email }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:select size="sm" class="max-w-52" wire:change="changeRole({{ $member->id }}, $event.target.value)" :aria-label="__('Role for :name', ['name' => $member->name])">
                                @foreach (UserRole::staffRoles() as $role)
                                    <flux:select.option :value="$role->value" :selected="$member->role === $role">{{ $role->label() }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($this::canGrant($member))
                                <div class="flex flex-wrap items-center gap-1">
                                    @foreach (StaffPage::cases() as $page)
                                        @if ($level = $member->grantedPageAccess($page))
                                            <flux:badge size="sm" :color="$level === PageAccess::Edit ? 'purple' : 'zinc'" :icon="$level === PageAccess::Edit ? 'pencil-square' : 'eye'">{{ __($page->label()) }}</flux:badge>
                                        @endif
                                    @endforeach
                                    <flux:button size="xs" variant="ghost" icon="key" wire:click="openAccess({{ $member->id }})" data-test="page-access-{{ $member->id }}">{{ __('Pages') }}</flux:button>
                                </div>
                            @else
                                <flux:text class="text-xs">{{ __('All pages') }}</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            @unless ($member->is(auth()->user()))
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="remove({{ $member->id }})" wire:confirm="{{ __('Remove :name from the team? They will lose access immediately.', ['name' => $member->name]) }}" :aria-label="__('Remove :name', ['name' => $member->name])" />
                            @endunless
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </section>

    {{-- What each role can do --}}
    <section class="flex flex-col gap-3">
        <flux:heading size="lg" level="2">{{ __('Roles') }}</flux:heading>
        <div class="grid gap-3 md:grid-cols-3">
            @foreach (UserRole::staffRoles() as $role)
                <flux:card class="flex flex-col gap-1">
                    <flux:heading>{{ $role->label() }}</flux:heading>
                    <flux:text>{{ $role->description() }}</flux:text>
                </flux:card>
            @endforeach
        </div>
    </section>

    <flux:modal name="page-access" class="w-full md:w-xl">
        <form wire:submit="saveAccess" class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ __('Page access for :name', ['name' => $this->accessMember?->name]) }}</flux:heading>
                <flux:text class="mt-2">{{ __('Open single pages to this member on top of their role. “View only” lets them look without changing anything; “Can edit” lets them make changes.') }}</flux:text>
            </div>

            <div class="flex flex-col divide-y divide-zinc-200 dark:divide-white/10">
                @foreach (StaffPage::cases() as $page)
                    <div wire:key="access-{{ $page->value }}" class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div class="min-w-0 flex-1">
                            <div class="font-medium text-zinc-800 dark:text-white">{{ __($page->label()) }}</div>
                            <flux:text class="text-xs">{{ __($page->editDescription()) }}</flux:text>
                        </div>
                        <flux:radio.group wire:model="access.{{ $page->value }}" variant="segmented" size="sm" :aria-label="__('Access to :page', ['page' => __($page->label())])">
                            <flux:radio value="" :label="__('None')" />
                            <flux:radio value="view" :label="__('View only')" />
                            @if ($page->isEditable())
                                <flux:radio value="edit" :label="__('Can edit')" />
                            @endif
                        </flux:radio.group>
                    </div>
                @endforeach
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" data-test="save-page-access">{{ __('Save access') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="add-member" class="md:w-md">
        <form wire:submit="invite" class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ __('Add a team member') }}</flux:heading>
                <flux:text class="mt-2">{{ __('They’ll get an email with a link to set their password.') }}</flux:text>
            </div>
            <flux:input wire:model="newName" :label="__('Full name')" />
            <flux:input wire:model="newEmail" :label="__('Email')" type="email" />
            <flux:select wire:model="newRole" :label="__('Role')">
                @foreach (UserRole::staffRoles() as $role)
                    <flux:select.option :value="$role->value">{{ $role->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Add member') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
