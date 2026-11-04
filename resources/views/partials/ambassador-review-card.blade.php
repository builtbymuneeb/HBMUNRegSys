{{-- One ambassador submission for staff to award points for (which approves it) or send back. Set $showTask to false on a task's own page. --}}
@php($showTask ??= true)
<flux:card class="flex flex-col gap-3" wire:key="review-{{ $submission->id }}" data-test="submission-{{ $submission->id }}">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('staff.ambassadors.show', $submission->ambassador) }}" wire:navigate class="font-semibold text-zinc-800 hover:underline dark:text-white">{{ $submission->ambassador->name }}</a>
                <flux:badge size="sm" color="purple">{{ $submission->ambassador->code }}</flux:badge>
            </div>
            @if ($showTask)
                <a href="{{ route('staff.ambassadors.tasks.show', $submission->task) }}" wire:navigate class="mt-0.5 block text-sm text-zinc-600 hover:underline dark:text-zinc-300">{{ $submission->task->title }} · {{ trans_choice(':count point|:count points', $submission->task->points) }}</a>
            @endif
            <flux:text class="text-xs">{{ __('Handed in :time', ['time' => $submission->created_at->timezone('Asia/Karachi')->format('j M, g:i a')]) }}</flux:text>
        </div>
        <div class="flex items-center gap-2">
            @if ($submission->points_awarded)
                <flux:badge size="sm" color="purple">+{{ $submission->points_awarded }}</flux:badge>
            @endif
            <flux:badge size="sm" :color="$submission->status->color()">{{ $submission->status->label() }}</flux:badge>
        </div>
    </div>

    @include('partials.ambassador-proof', ['submission' => $submission])

    @if ($submission->review_note)
        <flux:text class="text-sm"><span class="font-medium">{{ __('Note to them:') }}</span> {{ $submission->review_note }}</flux:text>
    @endif
    @if ($submission->reviewer)
        <flux:text class="text-xs">{{ __(':status by :name, :time', ['status' => $submission->status->label(), 'name' => $submission->reviewer->name, 'time' => $submission->reviewed_at?->timezone('Asia/Karachi')->format('j M, g:i a')]) }}</flux:text>
    @endif

    {{-- Staff give whatever points they judge fair; the task's points are only the starting figure. Giving points marks it approved. --}}
    @php($isApproved = $submission->status === \App\Enums\SubmissionStatus::Approved)
    <form class="flex flex-wrap items-center justify-end gap-2" x-data="{ points: {{ (int) ($isApproved ? $submission->points_awarded : $submission->task->points) }} }"
        x-on:submit.prevent="$wire.approveSubmission({{ $submission->id }}, points)" data-test="award-{{ $submission->id }}">
        @if ($isApproved)
            <flux:button type="button" size="sm" variant="ghost" icon="arrow-uturn-left" wire:click="startSendBack({{ $submission->id }})"
                wire:confirm="{{ __('This takes away the :points points they were given. Continue?', ['points' => $submission->points_awarded]) }}">{{ __('Undo and send back') }}</flux:button>
        @elseif ($submission->status === \App\Enums\SubmissionStatus::Pending)
            <flux:button type="button" size="sm" variant="ghost" icon="arrow-uturn-left" wire:click="startSendBack({{ $submission->id }})">{{ __('Send back') }}</flux:button>
        @endif
        <label class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-300">
            <span>{{ $isApproved ? __('Change points to') : __('Points') }}</span>
            <input type="number" min="0" max="100000" step="1" required x-model.number="points"
                style="width: 5.5rem" class="rounded-lg border border-zinc-200 bg-white px-2.5 py-1.5 text-sm text-zinc-800 shadow-xs dark:border-white/10 dark:bg-white/5 dark:text-white"
                data-test="award-points-{{ $submission->id }}">
        </label>
        <flux:button type="submit" size="sm" :variant="$isApproved ? 'filled' : 'primary'" icon="check" data-test="approve-{{ $submission->id }}">
            <span x-text="@js($isApproved ? __('Update') : __('Award')) + ' +' + (points || 0)">{{ $isApproved ? __('Update') : __('Award') }} +{{ $isApproved ? $submission->points_awarded : $submission->task->points }}</span>
        </flux:button>
    </form>
</flux:card>
