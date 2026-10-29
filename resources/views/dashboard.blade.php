<x-layouts::app :title="__('Dashboard')">
    @php($user = auth()->user())

    <div class="flex w-full flex-col gap-6 p-6 lg:p-8">
        <div class="relative overflow-hidden rounded-lg border border-[#a98bd6]/20 bg-[linear-gradient(120deg,#2a0d4d_0%,#160b24_55%,#0b0810_100%)] p-6 lg:p-8">
            <div class="hb-art hb-art-crest pointer-events-none absolute top-1/2 right-4 hidden h-36 -translate-y-1/2 opacity-75 [mask-image:linear-gradient(to_left,black_55%,transparent)] sm:block lg:right-8 lg:h-40" aria-hidden="true"></div>
            <div class="relative flex flex-col gap-2 sm:pr-36 lg:pr-44">
                <div class="hb-kicker">{{ config('conference.name') }} · {{ config('conference.dates_label') }}</div>
                <h1 class="font-display text-3xl leading-tight font-semibold text-white lg:text-4xl">{{ __('Welcome, :name', ['name' => $user->name]) }}</h1>
                <div class="flex flex-wrap items-center gap-3">
                    <flux:badge size="sm" color="purple">{{ $user->role->label() }}</flux:badge>
                    <span class="font-script text-lg text-hb-lavender italic">{{ __('Different voices. One purpose.') }}</span>
                </div>
            </div>
        </div>

        @if ($user->isAwaitingApproval())
            <flux:callout icon="clock" color="amber" :heading="__('Waiting for approval')"
                :text="__('You asked to join as :role. An admin will review your request, and you’ll get an email once you’re approved.', ['role' => $user->requested_role?->label()])" />
        @elseif ($user->isStaff())
            <div class="grid gap-4 md:grid-cols-2">
                @can('viewAny', \App\Models\Registration::class)
                    <flux:card class="flex flex-col items-start gap-3">
                        <flux:icon name="clipboard-document-check" class="size-6 text-hb-lavender" />
                        <flux:heading size="lg">{{ __('Registrations') }}</flux:heading>
                        <flux:text>{{ $user->canReviewRegistrations() ? __('Check payment screenshots and verify or reject them.') : __('View all registrations.') }}</flux:text>
                        <flux:button :href="route('staff.registrations.index')" wire:navigate>{{ __('Open registrations') }}</flux:button>
                    </flux:card>
                @endcan
                @can('manage-conference')
                    <flux:card class="flex flex-col items-start gap-3">
                        <flux:icon name="user-group" class="size-6 text-hb-lavender" />
                        <flux:heading size="lg">{{ __('Allocations') }}</flux:heading>
                        <flux:text>{{ __('Give verified delegates a committee and portfolio, then send them out.') }}</flux:text>
                        <flux:button :href="route('staff.allocations')" wire:navigate>{{ __('Open allocations') }}</flux:button>
                    </flux:card>
                    <flux:card class="flex flex-col items-start gap-3">
                        <flux:icon name="building-library" class="size-6 text-hb-lavender" />
                        <flux:heading size="lg">{{ __('Committees, guides & announcements') }}</flux:heading>
                        <flux:text>{{ __('Set meeting links and agendas, upload study guides and post updates for delegates.') }}</flux:text>
                        <div class="flex flex-wrap gap-2">
                            <flux:button size="sm" :href="route('staff.committees')" wire:navigate>{{ __('Committees') }}</flux:button>
                            <flux:button size="sm" :href="route('staff.guides')" wire:navigate>{{ __('Study guides') }}</flux:button>
                            <flux:button size="sm" :href="route('staff.announcements')" wire:navigate>{{ __('Announcements') }}</flux:button>
                        </div>
                    </flux:card>
                @endcan
                @can('manage-ambassadors')
                    <flux:card class="flex flex-col items-start gap-3">
                        <flux:icon name="ticket" class="size-6 text-hb-lavender" />
                        <flux:heading size="lg">{{ __('Campus ambassadors') }}</flux:heading>
                        <flux:text>{{ __('Add or import ambassadors and see how many registrations each code brings in.') }}</flux:text>
                        <flux:button :href="route('staff.ambassadors')" wire:navigate>{{ __('Open ambassadors') }}</flux:button>
                    </flux:card>
                @endcan
                @can('manage-staff')
                    <flux:card class="flex flex-col items-start gap-3">
                        <flux:icon name="users" class="size-6 text-hb-lavender" />
                        <flux:heading size="lg">{{ __('Team') }}</flux:heading>
                        <flux:text>{{ __('Approve staff requests and manage roles.') }}</flux:text>
                        <flux:button :href="route('staff.team')" wire:navigate>{{ __('Manage team') }}</flux:button>
                    </flux:card>
                @endcan
            </div>
        @else
            <livewire:delegate-dashboard />
        @endif
    </div>
</x-layouts::app>
