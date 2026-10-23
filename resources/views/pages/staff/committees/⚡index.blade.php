<?php

use App\Models\AuditLog;
use App\Models\Committee;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Committees')] class extends Component {
    public ?int $editingId = null;

    public string $name = '';

    public string $agenda = '';

    public string $meetingUrl = '';

    public string $meetingNotes = '';

    /**
     * @return Collection<int, Committee>
     */
    #[Computed]
    public function committees(): Collection
    {
        return Committee::ordered()->withCount('allocatedDelegates')->get();
    }

    public function edit(int $committeeId): void
    {
        Gate::authorize('manage-conference');

        $committee = Committee::findOrFail($committeeId);

        $this->resetValidation();
        $this->editingId = $committee->id;
        $this->name = $committee->name;
        $this->agenda = (string) $committee->agenda;
        $this->meetingUrl = (string) $committee->meeting_url;
        $this->meetingNotes = (string) $committee->meeting_notes;

        Flux::modal('edit-committee')->show();
    }

    public function save(): void
    {
        Gate::authorize('manage-conference');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'agenda' => ['nullable', 'string', 'max:2000'],
            'meetingUrl' => ['nullable', 'url:https', 'max:500'],
            'meetingNotes' => ['nullable', 'string', 'max:2000'],
        ], attributes: ['meetingUrl' => 'meeting link', 'meetingNotes' => 'schedule']);

        $committee = Committee::findOrFail($this->editingId);
        $committee->update([
            'name' => $validated['name'],
            'agenda' => $validated['agenda'] ?: null,
            'meeting_url' => $validated['meetingUrl'] ?: null,
            'meeting_notes' => $validated['meetingNotes'] ?: null,
        ]);

        AuditLog::record('committee_updated', $committee, Auth::user());

        Flux::modal('edit-committee')->close();
        Flux::toast(variant: 'success', text: __(':committee updated.', ['committee' => $committee->code]));
        $this->reset('editingId', 'name', 'agenda', 'meetingUrl', 'meetingNotes');
    }
}; ?>

<div class="flex w-full flex-col gap-6 p-6 lg:p-8">
    <div>
        <flux:heading size="xl" level="1">{{ __('Committees') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Agendas, meeting links and session times. Allocated delegates see their own committee’s details on their dashboard.') }}</flux:text>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        @foreach ($this->committees as $committee)
            <flux:card class="flex flex-col gap-3" wire:key="committee-{{ $committee->id }}">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <flux:heading size="lg">{{ $committee->label() }}</flux:heading>
                        <flux:text class="text-xs">
                            {{ trans_choice(':count delegate allocated|:count delegates allocated', $committee->allocated_delegates_count, ['count' => $committee->allocated_delegates_count]) }}
                            · {{ $committee->portfolio_type === \App\Enums\PortfolioType::Character ? __('Delegates play characters') : __('Delegates represent countries') }}
                        </flux:text>
                    </div>
                    <flux:button size="sm" wire:click="edit({{ $committee->id }})">{{ __('Edit') }}</flux:button>
                </div>

                <div>
                    <div class="hb-kicker">{{ __('Agenda') }}</div>
                    <flux:text class="whitespace-pre-line">{{ $committee->agenda ?: __('Not set') }}</flux:text>
                </div>
                <div>
                    <div class="hb-kicker">{{ __('Meeting link') }}</div>
                    @if ($committee->meeting_url)
                        <flux:link :href="$committee->meeting_url" target="_blank" class="break-all">{{ $committee->meeting_url }}</flux:link>
                    @else
                        <flux:text>{{ __('Not set') }}</flux:text>
                    @endif
                </div>
                <div>
                    <div class="hb-kicker">{{ __('Schedule') }}</div>
                    <flux:text class="whitespace-pre-line">{{ $committee->meeting_notes ?: __('Not set') }}</flux:text>
                </div>
            </flux:card>
        @endforeach
    </div>

    <flux:modal name="edit-committee" class="md:w-xl">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ __('Edit committee') }}</flux:heading>
            <flux:input wire:model="name" :label="__('Name')" :description="__('Shown to delegates and on the website.')" />
            <flux:textarea wire:model="agenda" :label="__('Agenda')" rows="3" />
            <flux:input wire:model="meetingUrl" type="url" :label="__('Meeting link')" placeholder="https://meet.google.com/…" />
            <flux:textarea wire:model="meetingNotes" :label="__('Schedule')" rows="4" :placeholder="__('Day 1 (21 Nov): 10:00 am – 1:00 pm…')" />
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
