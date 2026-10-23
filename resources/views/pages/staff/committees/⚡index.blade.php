<?php

use App\Models\Committee;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Committees')] class extends Component {
    /**
     * @return Collection<int, Committee>
     */
    #[Computed]
    public function committees(): Collection
    {
        return Committee::ordered()->withCount(['allocatedDelegates', 'sessions'])->with('sessions')->get();
    }
}; ?>

<div class="flex w-full flex-col gap-6 p-6 lg:p-8">
    <div>
        <flux:heading size="xl" level="1">{{ __('Committees') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Open a committee to add its agenda and sessions, and see who has been allocated to it. Allocated delegates see their own committee’s details on their dashboard.') }}</flux:text>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        @foreach ($this->committees as $committee)
            @php($next = $committee->sessions->first(fn ($session) => $session->isUpcoming()))
            <a href="{{ route('staff.committees.show', $committee) }}" wire:navigate wire:key="committee-{{ $committee->id }}" class="group block rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                <flux:card class="flex h-full flex-col gap-4 transition group-hover:border-zinc-400 dark:group-hover:border-zinc-500">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <flux:heading size="lg">{{ $committee->label() }}</flux:heading>
                            <flux:text class="text-xs">
                                {{ $committee->portfolio_type === \App\Enums\PortfolioType::Character ? __('Delegates play characters') : __('Delegates represent countries') }}
                            </flux:text>
                        </div>
                        <flux:icon.chevron-right class="mt-1 size-5 shrink-0 text-zinc-400 transition group-hover:translate-x-0.5 group-hover:text-zinc-700 dark:group-hover:text-white" />
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <div class="hb-kicker">{{ __('Delegates') }}</div>
                            <flux:heading size="lg">{{ $committee->allocated_delegates_count }}</flux:heading>
                        </div>
                        <div>
                            <div class="hb-kicker">{{ __('Sessions') }}</div>
                            <flux:heading size="lg">{{ $committee->sessions_count }}</flux:heading>
                        </div>
                        <div>
                            <div class="hb-kicker">{{ __('Agenda') }}</div>
                            @if ($committee->agenda)
                                <flux:badge size="sm" color="green" class="mt-1">{{ __('Set') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="amber" class="mt-1">{{ __('Missing') }}</flux:badge>
                            @endif
                        </div>
                    </div>

                    <flux:text class="text-sm">
                        @if ($next)
                            {{ __('Next: :title, :time', ['title' => $next->title, 'time' => $next->timeLabel()]) }}
                        @else
                            {{ __('No upcoming sessions yet.') }}
                        @endif
                    </flux:text>
                </flux:card>
            </a>
        @endforeach
    </div>
</div>
