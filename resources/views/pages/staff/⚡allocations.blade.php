<?php

use App\Actions\Allocations\AllocateDelegate;
use App\Actions\Allocations\PublishAllocations;
use App\Enums\RegistrationStatus;
use App\Models\Committee;
use App\Models\Delegate;
use App\Models\MailLog;
use App\Notifications\AllocationPublished;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Allocations')] class extends Component {
    use WithPagination;

    #[Url]
    public string $show = 'unallocated';

    #[Url]
    public string $committee = '';

    #[Url]
    public string $search = '';

    public ?int $editingId = null;

    public string $committeeId = '';

    public string $portfolio = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['show', 'committee', 'search'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return Collection<int, Committee>
     */
    #[Computed]
    public function committees(): Collection
    {
        return Committee::ordered()
            ->withCount([
                'allocatedDelegates as allocated_count',
                'allocatedDelegates as sent_count' => fn (Builder $query) => $query->whereNotNull('allocation_published_at'),
            ])
            ->get();
    }

    #[Computed]
    public function unsentCount(): int
    {
        return $this->allocatable()
            ->whereNotNull('allocated_committee_id')
            ->whereNull('allocation_published_at')
            ->count();
    }

    #[Computed]
    public function unallocatedCount(): int
    {
        return $this->allocatable()->whereNull('allocated_committee_id')->count();
    }

    /**
     * @return LengthAwarePaginator<int, Delegate>
     */
    #[Computed]
    public function delegates(): LengthAwarePaginator
    {
        $search = trim($this->search);
        $committeeId = (int) $this->committee ?: null;

        return $this->allocatable()
            ->with(['registration', 'user', 'firstCommittee', 'secondCommittee', 'allocatedCommittee'])
            ->when($this->show === 'unallocated', fn (Builder $query) => $query->whereNull('allocated_committee_id'))
            ->when($this->show === 'unsent', fn (Builder $query) => $query->whereNotNull('allocated_committee_id')->whereNull('allocation_published_at'))
            ->when($this->show === 'sent', fn (Builder $query) => $query->whereNotNull('allocation_published_at'))
            ->when($committeeId, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('allocated_committee_id', $committeeId)
                ->orWhere(fn (Builder $query) => $query->whereNull('allocated_committee_id')->where('first_committee_id', $committeeId))))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('allocated_portfolio', 'like', "%{$search}%")
                ->orWhere('country_preference', 'like', "%{$search}%")
                ->orWhere('personality_preference', 'like', "%{$search}%")
                ->orWhereHas('registration', fn (Builder $registration) => $registration
                    ->where('reference', 'like', "%{$search}%")
                    ->orWhere('organisation_name', 'like', "%{$search}%"))))
            ->orderBy('registration_id')
            ->orderBy('id')
            ->paginate(25);
    }

    /**
     * The latest allocation email logged for each delegate on this page, keyed by lowercase address.
     *
     * @return SupportCollection<string, MailLog>
     */
    #[Computed]
    public function allocationEmails(): SupportCollection
    {
        $addresses = collect($this->delegates->items())->map(fn (Delegate $delegate) => strtolower($delegate->user?->email ?? $delegate->email));

        return MailLog::query()
            ->where('notification', AllocationPublished::class)
            ->whereIn('to', $addresses)
            ->orderBy('id')
            ->get()
            ->keyBy(fn (MailLog $log) => strtolower($log->to));
    }

    #[Computed]
    public function editing(): ?Delegate
    {
        return $this->editingId ? $this->allocatable()->with(['registration', 'firstCommittee', 'secondCommittee'])->find($this->editingId) : null;
    }

    /**
     * The committee being allocated, as chosen in the form.
     */
    #[Computed]
    public function allocatingCommittee(): ?Committee
    {
        return $this->committees->firstWhere('id', (int) $this->committeeId);
    }

    /**
     * The portfolios that committee can be given from, or null when it takes any name.
     *
     * @return list<string>|null
     */
    #[Computed]
    public function portfolioOptions(): ?array
    {
        return $this->allocatingCommittee?->portfolioOptions();
    }

    /**
     * Portfolios already given out in the committee being allocated, for the hint under the input.
     *
     * @return list<string>
     */
    #[Computed]
    public function takenPortfolios(): array
    {
        if (! $this->committeeId) {
            return [];
        }

        return Delegate::query()
            ->where('allocated_committee_id', (int) $this->committeeId)
            ->whereKeyNot($this->editingId ?? 0)
            ->orderBy('allocated_portfolio')
            ->pluck('allocated_portfolio')
            ->filter()
            ->values()
            ->all();
    }

    public function edit(int $delegateId): void
    {
        Gate::authorize('manage-conference');

        $delegate = $this->allocatable()->findOrFail($delegateId);

        $this->resetValidation();
        $this->editingId = $delegate->id;

        if ($delegate->isAllocated()) {
            $this->committeeId = (string) $delegate->allocated_committee_id;
            $this->portfolio = $this->forCommittee($delegate->allocatedCommittee, $delegate->allocated_portfolio);
        } else {
            $this->usePreference(1);
        }

        Flux::modal('allocate-delegate')->show();
    }

    /**
     * Fill the form with the delegate's first or second preference.
     */
    public function usePreference(int $choice): void
    {
        $delegate = $this->editing;

        if (! $delegate) {
            return;
        }

        $committee = $choice === 1 ? $delegate->firstCommittee : $delegate->secondCommittee;

        $this->committeeId = (string) $committee->id;
        $this->portfolio = $this->forCommittee($committee, $delegate->preferenceFor($committee));
    }

    /**
     * A name typed loosely at registration ("usa", "maryam nawaz") becomes its listed name, or blank if it isn't on the list.
     */
    private function forCommittee(Committee $committee, ?string $portfolio): string
    {
        return (string) $committee->matchPortfolio($portfolio);
    }

    public function save(AllocateDelegate $allocateDelegate): void
    {
        Gate::authorize('manage-conference');

        $this->validate([
            'committeeId' => ['required', 'exists:committees,id'],
            'portfolio' => ['required', 'string', 'max:120', ...($this->portfolioOptions !== null ? [Rule::in($this->portfolioOptions)] : [])],
        ], ['portfolio.in' => __('Choose one from the committee’s list.')], ['committeeId' => 'committee']);

        $delegate = $this->allocatable()->findOrFail($this->editingId);

        $allocateDelegate->handle($delegate, Committee::findOrFail((int) $this->committeeId), $this->portfolio, Auth::user());

        Flux::modal('allocate-delegate')->close();
        Flux::toast(variant: 'success', text: __(':name allocated. Send it when you’re ready.', ['name' => $delegate->name]));
        $this->reset('editingId', 'committeeId', 'portfolio');
    }

    public function clear(AllocateDelegate $allocateDelegate): void
    {
        Gate::authorize('manage-conference');

        $delegate = $this->allocatable()->findOrFail($this->editingId);
        $allocateDelegate->handle($delegate, null, null, Auth::user());

        Flux::modal('allocate-delegate')->close();
        Flux::toast(text: __('Allocation cleared for :name.', ['name' => $delegate->name]));
        $this->reset('editingId', 'committeeId', 'portfolio');
    }

    public function send(int $delegateId, PublishAllocations $publishAllocations): void
    {
        Gate::authorize('manage-conference');

        $delegate = $this->allocatable()->findOrFail($delegateId);
        $publishAllocations->handle(collect([$delegate]), Auth::user());

        Flux::toast(variant: 'success', text: __('Allocation sent to :name.', ['name' => $delegate->name]));
    }

    public function sendAll(PublishAllocations $publishAllocations): void
    {
        Gate::authorize('manage-conference');

        $delegates = $this->allocatable()
            ->with(['registration', 'user', 'allocatedCommittee'])
            ->whereNotNull('allocated_committee_id')
            ->whereNull('allocation_published_at')
            ->get();

        $sent = $publishAllocations->handle($delegates, Auth::user());

        Flux::modal('send-all-allocations')->close();
        Flux::toast(variant: 'success', text: trans_choice(':count allocation sent.|:count allocations sent.', $sent, ['count' => $sent]));
    }

    /**
     * Delegates whose payment has been verified.
     *
     * @return Builder<Delegate>
     */
    private function allocatable(): Builder
    {
        return Delegate::query()->whereHas('registration', fn (Builder $query) => $query->where('status', RegistrationStatus::Verified));
    }
}; ?>

