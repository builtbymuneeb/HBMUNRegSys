{{-- The top three ambassadors, shown above the full list. Pass showPoints false to hide their points. --}}
@php($showPoints ??= true)
@php($top = $standings->take(3)->filter(fn ($row) => $row['total'] > 0))
@if ($top->isNotEmpty())
    <div class="grid grid-cols-3 items-end gap-2 lg:gap-4">
        @foreach ([1, 0, 2] as $position)
            @if ($row = $top->get($position))
                <div @class([
                    'flex flex-col items-center gap-1 rounded-2xl p-3 text-center',
                    'hb-hero pb-6 text-white' => $position === 0,
                    'border border-zinc-200 bg-white dark:border-white/8 dark:bg-zinc-900/70' => $position !== 0,
                ])>
                    <span class="text-2xl" aria-hidden="true">{{ ['🥇', '🥈', '🥉'][$position] }}</span>
                    <flux:avatar :name="$row['ambassador']->name" size="sm" />
                    <span class="line-clamp-2 text-sm font-semibold">{{ $row['ambassador']->name }}</span>
                    @if ($showPoints)
                        <span @class(['font-display text-xl font-semibold', 'text-zinc-900 dark:text-white' => $position !== 0])>{{ number_format($row['total']) }}</span>
                    @endif
                </div>
            @else
                <div></div>
            @endif
        @endforeach
    </div>
@endif
