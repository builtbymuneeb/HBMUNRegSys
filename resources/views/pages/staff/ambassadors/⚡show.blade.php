<?php

use App\Livewire\Forms\AmbassadorForm;
use App\Models\AuditLog;
use App\Models\CampusAmbassador;
use App\Models\Registration;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Campus ambassador')] class extends Component {
    public CampusAmbassador $ambassador;

    public AmbassadorForm $form;

    #[Computed]
    public function counts(): CampusAmbassador
    {
        return CampusAmbassador::whereKey($this->ambassador->id)->withReferralCounts()->firstOrFail();
    }

    /**
     * @return Collection<int, Registration>
     */
    #[Computed]
    public function registrations(): Collection
    {
        return $this->ambassador->registrations()->withCount('delegates')->latest()->get();
    }

    public function edit(): void
    {
        $this->form->edit($this->ambassador);
        Flux::modal('ambassador-form')->show();
    }

    public function save(): void
    {
        Gate::authorize('manage-ambassadors');

        $this->ambassador = $this->form->save();
        unset($this->counts, $this->registrations);

        Flux::modal('ambassador-form')->close();
        Flux::toast(variant: 'success', text: __('Ambassador saved.'));
    }

    public function delete(): void
    {
        Gate::authorize('manage-ambassadors');

        AuditLog::record('ambassador_deleted', $this->ambassador, Auth::user(), ['code' => $this->ambassador->code, 'name' => $this->ambassador->name]);
        $this->ambassador->delete();

        Flux::toast(text: __('Ambassador removed. Their registrations keep the code they entered.'));
        $this->redirectRoute('staff.ambassadors', navigate: true);
    }
}; ?>

<div class="flex w-full flex-col gap-6 p-6 lg:p-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:link :href="route('staff.ambassadors')" wire:navigate class="text-sm">← {{ __('Campus ambassadors') }}</flux:link>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <flux:heading size="xl" level="1">{{ $ambassador->name }}</flux:heading>
                <flux:badge color="purple">{{ $ambassador->code }}</flux:badge>
            </div>
        </div>
        <div class="flex gap-2">
            <flux:button icon="pencil-square" wire:click="edit">{{ __('Edit') }}</flux:button>
            <flux:button icon="trash" variant="ghost" wire:click="delete" wire:confirm="{{ __('Remove this ambassador? Their registrations keep the code they entered, but stop counting for anyone.') }}" :aria-label="__('Remove')" />
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <flux:card class="flex flex-col gap-1">
            <flux:text>{{ __('Registrations') }}</flux:text>
            <flux:heading size="xl">{{ $this->counts->registrations_count }}</flux:heading>
            <flux:text size="sm" class="text-zinc-400">{{ __(':count verified', ['count' => $this->counts->verified_registrations_count]) }}</flux:text>
        </flux:card>
        <flux:card class="flex flex-col gap-1">
            <flux:text>{{ __('Delegates') }}</flux:text>
            <flux:heading size="xl">{{ $this->counts->delegates_count }}</flux:heading>
            <flux:text size="sm" class="text-zinc-400">{{ __('across all their registrations') }}</flux:text>
        </flux:card>
        <flux:card class="flex flex-col gap-1">
            <flux:text>{{ __('Verified delegates') }}</flux:text>
            <flux:heading size="xl">{{ $this->counts->verified_delegates_count }}</flux:heading>
            <flux:text size="sm" class="text-zinc-400">{{ __('payment checked') }}</flux:text>
        </flux:card>
    </div>

    <flux:card>
        <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                __('Referral code') => $ambassador->code,
                __('Email') => $ambassador->email,
                __('Phone') => $ambassador->phone,
                __('Institution') => $ambassador->institution,
                __('City') => $ambassador->city,
                __('Added') => $ambassador->created_at->timezone('Asia/Karachi')->format('j M Y'),
            ] as $label => $value)
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ $label }}</dt>
                    <dd class="mt-0.5 break-words text-zinc-800 dark:text-white">{{ $value ?: '—' }}</dd>
                </div>
            @endforeach
            @if ($ambassador->notes)
                <div class="sm:col-span-2 lg:col-span-3">
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Notes') }}</dt>
                    <dd class="mt-0.5 break-words whitespace-pre-line text-zinc-800 dark:text-white">{{ $ambassador->notes }}</dd>
                </div>
            @endif
        </dl>
    </flux:card>

    <div class="flex flex-col gap-3">
        <flux:heading size="lg">{{ __('Registrations using this code') }}</flux:heading>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Reference') }}</flux:table.column>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Code entered') }}</flux:table.column>
                <flux:table.column>{{ __('Delegates') }}</flux:table.column>
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
                            <div class="text-xs">{{ $registration->city }}</div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $registration->referral_code }}</flux:table.cell>
                        <flux:table.cell>{{ $registration->delegates_count }}</flux:table.cell>
                        <flux:table.cell>{{ $registration->created_at->timezone('Asia/Karachi')->format('j M, g:i a') }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$registration->status->color()" inset="top bottom">{{ $registration->status->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            @can('view', $registration)
                                <flux:button size="sm" :href="route('staff.registrations.show', $registration)" wire:navigate>{{ __('View') }}</flux:button>
                            @endcan
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-10 text-center">{{ __('Nobody has registered with this code yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    @include('partials.ambassador-form')
</div>
