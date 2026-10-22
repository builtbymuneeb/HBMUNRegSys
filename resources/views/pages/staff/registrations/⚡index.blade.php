<?php

use App\Enums\RegistrationStatus;
use App\Enums\RegistrationType;
use App\Models\Delegate;
use App\Models\Registration;
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
                ->orWhereHas('delegates', fn ($delegates) => $delegates
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"))))
            ->oldest()
            ->paginate(25);
    }
}; ?>

<div class="flex w-full flex-col gap-6 p-6 lg:p-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Registrations') }}</flux:heading>
            <flux:text class="mt-1">
                {{ auth()->user()->canReviewRegistrations() ? __('Check each payment screenshot, then verify or reject it.') : __('Read-only overview of all registrations.') }}
            </flux:text>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
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

    <div class="grid gap-3 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)]">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search reference, name, email, phone, institution…')" :label="__('Search')" />
        <flux:select wire:model.live="status" :label="__('Status')">
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            @foreach (RegistrationStatus::cases() as $case)
                <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="type" :label="__('Type')">
            <flux:select.option value="">{{ __('All types') }}</flux:select.option>
            @foreach (RegistrationType::cases() as $case)
                <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$this->registrations">
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
