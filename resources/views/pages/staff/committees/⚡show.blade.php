<?php

use App\Actions\Push\NotifyDelegates;
use App\Enums\PortfolioType;
use App\Models\AuditLog;
use App\Models\Committee;
use App\Models\CommitteeSession;
use App\Models\Delegate;
use App\Support\Countries;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Committee')] class extends Component {
    public Committee $committee;

    public string $agenda = '';

    public string $portfolioList = '';

    public bool $portfoliosPublic = true;

    public ?int $editingSessionId = null;

    public string $sessionTitle = '';

    public string $sessionDate = '';

    public string $startTime = '';

    public string $endTime = '';

    public string $meetingUrl = '';

    /**
     * Mount the component.
     */
    public function mount(Committee $committee): void
    {
        $this->committee = $committee;
    }

    /**
     * @return Collection<int, CommitteeSession>
     */
    #[Computed]
    public function sessions(): Collection
    {
        return $this->committee->sessions()->get();
    }

    /**
     * @return Collection<int, Delegate>
     */
    #[Computed]
    public function delegates(): Collection
    {
        return $this->committee->allocatedDelegates()
            ->with('registration')
            ->orderBy('allocated_portfolio')
            ->get();
    }

    /**
     * Open the agenda form.
     */
    public function editAgenda(): void
    {
        Gate::authorize('manage-conference');

        $this->resetValidation();
        $this->agenda = (string) $this->committee->agenda;

        Flux::modal('committee-agenda')->show();
    }

    /**
     * Save the agenda (blank clears it).
     */
    public function saveAgenda(): void
    {
        Gate::authorize('manage-conference');

        $validated = $this->validate(['agenda' => ['nullable', 'string', 'max:2000']]);

        $this->committee->update(['agenda' => trim($validated['agenda']) ?: null]);

        AuditLog::record('committee_agenda_updated', $this->committee, Auth::user());

        Flux::modal('committee-agenda')->close();
        Flux::toast(variant: 'success', text: __('Agenda saved.'));
    }

    /**
     * Open the portfolio list editor, one name per line.
     */
    public function editPortfolios(): void
    {
        Gate::authorize('manage-conference');

        $this->resetValidation();
        $this->portfolioList = implode("\n", $this->committee->portfolios ?? []);
        $this->portfoliosPublic = $this->committee->portfolios_public;

        Flux::modal('committee-portfolios')->show();
    }

    /**
     * Save the portfolio list. Countries are matched to their listed names ("usa" becomes
     * United States) and repeated names are dropped.
     */
    public function savePortfolios(): void
    {
        Gate::authorize('manage-conference');

        $this->validate([
            'portfolioList' => ['nullable', 'string', 'max:20000'],
            'portfoliosPublic' => ['boolean'],
        ]);

        $names = collect(preg_split('/\R/', $this->portfolioList))
            ->map(fn (string $line) => Str::squish($line))
            ->filter();

        if ($this->committee->portfolio_type === PortfolioType::Country) {
            $unknown = $names->reject(fn (string $name) => Countries::canonical($name));

            if ($unknown->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'portfolioList' => __('Not recognised as countries: :names', ['names' => $unknown->implode(', ')]),
                ]);
            }

            $names = $names->map(fn (string $name) => Countries::canonical($name));
        }

        if ($tooLong = $names->first(fn (string $name) => mb_strlen($name) > 120)) {
            throw ValidationException::withMessages([
                'portfolioList' => __(':name is too long (120 characters at most).', ['name' => Str::limit($tooLong, 40)]),
            ]);
        }

        $names = $names->unique(fn (string $name) => mb_strtolower($name))->values()->all();

        $this->committee->update([
            'portfolios' => $names,
            'portfolios_public' => $this->portfoliosPublic,
        ]);

        AuditLog::record('committee_portfolios_updated', $this->committee, Auth::user(), [
            'count' => count($names),
            'public' => $this->portfoliosPublic,
        ]);

        Flux::modal('committee-portfolios')->close();
        Flux::toast(variant: 'success', text: trans_choice(':count portfolio saved.|:count portfolios saved.', count($names), ['count' => count($names)]));
    }

    /**
     * Open a blank session form, prefilled to follow the last session.
     */
    public function addSession(): void
    {
        Gate::authorize('manage-conference');

        $this->resetValidation();
        $this->editingSessionId = null;

        $last = $this->sessions->last();
        $this->sessionTitle = __('Session :number', ['number' => $this->sessions->count() + 1]);
        $this->sessionDate = $last
            ? $last->localStart()->format('Y-m-d')
            : Carbon::parse(config('conference.starts_at'), CommitteeSession::TIMEZONE)->format('Y-m-d');
        $this->startTime = $last ? $last->localEnd()->format('H:i') : '10:00';
        $this->endTime = '';
        $this->meetingUrl = (string) $last?->meeting_url;

        Flux::modal('committee-session')->show();
    }

    /**
     * Open the form for an existing session.
     */
    public function editSession(int $sessionId): void
    {
        Gate::authorize('manage-conference');

        $session = $this->committee->sessions()->findOrFail($sessionId);

        $this->resetValidation();
        $this->editingSessionId = $session->id;
        $this->sessionTitle = $session->title;
        $this->sessionDate = $session->localStart()->format('Y-m-d');
        $this->startTime = $session->localStart()->format('H:i');
        $this->endTime = $session->localEnd()->format('H:i');
        $this->meetingUrl = (string) $session->meeting_url;

        Flux::modal('committee-session')->show();
    }

    /**
     * Create or update a session.
     */
    public function saveSession(): void
    {
        Gate::authorize('manage-conference');

        $validated = $this->validate([
            'sessionTitle' => ['required', 'string', 'max:120'],
            'sessionDate' => ['required', 'date_format:Y-m-d'],
            'startTime' => ['required', 'date_format:H:i'],
            'endTime' => ['required', 'date_format:H:i', 'after:startTime'],
            'meetingUrl' => ['nullable', 'url:https', 'max:500'],
        ], attributes: [
            'sessionTitle' => 'title',
            'sessionDate' => 'date',
            'startTime' => 'start time',
            'endTime' => 'end time',
            'meetingUrl' => 'meeting link',
        ]);

        $attributes = [
            'title' => $validated['sessionTitle'],
            'starts_at' => Carbon::parse("{$validated['sessionDate']} {$validated['startTime']}", CommitteeSession::TIMEZONE)->utc(),
            'ends_at' => Carbon::parse("{$validated['sessionDate']} {$validated['endTime']}", CommitteeSession::TIMEZONE)->utc(),
            'meeting_url' => $validated['meetingUrl'] ?: null,
        ];

        if ($this->editingSessionId) {
            $session = $this->committee->sessions()->findOrFail($this->editingSessionId);
            $session->update($attributes);
        } else {
            $session = $this->committee->sessions()->create($attributes);
        }

        // Delegates only hear about changes the dashboard shows, not a renamed session.
        if ($session->wasRecentlyCreated || $session->wasChanged(['starts_at', 'ends_at', 'meeting_url'])) {
            app(NotifyDelegates::class)->aboutSession($session, $session->wasRecentlyCreated);
        }

        AuditLog::record($this->editingSessionId ? 'committee_session_updated' : 'committee_session_added', $this->committee, Auth::user(), [
            'session' => $session->title,
        ]);

        Flux::modal('committee-session')->close();
        Flux::toast(variant: 'success', text: __(':title saved.', ['title' => $session->title]));
        $this->reset('editingSessionId', 'sessionTitle', 'sessionDate', 'startTime', 'endTime', 'meetingUrl');
        unset($this->sessions);
    }

    /**
     * Remove a session.
     */
    public function deleteSession(int $sessionId): void
    {
        Gate::authorize('manage-conference');

        $session = $this->committee->sessions()->findOrFail($sessionId);
        $session->delete();

        AuditLog::record('committee_session_removed', $this->committee, Auth::user(), ['session' => $session->title]);

        Flux::toast(text: __(':title removed.', ['title' => $session->title]));
        unset($this->sessions);
    }
}; ?>

