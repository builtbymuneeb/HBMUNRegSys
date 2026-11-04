<?php

use App\Support\Leaderboard;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Leaderboard')] class extends Component {
    #[Computed]
    public function standings(): Collection
    {
        return Leaderboard::standings();
    }
}; ?>

<div class="mx-auto flex w-full max-w-3xl flex-col gap-4 p-6 lg:gap-6 lg:p-8">
    <div>
        <flux:heading size="xl" level="1">{{ __('Leaderboard') }}</flux:heading>
        <flux:text>{{ __('Where every campus ambassador stands, ranked by points from approved tasks and verified delegates.') }}</flux:text>
    </div>

    @include('partials.leaderboard-podium', ['standings' => $this->standings, 'showPoints' => false])

    <div class="hb-list" data-test="leaderboard">
        @foreach ($this->standings as $row)
            @php($isMe = $row['ambassador']->is(Auth::user()->ambassador))
            <div @class(['hb-row', 'bg-purple-500/10 ring-1 ring-purple-500/40 ring-inset' => $isMe]) wire:key="row-{{ $row['ambassador']->id }}" @if ($isMe) data-test="leaderboard-me" @endif>
                <span class="w-8 shrink-0 text-center font-display text-lg font-semibold text-zinc-500">{{ $row['rank'] }}</span>
                <span class="flex min-w-0 flex-1 flex-col">
                    <span class="truncate font-medium text-zinc-800 dark:text-white">{{ $row['ambassador']->name }} @if ($isMe)<span class="text-purple-500">({{ __('you') }})</span>@endif</span>
                    @if ($row['ambassador']->institution)
                        <span class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $row['ambassador']->institution }}</span>
                    @endif
                </span>
                {{-- Ambassadors see positions only, not anyone's points. --}}
            </div>
        @endforeach
    </div>
</div>
