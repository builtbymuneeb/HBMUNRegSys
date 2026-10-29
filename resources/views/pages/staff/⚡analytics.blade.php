<?php

use App\Models\PageView;
use App\Models\Registration;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Visitors')] class extends Component {
    /**
     * How far back to count: today, 7, 30 or all.
     */
    #[Url]
    public string $range = '7';

    /**
     * Public pages people can land on, by path.
     *
     * @var array<string, string>
     */
    public const PAGES = [
        '/' => 'Landing page',
        '/register' => 'Registration form',
        '/status' => 'Status check',
        '/links' => 'Link tree',
    ];

    /**
     * The first day counted, in Pakistan time; null for all time.
     */
    private function since(): ?CarbonInterface
    {
        return match ($this->range) {
            'today' => now('Asia/Karachi')->startOfDay(),
            '30' => now('Asia/Karachi')->subDays(29)->startOfDay(),
            'all' => null,
            default => now('Asia/Karachi')->subDays(6)->startOfDay(),
        };
    }

    /**
     * @return Builder<PageView>
     */
    private function views(): Builder
    {
        return PageView::query()->when($this->since(), fn (Builder $query, CarbonInterface $since) => $query->where('viewed_on', '>=', $since->toDateString()));
    }

    #[Computed]
    public function pageViews(): int
    {
        return $this->views()->count();
    }

    #[Computed]
    public function visitors(): int
    {
        return $this->views()->distinct()->count('visitor');
    }

    #[Computed]
    public function formVisitors(): int
    {
        return $this->views()->where('path', '/register')->distinct()->count('visitor');
    }

    #[Computed]
    public function submissions(): int
    {
        return Registration::query()
            ->when($this->since(), fn (Builder $query, CarbonInterface $since) => $query->where('created_at', '>=', $since->setTimezone(config('app.timezone'))))
            ->count();
    }

    /**
     * People on a public page in the last five minutes.
     */
    #[Computed]
    public function onlineNow(): int
    {
        return PageView::where('created_at', '>=', now()->subMinutes(5))->distinct()->count('visitor');
    }

    /**
     * Views and visitors for each day shown on the chart (at least a week, at most 30 days).
     *
     * @return Collection<int, array{date: CarbonInterface, views: int, visitors: int}>
     */
    #[Computed]
    public function days(): Collection
    {
        $days = $this->range === '30' || $this->range === 'all' ? 30 : 7;
        $first = now('Asia/Karachi')->subDays($days - 1)->startOfDay();

        $counts = PageView::query()
            ->where('viewed_on', '>=', $first->toDateString())
            ->groupBy('viewed_on')
            ->select('viewed_on', DB::raw('count(*) as views'), DB::raw('count(distinct visitor) as visitors'))
            ->get()
            ->keyBy(fn (PageView $row) => $row->viewed_on->toDateString());

        return collect(range(0, $days - 1))->map(function (int $offset) use ($first, $counts): array {
            $date = $first->copy()->addDays($offset);
            $row = $counts->get($date->toDateString());

            return ['date' => $date, 'views' => (int) ($row->views ?? 0), 'visitors' => (int) ($row->visitors ?? 0)];
        });
    }

    /**
     * The top values of one column, with views and visitors each.
     *
     * @return Collection<int, object{label: string|null, views: int, visitors: int}>
     */
    public function breakdown(string $column): Collection
    {
        return $this->views()
            ->toBase()
            ->groupBy($column)
            ->select($column.' as label', DB::raw('count(*) as views'), DB::raw('count(distinct visitor) as visitors'))
            ->orderByDesc('visitors')
            ->limit(10)
            ->get();
    }
}; ?>

