<?php

use App\Actions\Staff\ApproveStaffAccess;
use App\Actions\Staff\InviteStaffMember;
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
