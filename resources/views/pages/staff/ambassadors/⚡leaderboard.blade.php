<?php

use App\Models\AmbassadorPointAdjustment;
use App\Models\AuditLog;
use App\Models\CampusAmbassador;
use App\Models\Setting;
use App\Support\Leaderboard;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Leaderboard')] class extends Component {
    public string $rate = '';

    public ?int $adjustingId = null;

    public string $adjustPoints = '';

    public string $adjustReason = '';

    public function mount(): void
    {
        $this->rate = (string) Setting::pointsPerVerifiedDelegate();
    }

    #[Computed]
    public function standings(): Collection
    {
        return Leaderboard::standings();
    }

    public function saveRate(): void
    {
        Gate::authorize('manage-ambassadors');

        $this->validate(['rate' => ['required', 'integer', 'min:0', 'max:100000']], attributes: ['rate' => __('points per verified delegate')]);

        $before = Setting::pointsPerVerifiedDelegate();
        Setting::put(Setting::POINTS_PER_VERIFIED_DELEGATE, (string) (int) $this->rate);
        AuditLog::record('ambassador_points_rate_changed', Auth::user(), Auth::user(), ['from' => $before, 'to' => (int) $this->rate]);

        unset($this->standings);
        Flux::toast(variant: 'success', text: __('Each verified delegate is now worth :points points. Everyone’s totals are updated.', ['points' => (int) $this->rate]));
    }

    public function startAdjust(int $ambassadorId): void
    {
        $this->adjustingId = $ambassadorId;
        $this->reset('adjustPoints', 'adjustReason');
        $this->resetValidation();
        Flux::modal('adjust-points')->show();
    }

    public function saveAdjustment(): void
    {
        Gate::authorize('manage-ambassadors');

        $validated = $this->validate([
            'adjustPoints' => ['required', 'integer', 'not_in:0', 'min:-100000', 'max:100000'],
            'adjustReason' => ['required', 'string', 'max:255'],
        ], attributes: ['adjustPoints' => __('points'), 'adjustReason' => __('reason')]);

        $ambassador = CampusAmbassador::findOrFail($this->adjustingId);
        $adjustment = new AmbassadorPointAdjustment(['points' => (int) $validated['adjustPoints'], 'reason' => trim($validated['adjustReason']), 'created_by' => Auth::id()]);
        $ambassador->pointAdjustments()->save($adjustment);
        AuditLog::record('ambassador_points_adjusted', $ambassador, Auth::user(), ['points' => $adjustment->points, 'reason' => $adjustment->reason]);

        unset($this->standings);
        Flux::modal('adjust-points')->close();
        Flux::toast(variant: 'success', text: __(':points points for :name.', ['points' => ($adjustment->points > 0 ? '+' : '').$adjustment->points, 'name' => $ambassador->name]));
    }
}; ?>

<div class="flex w-full flex-col gap-6 p-6 lg:p-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Leaderboard') }}</flux:heading>
            <flux:text class="mt-1">{{ __('HB and Private ambassadors on one board. Points = approved tasks + verified delegates × the rate below + bonus.') }}</flux:text>
        </div>
        <flux:button icon="clipboard-document-list" :href="route('staff.ambassadors.tasks')" wire:navigate>{{ __('Ambassador tasks') }}</flux:button>
    </div>

    <flux:card>
        <form wire:submit="saveRate" class="flex flex-wrap items-end gap-3">
            <flux:input wire:model="rate" type="number" min="0" :label="__('Points per verified delegate')" class="max-w-56"
                :description="__('Counts every delegate in a verified registration that used their code. Changing it updates everyone’s total.')" />
            <flux:button type="submit" variant="primary" data-test="save-rate">{{ __('Save') }}</flux:button>
        </form>
        @if (\App\Models\Setting::pointsPerVerifiedDelegate() === 0)
            <flux:text class="mt-3 text-sm text-amber-600 dark:text-amber-300">{{ __('Not set yet, so referrals earn no points for now.') }}</flux:text>
        @endif
    </flux:card>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>#</flux:table.column>
            <flux:table.column>{{ __('Ambassador') }}</flux:table.column>
            <flux:table.column>{{ __('Tasks') }}</flux:table.column>
            <flux:table.column>{{ __('Referrals') }}</flux:table.column>
            <flux:table.column>{{ __('Bonus') }}</flux:table.column>
            <flux:table.column>{{ __('Total') }}</flux:table.column>
            <flux:table.column>{{ __('Portal') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->standings as $row)
                @php($ambassador = $row['ambassador'])
                <flux:table.row :key="$ambassador->id">
                    <flux:table.cell class="font-semibold">{{ $row['rank'] }}</flux:table.cell>
                    <flux:table.cell>
                        <a href="{{ route('staff.ambassadors.show', $ambassador) }}" wire:navigate class="font-medium text-zinc-800 hover:underline dark:text-white">{{ $ambassador->name }}</a>
                        <div class="text-xs">{{ $ambassador->code }} · {{ $ambassador->type->label() }}</div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $row['task_points'] }} <span class="text-xs text-zinc-400">({{ $row['tasks_done'] }})</span></flux:table.cell>
                    <flux:table.cell>{{ $row['referral_points'] }} <span class="text-xs text-zinc-400">({{ trans_choice(':count delegate|:count delegates', $row['verified_delegates']) }})</span></flux:table.cell>
                    <flux:table.cell>{{ $row['bonus_points'] > 0 ? '+' : '' }}{{ $row['bonus_points'] }}</flux:table.cell>
                    <flux:table.cell variant="strong" class="text-base">{{ number_format($row['total']) }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($ambassador->user_id)
                            <flux:badge size="sm" color="green">{{ __('Has access') }}</flux:badge>
                        @else
                            <flux:badge size="sm">{{ __('No login') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:button size="sm" variant="ghost" icon="plus-circle" wire:click="startAdjust({{ $ambassador->id }})">{{ __('Bonus') }}</flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="8" class="py-10 text-center">{{ __('No campus ambassadors yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="adjust-points" class="w-full md:max-w-md">
        <form wire:submit="saveAdjustment" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Add or take away points') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Use a minus sign to take points away, e.g. -10. The reason shows on their profile.') }}</flux:text>
            </div>
            <flux:input wire:model="adjustPoints" type="number" :label="__('Points')" />
            <flux:input wire:model="adjustReason" :label="__('Reason')" :placeholder="__('e.g. Brought 3 volunteers to the info session')" />
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