<div class="flex w-full flex-col gap-6 p-6 lg:p-8" wire:poll.30s>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Visitors') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Visits to the public pages (landing, registration form, status check). Bots, link previews and logged-in staff aren’t counted.') }}</flux:text>
        </div>
        <flux:radio.group wire:model.live="range" variant="segmented" size="sm">
            <flux:radio value="today" :label="__('Today')" />
            <flux:radio value="7" :label="__('7 days')" />
            <flux:radio value="30" :label="__('30 days')" />
            <flux:radio value="all" :label="__('All time')" />
        </flux:radio.group>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <flux:card class="flex flex-col gap-1">
            <flux:text>{{ __('On the site now') }}</flux:text>
            <flux:heading size="xl" class="flex items-center gap-2">
                @if ($this->onlineNow > 0)
                    <span class="size-2 rounded-full bg-green-400"></span>
                @endif
                {{ $this->onlineNow }}
            </flux:heading>
            <flux:text class="text-xs">{{ __('Last 5 minutes') }}</flux:text>
        </flux:card>
        <flux:card class="flex flex-col gap-1">
            <flux:text>{{ __('Visitors') }}</flux:text>
            <flux:heading size="xl">{{ number_format($this->visitors) }}</flux:heading>
            <flux:text class="text-xs">{{ __('Different people') }}</flux:text>
        </flux:card>
        <flux:card class="flex flex-col gap-1">
            <flux:text>{{ __('Page views') }}</flux:text>
            <flux:heading size="xl">{{ number_format($this->pageViews) }}</flux:heading>
            <flux:text class="text-xs">{{ __('Every page opened') }}</flux:text>
        </flux:card>
        <flux:card class="flex flex-col gap-1">
            <flux:text>{{ __('Opened the form') }}</flux:text>
            <flux:heading size="xl">{{ number_format($this->formVisitors) }}</flux:heading>
            <flux:text class="text-xs">{{ __('Visitors on /register') }}</flux:text>
        </flux:card>
        <flux:card class="flex flex-col gap-1">
            <flux:text>{{ __('Submitted') }}</flux:text>
            <flux:heading size="xl">{{ number_format($this->submissions) }}</flux:heading>
            <flux:text class="text-xs">
                @if ($this->formVisitors > 0)
                    {{ __(':percent% of form visitors', ['percent' => round(min(100, $this->submissions / $this->formVisitors * 100))]) }}
                @else
                    {{ __('Registrations received') }}
                @endif
            </flux:text>
        </flux:card>
    </div>

    <flux:card class="flex flex-col gap-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <flux:heading>{{ __('Per day') }}</flux:heading>
            <div class="flex items-center gap-4 text-xs text-zinc-500 dark:text-zinc-400">
                <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-violet-400"></span>{{ __('Visitors') }}</span>
                <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-violet-400/30"></span>{{ __('Page views') }}</span>
            </div>
        </div>

        @php($peak = max(1, $this->days->max('views')))
        <div class="flex h-44 items-end gap-1 sm:gap-1.5">
            @foreach ($this->days as $day)
                <div wire:key="day-{{ $day['date']->toDateString() }}" class="group relative flex h-full min-w-0 flex-1 flex-col justify-end" title="{{ $day['date']->format('D j M') }}: {{ $day['visitors'] }} {{ __('visitors') }}, {{ $day['views'] }} {{ __('views') }}">
                    <div class="relative w-full rounded-t-sm bg-violet-400/30" style="height: {{ $day['views'] / $peak * 100 }}%">
                        <div class="absolute inset-x-0 bottom-0 rounded-t-sm bg-violet-400" style="height: {{ $day['views'] > 0 ? $day['visitors'] / $day['views'] * 100 : 0 }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="flex gap-1 text-[10px] text-zinc-500 sm:gap-1.5 dark:text-zinc-400">
            @foreach ($this->days as $day)
                <div wire:key="label-{{ $day['date']->toDateString() }}" class="min-w-0 flex-1 truncate text-center">
                    @if ($this->days->count() <= 7 || $loop->index % 5 === 0 || $loop->last)
                        {{ $day['date']->format($this->days->count() <= 7 ? 'D j' : 'j M') }}
                    @endif
                </div>
            @endforeach
        </div>
    </flux:card>

    <div class="grid gap-4 lg:grid-cols-2">
        @foreach ([
            'path' => __('Pages'),
            'source' => __('Where they came from'),
            'device' => __('Devices'),
            'browser' => __('Browsers and apps'),
        ] as $column => $heading)
            @php($rows = $this->breakdown($column))
            @php($top = max(1, $rows->max('visitors')))
            <flux:card wire:key="breakdown-{{ $column }}" class="flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <flux:heading>{{ $heading }}</flux:heading>
                    <flux:text class="text-xs">{{ __('Visitors · views') }}</flux:text>
                </div>
                @forelse ($rows as $row)
                    <div class="relative flex items-center justify-between gap-3 overflow-hidden rounded px-2 py-1.5 text-sm">
                        <div class="absolute inset-y-0 left-0 bg-violet-400/15" style="width: {{ $row->visitors / $top * 100 }}%"></div>
                        <span class="relative truncate text-zinc-800 dark:text-white">
                            {{ match ($column) {
                                'path' => $this::PAGES[$row->label] ?? $row->label,
                                'source' => $row->label ? ucfirst($row->label) : __('Direct or app (WhatsApp, Instagram…)'),
                                'device' => ucfirst($row->label),
                                default => $row->label ?? __('Other'),
                            } }}
                        </span>
                        <span class="relative shrink-0 tabular-nums text-zinc-500 dark:text-zinc-400">
                            <span class="font-medium text-zinc-800 dark:text-white">{{ number_format($row->visitors) }}</span> · {{ number_format($row->views) }}
                        </span>
                    </div>
                @empty
                    <flux:text class="py-4 text-center">{{ __('No visits yet.') }}</flux:text>
                @endforelse
            </flux:card>
        @endforeach
    </div>

    <flux:text class="text-xs">
        {{ __('Tip: add ?ref=name to links you share (e.g. hbmun.info/register?ref=insta-story) to see which post brought people in. IP addresses aren’t stored; each visitor is counted by a scrambled code.') }}
    </flux:text>
</div>
