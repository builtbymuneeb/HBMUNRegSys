<x-layouts::app :title="__('Dashboard')">
    @php($user = auth()->user())

    <div class="flex w-full flex-col gap-6 p-6 lg:p-8">
        <div>
            <flux:heading size="xl" level="1">{{ __('Welcome, :name', ['name' => $user->name]) }}</flux:heading>
            <flux:text class="mt-1">{{ $user->role->label() }}</flux:text>
        </div>

        @if ($user->isAwaitingApproval())
            <flux:callout icon="clock" color="amber" :heading="__('Waiting for approval')"
                :text="__('You asked to join as :role. An admin will review your request, and you’ll get an email once you’re approved.', ['role' => $user->requested_role?->label()])" />
        @elseif ($user->isStaff())
            <div class="grid gap-4 md:grid-cols-2">
                @can('viewAny', \App\Models\Registration::class)
                    <flux:card class="flex flex-col items-start gap-3">
                        <flux:heading size="lg">{{ __('Registrations') }}</flux:heading>
                        <flux:text>{{ $user->canReviewRegistrations() ? __('Check payment screenshots and verify or reject them.') : __('View all registrations.') }}</flux:text>
                        <flux:button :href="route('staff.registrations.index')" wire:navigate>{{ __('Open registrations') }}</flux:button>
                    </flux:card>
                @endcan
                @can('manage-conference')
                    <flux:card class="flex flex-col items-start gap-3">
                        <flux:heading size="lg">{{ __('Allocations') }}</flux:heading>
                        <flux:text>{{ __('Give verified delegates a committee and portfolio, then send them out.') }}</flux:text>
                        <flux:button :href="route('staff.allocations')" wire:navigate>{{ __('Open allocations') }}</flux:button>
                    </flux:card>
                    <flux:card class="flex flex-col items-start gap-3">
                        <flux:heading size="lg">{{ __('Committees, guides & announcements') }}</flux:heading>
                        <flux:text>{{ __('Set meeting links and agendas, upload study guides and post updates for delegates.') }}</flux:text>
                        <div class="flex flex-wrap gap-2">
                            <flux:button size="sm" :href="route('staff.committees')" wire:navigate>{{ __('Committees') }}</flux:button>
                            <flux:button size="sm" :href="route('staff.guides')" wire:navigate>{{ __('Study guides') }}</flux:button>
                            <flux:button size="sm" :href="route('staff.announcements')" wire:navigate>{{ __('Announcements') }}</flux:button>
                        </div>
                    </flux:card>
                @endcan
                @can('manage-staff')
                    <flux:card class="flex flex-col items-start gap-3">
                        <flux:heading size="lg">{{ __('Team') }}</flux:heading>
                        <flux:text>{{ __('Approve staff requests and manage roles.') }}</flux:text>
                        <flux:button :href="route('staff.team')" wire:navigate>{{ __('Manage team') }}</flux:button>
                    </flux:card>
                @endcan
            </div>
        @else
            @include('dashboard.delegate')
        @endif
    </div>
</x-layouts::app>
