<?php

use App\Models\AuditLog;
use App\Models\ScheduleItem;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Schedule')] class extends Component {
    public ?int $editingId = null;

    public string $day = '1';

    public string $startTime = '';

    public string $endTime = '';

    public string $activity = '';

    public string $link = '';

    public bool $linkVisible = false;

    /**
     * Rows grouped by day number.
     *
     * @return Collection<int, \Illuminate\Database\Eloquent\Collection<int, ScheduleItem>>
     */
    #[Computed]
    public function days(): Collection
    {
        return ScheduleItem::ordered()->get()->groupBy('day');
    }

    public function add(?int $day = null): void
    {
        Gate::authorize('manage-conference');

        $this->resetValidation();
        $this->reset('editingId', 'startTime', 'endTime', 'activity', 'link', 'linkVisible');

        $day ??= (int) ($this->days->keys()->max() ?? 1);
        $last = $this->days->get($day)?->last();
        $this->day = (string) $day;
        $this->startTime = $last?->ends_at ? $last->endInput() : '10:00';

        Flux::modal('schedule-item')->show();
    }

    public function edit(int $id): void
    {
        Gate::authorize('manage-conference');

        $item = ScheduleItem::findOrFail($id);

        $this->resetValidation();
        $this->editingId = $item->id;
        $this->day = (string) $item->day;
        $this->startTime = $item->startInput();
        $this->endTime = $item->endInput();
        $this->activity = $item->activity;
        $this->link = (string) $item->link;
        $this->linkVisible = $item->link_visible;

        Flux::modal('schedule-item')->show();
    }

    public function save(): void
    {
        Gate::authorize('manage-conference');

        $validated = $this->validate([
            'day' => ['required', 'integer', 'between:1,9'],
            'startTime' => ['required', 'date_format:H:i'],
            'endTime' => ['nullable', 'date_format:H:i', 'after:startTime'],
            'activity' => ['required', 'string', 'max:120'],
            'link' => ['nullable', 'url:http,https', 'max:500'],
            'linkVisible' => ['boolean'],
        ], attributes: ['startTime' => 'start time', 'endTime' => 'end time']);

        $attributes = [
            'day' => (int) $validated['day'],
            'starts_at' => $validated['startTime'],
            'ends_at' => $validated['endTime'] ?: null,
            'activity' => $validated['activity'],
            'link' => $validated['link'] ?: null,
            'link_visible' => $validated['linkVisible'],
        ];

        $item = $this->editingId
            ? tap(ScheduleItem::findOrFail($this->editingId))->update($attributes)
            : ScheduleItem::create($attributes);

        AuditLog::record($this->editingId ? 'schedule_item_updated' : 'schedule_item_added', $item, Auth::user(), ['activity' => $item->activity, 'day' => $item->day]);

        Flux::modal('schedule-item')->close();
        Flux::toast(variant: 'success', text: __(':activity saved.', ['activity' => $item->activity]));
        $this->reset('editingId', 'startTime', 'endTime', 'activity', 'link', 'linkVisible');
        unset($this->days);
    }

    public function toggleLink(int $id): void
    {
        Gate::authorize('manage-conference');

        $item = ScheduleItem::findOrFail($id);
        $item->update(['link_visible' => ! $item->link_visible]);

        AuditLog::record($item->link_visible ? 'schedule_link_shown' : 'schedule_link_hidden', $item, Auth::user(), ['activity' => $item->activity, 'day' => $item->day]);
        Flux::toast(text: $item->link_visible
            ? __('Delegates can now see the :activity link.', ['activity' => $item->activity])
            : __(':activity link hidden from delegates.', ['activity' => $item->activity]));
        unset($this->days);
    }

    /**
     * Show or hide every link on one day, or on all days when $day is null.
     */
    public function setAllLinks(bool $visible, ?int $day = null): void
    {
        Gate::authorize('manage-conference');

        $items = ScheduleItem::query()
            ->whereNotNull('link')
            ->where('link_visible', ! $visible)
            ->when($day, fn ($query) => $query->where('day', $day))
            ->get();

        foreach ($items as $item) {
            $item->update(['link_visible' => $visible]);
            AuditLog::record($visible ? 'schedule_link_shown' : 'schedule_link_hidden', $item, Auth::user(), ['activity' => $item->activity, 'day' => $item->day]);
        }

        Flux::toast(text: $visible
            ? trans_choice(':count link is now visible to delegates.|:count links are now visible to delegates.', $items->count())
            : trans_choice(':count link hidden from delegates.|:count links hidden from delegates.', $items->count()));
        unset($this->days);
    }

    public function delete(int $id): void
    {
        Gate::authorize('manage-conference');

        $item = ScheduleItem::findOrFail($id);
        AuditLog::record('schedule_item_deleted', $item, Auth::user(), ['activity' => $item->activity, 'day' => $item->day]);
        $item->delete();

        Flux::toast(text: __(':activity removed.', ['activity' => $item->activity]));
        unset($this->days);
    }
}; ?>