<div class="flex w-full flex-col gap-6 p-6 lg:p-8">
    <div class="flex flex-col gap-2">
        <flux:link :href="route('staff.committees')" wire:navigate class="text-sm">← {{ __('All committees') }}</flux:link>
        <flux:heading size="xl" level="1">{{ $committee->label() }}</flux:heading>
        <flux:text>
            {{ trans_choice(':count delegate allocated|:count delegates allocated', $this->delegates->count(), ['count' => $this->delegates->count()]) }}
            · {{ $committee->portfolio_type === \App\Enums\PortfolioType::Character ? __('Delegates play characters') : __('Delegates represent countries') }}
        </flux:text>
    </div>

    {{-- Agenda --}}
    <flux:card class="flex flex-col gap-3">
        <div class="flex items-start justify-between gap-4">
            <div class="hb-kicker">{{ __('Agenda') }}</div>
            @if ($committee->agenda)
                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="editAgenda">{{ __('Change') }}</flux:button>
            @endif
        </div>
        @if ($committee->agenda)
            <flux:text class="whitespace-pre-line text-zinc-800 dark:text-white">{{ $committee->agenda }}</flux:text>
        @else
            <div class="flex flex-wrap items-center justify-between gap-3">
                <flux:text>{{ __('No agenda yet. Delegates see it on their dashboard once it’s added.') }}</flux:text>
                <flux:button size="sm" variant="primary" icon="plus" wire:click="editAgenda">{{ __('Add agenda') }}</flux:button>
            </div>
        @endif
    </flux:card>

    {{-- Portfolios --}}
    @php($portfolios = $committee->portfolios ?? [])
    @php($taken = $this->delegates->pluck('allocated_portfolio')->map(fn ($name) => mb_strtolower((string) $name))->all())
    <flux:card class="flex flex-col gap-3">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex flex-col gap-1">
                <div class="hb-kicker">{{ $committee->portfolio_type === PortfolioType::Character ? __('Characters') : __('Countries') }}</div>
                <flux:text class="text-sm">
                    @if ($portfolios)
                        {{ trans_choice(':count on the list|:count on the list', count($portfolios), ['count' => count($portfolios)]) }}
                        · {{ trans_choice(':count taken|:count taken', count($taken), ['count' => count($taken)]) }}
                        · {{ $committee->portfolios_public ? __('delegates pick from it when registering') : __('hidden from the registration form') }}
                    @elseif ($committee->portfolio_type === PortfolioType::Country)
                        {{ __('No list yet, so any country can be chosen.') }}
                    @else
                        {{ __('No list yet, so names are typed freely.') }}
                    @endif
                </flux:text>
            </div>
            <flux:button size="sm" :variant="$portfolios ? 'ghost' : 'primary'" icon="pencil-square" wire:click="editPortfolios">{{ $portfolios ? __('Edit list') : __('Add list') }}</flux:button>
        </div>
        @if ($portfolios)
            <div class="flex flex-wrap gap-1.5">
                @foreach ($portfolios as $name)
                    @if (in_array(mb_strtolower($name), $taken, true))
                        <flux:badge size="sm" color="zinc" class="line-through opacity-60">{{ $name }}</flux:badge>
                    @else
                        <flux:badge size="sm">{{ $name }}</flux:badge>
                    @endif
                @endforeach
            </div>
        @endif
    </flux:card>

    {{-- Sessions --}}
    <div class="flex flex-col gap-3">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <flux:heading size="lg">{{ __('Sessions') }}</flux:heading>
                <flux:text class="text-sm">{{ __('Times are Pakistan time. Each session can have its own meeting link.') }}</flux:text>
            </div>
            <flux:button variant="primary" icon="plus" wire:click="addSession">{{ __('Add session') }}</flux:button>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Session') }}</flux:table.column>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Start') }}</flux:table.column>
                <flux:table.column>{{ __('End') }}</flux:table.column>
                <flux:table.column>{{ __('Meeting link') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->sessions as $session)
                    <flux:table.row :key="'session-'.$session->id">
                        <flux:table.cell class="font-medium text-zinc-800 dark:text-white">{{ $session->title }}</flux:table.cell>
                        <flux:table.cell>{{ $session->localStart()->format('D j M') }}</flux:table.cell>
                        <flux:table.cell>{{ $session->localStart()->format('g:i a') }}</flux:table.cell>
                        <flux:table.cell>{{ $session->localEnd()->format('g:i a') }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($session->meeting_url)
                                <flux:link :href="$session->meeting_url" target="_blank" :title="$session->meeting_url" class="inline-flex items-center gap-1">
                                    {{ parse_url($session->meeting_url, PHP_URL_HOST) }}
                                    <flux:icon.arrow-top-right-on-square variant="micro" />
                                </flux:link>
                            @else
                                <flux:badge size="sm" color="amber" inset="top bottom">{{ __('No link') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-1">
                                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="editSession({{ $session->id }})" :aria-label="__('Change :title', ['title' => $session->title])" />
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="deleteSession({{ $session->id }})"
                                    wire:confirm="{{ __('Remove :title? Delegates will no longer see it.', ['title' => $session->title]) }}"
                                    :aria-label="__('Remove :title', ['title' => $session->title])" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-10 text-center">{{ __('No sessions yet. Add the first one to give delegates a time and a link.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    {{-- Delegates --}}
    <div class="flex flex-col gap-3">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <flux:heading size="lg">{{ __('Allocated delegates') }}</flux:heading>
                <flux:text class="text-sm">{{ __('Change allocations from the Allocations page.') }}</flux:text>
            </div>
            <flux:button icon="users" :href="route('staff.allocations', ['committee' => $committee->id, 'show' => 'all'])" wire:navigate>{{ __('Allocations') }}</flux:button>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ $committee->portfolioLabel() }}</flux:table.column>
                <flux:table.column>{{ __('Delegate') }}</flux:table.column>
                <flux:table.column>{{ __('Delegation') }}</flux:table.column>
                <flux:table.column>{{ __('Contact') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->delegates as $delegate)
                    <flux:table.row :key="'delegate-'.$delegate->id">
                        <flux:table.cell class="font-medium text-zinc-800 dark:text-white">{{ $delegate->allocated_portfolio }}</flux:table.cell>
                        <flux:table.cell>{{ $delegate->name }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:link :href="route('staff.registrations.show', $delegate->registration)" wire:navigate>{{ $delegate->registration->reference }}</flux:link>
                            <div class="text-xs">{{ $delegate->registration->displayName() }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div>{{ $delegate->email }}</div>
                            <div class="text-xs">{{ $delegate->phone }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($delegate->hasPublishedAllocation())
                                <flux:badge size="sm" color="green" inset="top bottom">{{ __('Sent') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="amber" inset="top bottom">{{ __('Not sent') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="py-10 text-center">{{ __('No delegates allocated to this committee yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="committee-agenda" class="md:w-xl">
        <form wire:submit="saveAgenda" class="space-y-5">
            <flux:heading size="lg">{{ $committee->agenda ? __('Change agenda') : __('Add agenda') }}</flux:heading>
            <flux:textarea wire:model="agenda" :label="__('Agenda')" rows="4" :placeholder="__('e.g. Women’s role in post-conflict peacebuilding')" />
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save agenda') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="committee-portfolios" class="md:w-xl">
        <form wire:submit="savePortfolios" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ $committee->portfolio_type === PortfolioType::Character ? __('Characters') : __('Countries') }}</flux:heading>
                <flux:text class="mt-1">{{ __('One per line. Staff can only allocate names on this list, and each one only once.') }}</flux:text>
            </div>
            <flux:textarea wire:model="portfolioList" rows="14" :label="__('Portfolio list')" />
            <flux:switch wire:model="portfoliosPublic" :label="__('Delegates choose from this list when registering')" :description="__('Turn off to keep the list secret; delegates then type their preference freely.')" />
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save list') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="committee-session" class="md:w-xl">
        <form wire:submit="saveSession" class="space-y-5">
            <flux:heading size="lg">{{ $editingSessionId ? __('Change session') : __('Add session') }}</flux:heading>
            <flux:input wire:model="sessionTitle" :label="__('Title')" :placeholder="__('Session 1 — Opening ceremony')" />
            <div class="grid gap-4 sm:grid-cols-3">
                <flux:input wire:model="sessionDate" type="date" :label="__('Date')" />
                <flux:input wire:model="startTime" type="time" :label="__('Start time')" />
                <flux:input wire:model="endTime" type="time" :label="__('End time')" />
            </div>
            <flux:input wire:model="meetingUrl" type="url" :label="__('Meeting link')" :description="__('Optional. You can add it later.')" placeholder="https://meet.google.com/…" />
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ $editingSessionId ? __('Save session') : __('Add session') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