<div class="flex w-full flex-col gap-6 p-6 lg:p-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Allocations') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Give each verified delegate a committee and portfolio. Delegates only see an allocation once you send it.') }}</flux:text>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button icon="arrow-down-tray" :href="route('staff.delegates.export')">{{ __('Export CSV') }}</flux:button>
            <flux:modal.trigger name="send-all-allocations">
                <flux:button variant="primary" icon="paper-airplane" :disabled="$this->unsentCount === 0">
                    {{ __('Send unsent (:count)', ['count' => $this->unsentCount]) }}
                </flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <flux:card class="flex flex-col gap-1">
            <flux:text>{{ __('Not allocated yet') }}</flux:text>
            <flux:heading size="xl">{{ $this->unallocatedCount }}</flux:heading>
        </flux:card>
        @foreach ($this->committees as $committeeCard)
            <flux:card class="flex flex-col gap-1">
                <flux:text>{{ $committeeCard->code }}</flux:text>
                <flux:heading size="xl">{{ $committeeCard->allocated_count }}</flux:heading>
                <flux:text class="text-xs">{{ __(':sent sent', ['sent' => $committeeCard->sent_count]) }}</flux:text>
            </flux:card>
        @endforeach
    </div>

    <div class="grid gap-3 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)]">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search name, email, portfolio, reference, institution…')" :label="__('Search')" />
        <flux:select wire:model.live="show" :label="__('Show')">
            <flux:select.option value="unallocated">{{ __('Not allocated') }}</flux:select.option>
            <flux:select.option value="unsent">{{ __('Allocated, not sent') }}</flux:select.option>
            <flux:select.option value="sent">{{ __('Sent') }}</flux:select.option>
            <flux:select.option value="all">{{ __('All verified delegates') }}</flux:select.option>
        </flux:select>
        <flux:select wire:model.live="committee" :label="__('Committee')">
            <flux:select.option value="">{{ __('All committees') }}</flux:select.option>
            @foreach ($this->committees as $option)
                <flux:select.option value="{{ $option->id }}">{{ $option->code }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$this->delegates">
        <flux:table.columns>
            <flux:table.column>{{ __('Delegate') }}</flux:table.column>
            <flux:table.column>{{ __('1st choice') }}</flux:table.column>
            <flux:table.column>{{ __('2nd choice') }}</flux:table.column>
            <flux:table.column>{{ __('Allocation') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->delegates as $delegate)
                <flux:table.row :key="$delegate->id">
                    <flux:table.cell>
                        <div class="font-medium text-zinc-800 dark:text-white">{{ $delegate->name }}</div>
                        <div class="text-xs">{{ $delegate->registration->reference }} · {{ $delegate->registration->displayName() }}</div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="font-medium">{{ $delegate->firstCommittee->code }}</div>
                        <div class="text-xs">{{ $delegate->preferenceFor($delegate->firstCommittee) ?: '—' }}</div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="font-medium">{{ $delegate->secondCommittee->code }}</div>
                        <div class="text-xs">{{ $delegate->preferenceFor($delegate->secondCommittee) ?: '—' }}</div>
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($delegate->isAllocated())
                            <div class="font-medium text-zinc-800 dark:text-white">{{ $delegate->allocatedCommittee->code }}</div>
                            <div class="text-xs">{{ $delegate->allocated_portfolio }}</div>
                        @else
                            <span class="text-zinc-400">—</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($delegate->hasPublishedAllocation())
                            <flux:badge size="sm" color="green" inset="top bottom">{{ __('Sent') }}</flux:badge>
                            @php($mail = $this->allocationEmails->get(strtolower($delegate->user?->email ?? $delegate->email)))
                            @if (! $mail)
                                <div class="mt-1 text-xs text-amber-300">{{ __('Email waiting to send') }}</div>
                            @elseif ($mail->failed())
                                <div class="mt-1 text-xs text-red-300" title="{{ $mail->error }}">{{ __('Email failed') }}</div>
                            @else
                                <div class="mt-1 text-xs">{{ __('Emailed :time', ['time' => $mail->created_at->timezone('Asia/Karachi')->format('j M, g:i a')]) }}</div>
                            @endif
                        @elseif ($delegate->isAllocated())
                            <flux:badge size="sm" color="amber" inset="top bottom">{{ __('Not sent') }}</flux:badge>
                        @else
                            <flux:badge size="sm" inset="top bottom">{{ __('Not allocated') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <div class="flex justify-end gap-2">
                            @if ($delegate->isAllocated() && ! $delegate->hasPublishedAllocation())
                                <flux:button size="sm" icon="paper-airplane" wire:click="send({{ $delegate->id }})">{{ __('Send') }}</flux:button>
                            @elseif ($delegate->hasPublishedAllocation())
                                <x-whatsapp-button :label="__('WhatsApp')" :phone="$delegate->phone" :message="(new AllocationPublished($delegate))->toWhatsApp()" />
                            @endif
                            <flux:button size="sm" wire:click="edit({{ $delegate->id }})">{{ $delegate->isAllocated() ? __('Change') : __('Allocate') }}</flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="py-10 text-center">{{ __('No verified delegates match these filters.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="allocate-delegate" class="md:w-lg">
        @if ($delegate = $this->editing)
            <form wire:submit="save" class="space-y-5">
                <div>
                    <flux:heading size="lg">{{ __('Allocate :name', ['name' => $delegate->name]) }}</flux:heading>
                    <flux:text class="mt-1">{{ $delegate->registration->reference }} · {{ $delegate->registration->displayName() }}</flux:text>
                </div>

                <div class="flex flex-wrap gap-2">
                    <flux:button size="sm" wire:click="usePreference(1)">
                        {{ __('1st: :committee — :pref', ['committee' => $delegate->firstCommittee->code, 'pref' => $delegate->preferenceFor($delegate->firstCommittee) ?: __('no preference')]) }}
                    </flux:button>
                    <flux:button size="sm" wire:click="usePreference(2)">
                        {{ __('2nd: :committee — :pref', ['committee' => $delegate->secondCommittee->code, 'pref' => $delegate->preferenceFor($delegate->secondCommittee) ?: __('no preference')]) }}
                    </flux:button>
                </div>

                <flux:select wire:model.live="committeeId" :label="__('Committee')">
                    <flux:select.option value="">{{ __('Choose a committee') }}</flux:select.option>
                    @foreach ($this->committees as $option)
                        <flux:select.option value="{{ $option->id }}">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div>
                    @if ($this->portfolioOptions !== null)
                        <flux:field>
                            <flux:label>{{ $this->allocatingCommittee->portfolioLabel() }}</flux:label>
                            <x-portfolio-select wire:model="portfolio" wire:key="portfolio-{{ $editingId }}-{{ $committeeId }}" variant="staff" :options="$this->portfolioOptions" :taken="$this->takenPortfolios" :placeholder="__('Search the committee’s list…')" />
                            <flux:error name="portfolio" />
                        </flux:field>
                    @else
                        <flux:input wire:model="portfolio" :label="$this->allocatingCommittee?->portfolioLabel() ?? __('Portfolio')" :placeholder="__('e.g. Maryam Nawaz')" />
                    @endif
                    @if ($this->takenPortfolios)
                        <flux:text class="mt-2 text-xs">{{ __('Already taken here:') }} {{ implode(', ', $this->takenPortfolios) }}</flux:text>
                    @endif
                </div>

                <div class="flex flex-wrap gap-2">
                    @if ($delegate->isAllocated())
                        <flux:button variant="ghost" wire:click="clear">{{ __('Clear allocation') }}</flux:button>
                    @endif
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </form>
        @endif
    </flux:modal>

    <flux:modal name="send-all-allocations" class="md:w-md">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Send :count allocations?', ['count' => $this->unsentCount]) }}</flux:heading>
                <flux:text class="mt-2">{{ __('Each delegate is emailed their committee and portfolio, and it appears on their dashboard. Check the list first; any later change has to be sent again.') }}</flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="sendAll">{{ __('Send allocations') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
