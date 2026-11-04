<x-layouts::app :title="__('Home')">
    @php($user = auth()->user())
    @php($firstName = \Illuminate\Support\Str::before($user->name, ' '))

    <div class="mx-auto flex w-full max-w-5xl flex-col gap-4 p-6 lg:gap-6 lg:p-8">
        <div class="flex items-center justify-between gap-3 px-1">
            <h1 class="font-display text-[26px] leading-tight font-semibold text-zinc-900 lg:text-4xl dark:text-white">{{ __('Hi, :name', ['name' => $firstName]) }}</h1>
            @if ($user->isStaff())
                <flux:badge size="sm" color="purple">{{ $user->role->label() }}</flux:badge>
            @endif
        </div>

        @if ($user->isAwaitingApproval())
            <flux:callout icon="clock" color="amber" :heading="__('Waiting for approval')"
                :text="__('You asked to join as :role. You’ll get an email once an admin approves you.', ['role' => $user->requested_role?->label()])" />
        @elseif ($user->isStaff())
            @php($daysToGo = max(0, (int) ceil(now()->diffInDays(\Illuminate\Support\Carbon::parse(config('conference.starts_at'), 'Asia/Karachi'), false))))
            @php($tiles = [])
            @if ($user->canViewRegistrations())
                @php($counts = \App\Models\Registration::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'))
                @php($tiles[] = ['value' => $counts['pending'] ?? 0, 'label' => $user->canReviewRegistrations() ? __('To verify') : __('Pending'), 'url' => route('staff.registrations.index', ['status' => 'pending']), 'hot' => ($counts['pending'] ?? 0) > 0])
                @php($tiles[] = ['value' => $counts['verified'] ?? 0, 'label' => __('Verified'), 'url' => route('staff.registrations.index', ['status' => 'verified']), 'hot' => false])
                @php($tiles[] = ['value' => \App\Models\Delegate::whereHas('registration', fn ($query) => $query->where('status', 'verified'))->count(), 'label' => __('Delegates'), 'url' => null, 'hot' => false])
            @elseif ($user->can('manage-ambassadors'))
                @php($tiles[] = ['value' => \App\Models\CampusAmbassador::count(), 'label' => __('Ambassadors'), 'url' => route('staff.ambassadors'), 'hot' => false])
                @php($tiles[] = ['value' => \App\Models\InboxMessage::visibleTo($user)->unread()->count(), 'label' => __('Unread'), 'url' => route('staff.inbox'), 'hot' => false])
            @elseif ($user->can('use-inbox'))
                @php($tiles[] = ['value' => \App\Models\InboxMessage::visibleTo($user)->unread()->count(), 'label' => __('Unread'), 'url' => route('staff.inbox'), 'hot' => false])
            @endif
            @php($tiles[] = ['value' => $daysToGo, 'label' => __('Days to go'), 'url' => null, 'hot' => false])

            <div @class(['grid gap-2 lg:gap-4', 'grid-cols-2 lg:grid-cols-4' => count($tiles) >= 3, 'grid-cols-2 lg:grid-cols-3' => count($tiles) < 3])>
                @foreach ($tiles as $tile)
                    <{{ $tile['url'] ? 'a' : 'div' }} @if ($tile['url']) href="{{ $tile['url'] }}" wire:navigate @endif @class([
                        'rounded-2xl border p-3.5 lg:p-5',
                        'hb-hero border-transparent text-white' => $tile['hot'],
                        'border-zinc-200 bg-white dark:border-white/8 dark:bg-zinc-900/70' => ! $tile['hot'],
                    ])>
                        <div @class(['font-display text-[28px] leading-none font-semibold lg:text-4xl', 'text-zinc-900 dark:text-white' => ! $tile['hot']])>{{ number_format($tile['value']) }}</div>
                        <div @class(['mt-1.5 text-xs font-semibold', 'text-white/80' => $tile['hot'], 'text-zinc-500 dark:text-zinc-400' => ! $tile['hot']])>{{ $tile['label'] }}</div>
                    </{{ $tile['url'] ? 'a' : 'div' }}>
                @endforeach
            </div>

            <div class="hb-list lg:grid lg:grid-cols-2 lg:[&>a]:border-r lg:[&>a:nth-child(2n)]:border-r-0">
                @foreach (\App\Support\AppNavigation::staffItems($user) as $item)
                    <a href="{{ $item['url'] }}" wire:navigate class="hb-row text-[15px] font-medium text-zinc-800 hover:bg-zinc-50 dark:text-white dark:hover:bg-white/4" data-test="home-{{ $item['key'] }}">
                        <span class="hb-icon-tile"><flux:icon :name="$item['icon']" class="size-5" /></span>
                        <span class="flex-1 truncate">{{ $item['label'] }}</span>
                        @if ($item['badge'])
                            <flux:badge size="sm" :color="$item['key'] === 'inbox' ? 'red' : 'amber'">{{ $item['badge'] }}</flux:badge>
                        @endif
                        <flux:icon.chevron-right variant="micro" class="text-zinc-400" />
                    </a>
                @endforeach
            </div>
        @elseif ($user->role === \App\Enums\UserRole::Ambassador)
            @if ($user->ambassador)
                @include('partials.ambassador-home', ['ambassador' => $user->ambassador])
            @else
                <flux:callout icon="exclamation-triangle" color="amber" :heading="__('Your ambassador profile isn’t set up')"
                    :text="__('Please contact the team so they can link your account to your ambassador profile.')" />
            @endif
        @else
            @if ($user->ambassador)
                <a href="{{ route('ambassador.tasks') }}" wire:navigate class="hb-row rounded-2xl border border-zinc-200 bg-white hover:bg-zinc-50 dark:border-white/8 dark:bg-zinc-900/70 dark:hover:bg-white/4" data-test="ambassador-portal-link">
                    <span class="hb-icon-tile"><flux:icon.trophy class="size-5" /></span>
                    <span class="flex-1 text-[15px] font-medium text-zinc-800 dark:text-white">{{ __('Campus ambassador portal') }}</span>
                    <flux:icon.chevron-right variant="micro" class="text-zinc-400" />
                </a>
            @endif
            <livewire:delegate-dashboard />
        @endif
    </div>
</x-layouts::app>