<div class="flex w-full flex-col gap-6 p-6 lg:p-8">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading size="xl" level="1">{{ __('Schedule') }}</flux:heading>
            <flux:text class="mt-1">{{ __('The timetable shows on every verified delegate’s dashboard. Links stay hidden from delegates until you switch them on.') }}</flux:text>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button icon="eye-slash" wire:click="setAllLinks(false)" wire:confirm="{{ __('Hide every link from delegates?') }}">{{ __('Hide all links') }}</flux:button>
            <flux:button icon="eye" wire:click="setAllLinks(true)" wire:confirm="{{ __('Show every link on every day to delegates?') }}">{{ __('Show all links') }}</flux:button>
            <flux:button variant="primary" icon="plus" wire:click="add">{{ __('Add row') }}</flux:button>
        </div>
    </div>

    @forelse ($this->days as $day => $items)
        <div class="flex flex-col gap-3" wire:key="day-{{ $day }}">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <flux:heading size="lg">{{ __('Day :day', ['day' => $day]) }}</flux:heading>
                <div class="flex flex-wrap gap-1">
                    @if ($items->whereNotNull('link')->isNotEmpty())
                        <flux:button size="sm" variant="ghost" icon="eye-slash" wire:click="setAllLinks(false, {{ $day }})">{{ __('Hide day’s links') }}</flux:button>
                        <flux:button size="sm" variant="ghost" icon="eye" wire:click="setAllLinks(true, {{ $day }})">{{ __('Show day’s links') }}</flux:button>
                    @endif
                    <flux:button size="sm" variant="ghost" icon="plus" wire:click="add({{ $day }})">{{ __('Add to Day :day', ['day' => $day]) }}</flux:button>
                </div>
            </div>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Time') }}</flux:table.column>
                    <flux:table.column>{{ __('Activity') }}</flux:table.column>
                    <flux:table.column>{{ __('Link') }}</flux:table.column>
                    <flux:table.column>{{ __('Delegates see link') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($items as $item)
                        <flux:table.row :key="'item-'.$item->id">
                            <flux:table.cell class="whitespace-nowrap">{{ $item->timeLabel() }}</flux:table.cell>
                            <flux:table.cell class="font-medium text-zinc-800 dark:text-white">{{ $item->activity }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($item->link)
                                    <flux:link :href="$item->link" target="_blank" :title="$item->link" class="inline-flex items-center gap-1">
                                        {{ parse_url($item->link, PHP_URL_HOST) }}
                                        <flux:icon.arrow-top-right-on-square variant="micro" />
                                    </flux:link>
                                @else
                                    <flux:button size="xs" variant="ghost" icon="link" wire:click="edit({{ $item->id }})">{{ __('Add link') }}</flux:button>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($item->link)
                                    <flux:button size="xs" :variant="$item->link_visible ? 'primary' : 'filled'" :icon="$item->link_visible ? 'eye' : 'eye-slash'" wire:click="toggleLink({{ $item->id }})">
                                        {{ $item->link_visible ? __('Visible') : __('Hidden') }}
                                    </flux:button>
                                @else
                                    <span class="text-xs text-zinc-400">—</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <div class="flex justify-end gap-1">
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $item->id }})" :aria-label="__('Change :activity', ['activity' => $item->activity])" />
                                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $item->id }})"
                                        wire:confirm="{{ __('Remove :activity from Day :day?', ['activity' => $item->activity, 'day' => $day]) }}"
                                        :aria-label="__('Remove :activity', ['activity' => $item->activity])" />
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    @empty
        <flux:card class="py-10 text-center">
            <flux:text>{{ __('No schedule yet. Add the first row.') }}</flux:text>
        </flux:card>
    @endforelse

    <flux:modal name="schedule-item" class="md:w-xl">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingId ? __('Change row') : __('Add row') }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-3">
                <flux:input wire:model="day" type="number" min="1" max="9" :label="__('Day')" />
                <flux:input wire:model="startTime" type="time" :label="__('Start time')" />
                <flux:input wire:model="endTime" type="time" :label="__('End time')" :description="__('Optional')" />
            </div>
            <flux:input wire:model="activity" :label="__('Activity')" :placeholder="__('Session 01')" />
            <flux:input wire:model="link" type="url" :label="__('Link')" :description="__('Optional — meeting, form or stream link.')" placeholder="https://meet.google.com/…" />
            <flux:switch wire:model="linkVisible" :label="__('Show this link to delegates')" :description="__('Leave off to keep it hidden until you’re ready.')" />
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ $editingId ? __('Save') : __('Add') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
