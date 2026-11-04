<?php

use App\Enums\RegistrationStatus;
use App\Enums\RegistrationType;
use App\Models\Delegate;
use App\Models\Registration;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Registrations')] class extends Component {
    use WithPagination;

    #[Url]
    public string $status = 'pending';

    #[Url]
    public string $type = '';

    #[Url]
    public string $search = '';

    public string $stamp = '';

    public int $pendingCount = 0;

    public function mount(): void
    {
        $this->stamp = $this->fingerprint();
        $this->pendingCount = $this->pendingTotal();
    }

    /**
     * Polled by the browser. Skips the re-render unless a registration or delegate has changed.
     */
    public function poll(): void
    {
        $stamp = $this->fingerprint();

        if ($stamp === $this->stamp) {
            $this->skipRender();

            return;
        }

        $this->stamp = $stamp;

        $pending = $this->pendingTotal();
        if ($pending > $this->pendingCount) {
            Flux::toast(heading: __('New submissions'), text: trans_choice(':count new registration is waiting for review.|:count new registrations are waiting for review.', $pending - $this->pendingCount));
        }
        $this->pendingCount = $pending;
    }

    protected function fingerprint(): string
    {
        return md5(json_encode([
            Registration::query()->selectRaw('count(*) as c, max(updated_at) as u')->first()?->only(['c', 'u']),
            Delegate::query()->selectRaw('count(*) as c, max(updated_at) as u')->first()?->only(['c', 'u']),
        ]));
    }

    protected function pendingTotal(): int
    {
        return Registration::where('status', RegistrationStatus::Pending)->count();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'type', 'search'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return Collection<string, int>
     */
    #[Computed]
    public function statusCounts(): Collection
    {
        return Registration::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
    }

    #[Computed]
    public function verifiedDelegateCount(): int
    {
        return Delegate::query()
            ->whereHas('registration', fn ($query) => $query->where('status', RegistrationStatus::Verified))
            ->count();
    }

    #[Computed]
    public function verifiedAmount(): int
    {
        return (int) Registration::query()->where('status', RegistrationStatus::Verified)->sum('amount_due');
    }

    /**
     * @return LengthAwarePaginator<int, Registration>
     */
    #[Computed]
    public function registrations(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return Registration::query()
            ->withCount('delegates')
            ->when(RegistrationStatus::tryFrom($this->status), fn ($query, $status) => $query->where('status', $status))
            ->when(RegistrationType::tryFrom($this->type), fn ($query, $type) => $query->where('type', $type))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('reference', 'like', "%{$search}%")
                ->orWhere('organisation_name', 'like', "%{$search}%")
                ->orWhere('contact_name', 'like', "%{$search}%")
                ->orWhere('contact_email', 'like', "%{$search}%")
                ->orWhere('contact_phone', 'like', "%{$search}%")
                ->orWhere('referral_code', 'like', "%{$search}%")
                ->orWhereHas('delegates', fn ($delegates) => $delegates
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"))))
            ->oldest()
            ->paginate(25);
    }
}; ?>

<div wire:poll.15s="poll" class="flex w-full flex-col gap-6 p-6 lg:p-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <flux:heading size="xl" level="1" class="max-sm:px-1">{{ __('Registrations') }}</flux:heading>
        @can('manage-conference')
            <flux:button icon="arrow-down-tray" class="max-sm:hidden" :href="route('staff.delegates.export', ['status' => $status])">
                {{ $status ? __('Export :status delegates (CSV)', ['status' => strtolower(RegistrationStatus::tryFrom($status)?->label() ?? '')]) : __('Export all delegates (CSV)') }}
            </flux:button>
            <flux:button icon="arrow-down-tray" size="sm" class="sm:hidden" :href="route('staff.delegates.export', ['status' => $status])" :aria-label="__('Export delegates (CSV)')" />
        @endcan
    </div>

    <div class="hidden gap-4 sm:grid sm:grid-cols-2 xl:grid-cols-5">
        @foreach (RegistrationStatus::cases() as $case)
            <flux:card class="flex flex-col gap-1">
                <flux:text>{{ $case->label() }}</flux:text>
                <flux:heading size="xl">{{ $this->statusCounts[$case->value] ?? 0 }}</flux:heading>
            </flux:card>
        @endforeach
        <flux:card class="flex flex-col gap-1">
            <flux:text>{{ __('Verified delegates') }}</flux:text>
            <flux:heading size="xl">{{ $this->verifiedDelegateCount }}</flux:heading>
        </flux:card>
        <flux:card class="flex flex-col gap-1">
            <flux:text>{{ __('Verified amount') }}</flux:text>
            <flux:heading size="xl">PKR {{ number_format($this->verifiedAmount) }}</flux:heading>
        </flux:card>
    </div>

    {{-- Phone: status chips with counts --}}
    <div class="-mx-2 flex gap-1.5 overflow-x-auto px-2 sm:hidden" data-test="status-chips">
        @foreach ([['', __('All')], ...collect(RegistrationStatus::cases())->map(fn ($case) => [$case->value, \Illuminate\Support\Str::ucfirst($case->value).' · '.($this->statusCounts[$case->value] ?? 0)])] as [$value, $label])
            <button type="button" wire:click="$set('status', @js($value))" @class([
                'shrink-0 rounded-full border px-3 py-1.5 text-xs font-semibold',
                'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900' => $status === $value,
                'border-zinc-200 bg-white text-zinc-600 dark:border-white/10 dark:bg-white/5 dark:text-zinc-300' => $status !== $value,
            ])>{{ $label }}</button>
        @endforeach
    </div>

    <div class="grid gap-3 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)]">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search name, reference, email…')" :aria-label="__('Search')" class="sm:mt-[1.625rem]" />
        <div class="max-sm:hidden">
        <flux:select wire:model.live="status" :label="__('Status')">
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            @foreach (RegistrationStatus::cases() as $case)
                <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        </div>
        <div class="max-sm:hidden">
        <flux:select wire:model.live="type" :label="__('Type')">
            <flux:select.option value="">{{ __('All types') }}</flux:select.option>
            @foreach (RegistrationType::cases() as $case)
                <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        </div>
    </div>

    {{-- Phone: one line per registration --}}
    <div class="sm:hidden">
        <div class="hb-list">
            @forelse ($this->registrations as $registration)
                <a href="{{ route('staff.registrations.show', $registration) }}" wire:navigate wire:key="m-{{ $registration->id }}" class="hb-row hover:bg-zinc-50 dark:hover:bg-white/4">
                    <span class="hb-icon-tile text-xs font-bold">{{ \Illuminate\Support\Str::of($registration->displayName())->explode(' ')->take(2)->map(fn ($word) => mb_substr($word, 0, 1))->implode('') }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold text-zinc-900 dark:text-white">{{ $registration->displayName() }}</span>
                        <span class="block truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $registration->delegates_count === 1 ? __('Individual') : __(':count delegates', ['count' => $registration->delegates_count]) }}</span>
                    </span>
                    <flux:badge size="sm" :color="$registration->status->color()">{{ \Illuminate\Support\Str::ucfirst($registration->status->value) }}</flux:badge>
                </a>
            @empty
                <div class="hb-row justify-center py-8 text-sm text-zinc-500">{{ __('No registrations match.') }}</div>
            @endforelse
        </div>
        <div class="mt-3">{{ $this->registrations->links() }}</div>
    </div>

    <flux:table :paginate="$this->registrations" class="max-sm:hidden">
        <flux:table.columns>
            <flux:table.column>{{ __('Reference') }}</flux:table.column>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Type') }}</flux:table.column>
            <flux:table.column>{{ __('Delegates') }}</flux:table.column>
            <flux:table.column>{{ __('Amount') }}</flux:table.column>
            <flux:table.column>{{ __('Submitted') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->registrations as $registration)
                <flux:table.row :key="$registration->id">
                    <flux:table.cell variant="strong">{{ $registration->reference }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="font-medium text-zinc-800 dark:text-white">{{ $registration->displayName() }}</div>
                        <div class="text-xs">{{ $registration->contact_phone }}</div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $registration->type->label() }}</flux:table.cell>
                    <flux:table.cell>{{ $registration->delegates_count }}</flux:table.cell>
                    <flux:table.cell>PKR {{ number_format($registration->amount_due) }}</flux:table.cell>
                    <flux:table.cell>{{ $registration->created_at->timezone('Asia/Karachi')->format('j M, g:i a') }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$registration->status->color()" inset="top bottom">{{ $registration->status->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:button size="sm" :href="route('staff.registrations.show', $registration)" wire:navigate>
                            {{ auth()->user()->canReviewRegistrations() && $registration->isPending() ? __('Review') : __('View') }}
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="8" class="py-10 text-center">{{ __('No registrations match these filters.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
