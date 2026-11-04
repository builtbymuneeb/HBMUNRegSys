{{-- An ambassador's points, rank and referral link, shown on their home page and the tasks page. --}}
@php($standing = \App\Support\Leaderboard::standingOf($ambassador))
@php($boardSize = \App\Models\CampusAmbassador::count())
@php($rate = \App\Models\Setting::pointsPerVerifiedDelegate())

<div class="hb-hero relative overflow-hidden rounded-[20px] p-4 text-white lg:p-6" data-test="ambassador-summary">
    <div class="pointer-events-none absolute -top-12 -right-12 size-44 rounded-full bg-[radial-gradient(circle,rgb(255_255_255/0.18),transparent_70%)]"></div>
    <div class="relative flex flex-wrap items-end justify-between gap-4">
        <div>
            <div class="text-[10.5px] font-semibold tracking-[0.16em] text-white/75 uppercase">{{ __('Your points') }}</div>
            <div class="mt-1 font-display text-5xl leading-none font-semibold" data-test="ambassador-total">{{ number_format($standing['total'] ?? 0) }}</div>
        </div>
        <a href="{{ route('ambassador.leaderboard') }}" wire:navigate class="rounded-2xl bg-white/15 px-4 py-2.5 text-right hover:bg-white/20">
            <div class="text-[10.5px] font-semibold tracking-[0.16em] text-white/75 uppercase">{{ __('Rank') }}</div>
            <div class="font-display text-2xl leading-tight font-semibold">#{{ $standing['rank'] ?? '—' }} <span class="text-sm font-medium text-white/70">{{ __('of :count', ['count' => $boardSize]) }}</span></div>
        </a>
    </div>
    <div class="relative mt-4 grid grid-cols-3 gap-2 text-sm">
        <div class="rounded-xl bg-white/10 px-3 py-2">
            <div class="font-semibold">{{ number_format($standing['task_points'] ?? 0) }}</div>
            <div class="text-xs text-white/75">{{ trans_choice(':count task|:count tasks', $standing['tasks_done'] ?? 0) }}</div>
        </div>
        <div class="rounded-xl bg-white/10 px-3 py-2">
            <div class="font-semibold">{{ number_format($standing['referral_points'] ?? 0) }}</div>
            <div class="text-xs text-white/75">{{ trans_choice(':count verified delegate|:count verified delegates', $standing['verified_delegates'] ?? 0) }}</div>
        </div>
        <div class="rounded-xl bg-white/10 px-3 py-2">
            <div class="font-semibold">{{ ($standing['bonus_points'] ?? 0) > 0 ? '+' : '' }}{{ number_format($standing['bonus_points'] ?? 0) }}</div>
            <div class="text-xs text-white/75">{{ __('Bonus') }}</div>
        </div>
    </div>
</div>

<flux:card class="flex flex-col gap-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <flux:heading>{{ __('Your referral code') }}</flux:heading>
            <flux:text class="text-sm">
                @if ($rate > 0)
                    {{ __('Every verified delegate who registers with it earns you :points points.', ['points' => $rate]) }}
                @else
                    {{ __('Delegates who register with it count towards your total.') }}
                @endif
            </flux:text>
        </div>
        <flux:badge color="purple" size="lg">{{ $ambassador->code }}</flux:badge>
    </div>
    <div class="flex gap-2" x-data="{ copied: false }">
        <flux:input readonly :value="$ambassador->registrationLink()" class="flex-1" />
        <flux:button icon="clipboard" x-on:click="navigator.clipboard.writeText(@js($ambassador->registrationLink())); copied = true; setTimeout(() => copied = false, 2000)">
            <span x-text="copied ? @js(__('Copied')) : @js(__('Copy'))">{{ __('Copy') }}</span>
        </flux:button>
    </div>
</flux:card>
