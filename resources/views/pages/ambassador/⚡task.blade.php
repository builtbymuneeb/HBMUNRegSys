<?php

use App\Actions\Ambassadors\SubmitTask;
use App\Models\AmbassadorSubmission;
use App\Enums\SubmissionStatus;
use App\Models\AmbassadorTask;
use App\Models\AuditLog;
use App\Models\CampusAmbassador;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Task')] class extends Component {
    use WithFileUploads;

    public AmbassadorTask $task;

    public string $note = '';

    public string $link = '';

    /** @var list<\Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $proofFiles = [];

    /** Each picked file lands here first, then joins $proofFiles. */
    public $newProof;

    public function mount(): void
    {
        abort_unless($this->task->isFor($this->ambassador) && ($this->task->isOpen() || $this->mySubmissions->isNotEmpty()), 404);
    }

    #[Computed]
    public function ambassador(): CampusAmbassador
    {
        return Auth::user()->ambassador;
    }

    /**
     * @return Collection<int, AmbassadorSubmission>
     */
    #[Computed]
    public function mySubmissions(): Collection
    {
        return $this->task->submissions()->where('campus_ambassador_id', $this->ambassador->id)->with('files')->latest('id')->get();
    }

    #[Computed]
    public function canSubmit(): bool
    {
        return $this->task->canBeSubmittedBy($this->ambassador);
    }

    public function updatedNewProof(): void
    {
        $this->resetValidation('newProof');

        $validator = Validator::make(['newProof' => $this->newProof], ['newProof' => self::fileRules()], attributes: ['newProof' => 'file']);

        if ($validator->fails()) {
            $this->addError('newProof', $validator->errors()->first('newProof'));
        } elseif (count($this->proofFiles) >= AmbassadorSubmission::MAX_FILES) {
            $this->addError('newProof', __('You can add up to :max files.', ['max' => AmbassadorSubmission::MAX_FILES]));
        } else {
            $this->proofFiles[] = $this->newProof;
        }

        $this->reset('newProof');
    }

    public function removeProof(int $index): void
    {
        unset($this->proofFiles[$index]);
        $this->proofFiles = array_values($this->proofFiles);
    }

    /**
     * @return list<string>
     */
    public static function fileRules(): array
    {
        return ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif,pdf,mp4,mov', 'max:25600'];
    }

    public function submit(SubmitTask $submitTask): void
    {
        if (Auth::user()->isPreviewingRole()) {
            Flux::toast(variant: 'warning', text: __('You are viewing as this ambassador, so nothing was submitted.'));

            return;
        }

        $this->validate([
            'note' => ['nullable', 'string', 'max:2000'],
            'link' => ['nullable', 'url:http,https', 'max:2048'],
            'proofFiles' => ['array', 'max:'.AmbassadorSubmission::MAX_FILES],
            'proofFiles.*' => self::fileRules(),
        ], attributes: ['proofFiles.*' => 'file']);

        if ($this->proofFiles === [] && blank($this->link) && blank($this->note)) {
            $this->addError('proofFiles', __('Add a screenshot, a link or a note so the team can check it.'));

            return;
        }

        $submitTask->handle($this->task, $this->ambassador, $this->note, $this->link, $this->proofFiles, Auth::user());

        $this->reset('note', 'link', 'proofFiles', 'newProof');
        unset($this->mySubmissions, $this->canSubmit);

        Flux::toast(variant: 'success', heading: __('Marked as done'), text: __('The team will check your proof and add your points.'));
    }

    /**
     * An ambassador takes back something they handed in that hasn't been approved
     * (waiting or sent back). Approved ones stay, since points were given for them.
     */
    public function deleteSubmission(int $submissionId): void
    {
        if (Auth::user()->isPreviewingRole()) {
            Flux::toast(variant: 'warning', text: __('You are viewing as this ambassador, so nothing was deleted.'));

            return;
        }

        $submission = $this->task->submissions()
            ->where('campus_ambassador_id', $this->ambassador->id)
            ->with('files')
            ->findOrFail($submissionId);

        if ($submission->status === SubmissionStatus::Approved) {
            Flux::toast(variant: 'danger', text: __('This one is approved and has points, so it can’t be deleted. Ask the team if something is wrong.'));

            return;
        }

        $paths = $submission->files->pluck('path')->all();

        AuditLog::record('ambassador_submission_deleted', $submission, Auth::user(), ['task' => $this->task->title, 'code' => $this->ambassador->code, 'status' => $submission->status->value]);
        $submission->delete();
        Storage::disk('local')->delete($paths);

        unset($this->mySubmissions, $this->canSubmit);

        Flux::toast(text: __('Deleted.'));
    }
}; ?>

<div class="mx-auto flex w-full max-w-3xl flex-col gap-4 p-6 lg:gap-6 lg:p-8">
    <div>
        <flux:link :href="route('ambassador.tasks')" wire:navigate class="text-sm">← {{ __('My tasks') }}</flux:link>
        <div class="mt-2 flex flex-wrap items-center gap-2">
            <flux:heading size="xl" level="1">{{ $task->title }}</flux:heading>
        </div>
        <div class="mt-2 flex flex-wrap gap-2">
            <flux:badge color="purple" icon="star">{{ trans_choice(':count point|:count points', $task->points) }}{{ $task->repeatable ? ' '.__('each time') : '' }}</flux:badge>
            <flux:badge icon="arrow-path">{{ $task->frequencyLabel() }}</flux:badge>
            @if ($task->due_at)
                <flux:badge :color="$task->isPastDue() ? 'red' : 'zinc'" icon="calendar">
                    {{ $task->isPastDue() ? __('Closed :date', ['date' => $task->due_at->timezone('Asia/Karachi')->format('j M, g:i a')]) : __('Due :date', ['date' => $task->due_at->timezone('Asia/Karachi')->format('j M, g:i a')]) }}
                </flux:badge>
            @endif
        </div>
    </div>

    @if (filled($task->description))
        <flux:card>
            <div class="text-[15px] leading-relaxed break-words whitespace-pre-line text-zinc-700 dark:text-zinc-200">{{ $task->description }}</div>
        </flux:card>
    @endif

    @if ($this->canSubmit)
        <flux:card class="flex flex-col gap-4" data-test="submit-task">
            <div>
                <flux:heading size="lg">{{ __('Mark as done') }}</flux:heading>
                <flux:text>{{ __('Upload a screenshot or other proof, add a link, or leave a note. The team checks it and adds your points.') }}</flux:text>
            </div>

            <div class="flex flex-col gap-3"
                x-data="{
                    uploading: false,
                    async pick(input) {
                        const files = [...input.files];
                        input.value = '';
                        this.uploading = true;
                        for (const file of files) {
                            await new Promise((done) => this.$wire.$upload('newProof', file, done, done));
                        }
                        this.uploading = false;
                    },
                }">
                @if ($proofFiles)
                    <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3" data-test="proof-files">
                        @foreach ($proofFiles as $index => $file)
                            <li class="flex flex-col gap-2 rounded-xl border border-zinc-200 p-2 dark:border-white/10" wire:key="proof-{{ $index }}-{{ $file->getFilename() }}">
                                @if ($file->isPreviewable())
                                    <img src="{{ $file->temporaryUrl() }}" alt="" class="h-28 w-full rounded-lg object-cover">
                                @else
                                    <div class="flex h-28 items-center justify-center rounded-lg bg-zinc-100 dark:bg-white/5"><flux:icon.document class="size-8 text-zinc-400" /></div>
                                @endif
                                <div class="flex items-center justify-between gap-2 text-xs">
                                    <span class="min-w-0 truncate text-zinc-500">{{ $file->getClientOriginalName() }}</span>
                                    <button type="button" wire:click="removeProof({{ $index }})" class="shrink-0 font-medium text-red-500 hover:underline">{{ __('Remove') }}</button>
                                </div>
                                <flux:error name="proofFiles.{{ $index }}" />
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if (count($proofFiles) < \App\Models\AmbassadorSubmission::MAX_FILES)
                    <label class="flex min-h-28 cursor-pointer flex-col items-center justify-center gap-1.5 rounded-xl border border-dashed border-zinc-300 p-5 text-center hover:bg-zinc-50 has-focus-visible:outline-2 dark:border-white/15 dark:hover:bg-white/4">
                        <flux:icon.arrow-up-tray class="size-6 text-zinc-400" />
                        <span class="font-medium" x-text="uploading ? @js(__('Uploading…')) : @js($proofFiles ? __('Add another file') : __('Upload proof'))">{{ $proofFiles ? __('Add another file') : __('Upload proof') }}</span>
                        <span class="text-xs text-zinc-500">{{ __('Screenshots, photos, PDFs or short videos. Up to :max files, 25 MB each.', ['max' => \App\Models\AmbassadorSubmission::MAX_FILES]) }}</span>
                        <input type="file" multiple accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.mp4,.mov" class="sr-only" x-on:change.stop="pick($el)" data-test="proof-input">
                    </label>
                @endif
                <flux:error name="newProof" />
                <flux:error name="proofFiles" />
            </div>

            <flux:input wire:model="link" type="url" :label="__('Link (optional)')" placeholder="https://" :description="__('For example your Instagram story or post.')" />
            <flux:textarea wire:model="note" :label="__('Note (optional)')" rows="3" />
            <flux:error name="submission" />

            <div class="flex justify-end">
                <flux:button variant="primary" icon="check" wire:click="submit" wire:loading.attr="disabled" wire:target="submit,newProof" data-test="mark-done">{{ __('Mark as done') }}</flux:button>
            </div>
        </flux:card>
    @elseif ($task->isOpen())
        <flux:callout icon="check-circle" color="green" :heading="__('All done')" :text="$this->mySubmissions->contains(fn ($submission) => $submission->isPending()) ? __('The team is checking your proof.') : __('You’ve done this task as many times as it counts.')" />
    @else
        <flux:callout icon="lock-closed" :heading="__('This task is closed')" />
    @endif

    @if ($this->mySubmissions->isNotEmpty())
        <section class="flex flex-col gap-2">
            <flux:heading size="lg" class="px-1">{{ __('What you handed in') }}</flux:heading>
            @foreach ($this->mySubmissions as $submission)
                <flux:card class="flex flex-col gap-3" wire:key="submission-{{ $submission->id }}">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <flux:text class="text-sm">{{ $submission->created_at->timezone('Asia/Karachi')->format('j M, g:i a') }}</flux:text>
                        <div class="flex items-center gap-2">
                            @if ($submission->points_awarded)
                                <flux:badge size="sm" color="purple">+{{ $submission->points_awarded }}</flux:badge>
                            @endif
                            <flux:badge size="sm" :color="$submission->status->color()">{{ $submission->status->label() }}</flux:badge>
                        </div>
                    </div>
                    @include('partials.ambassador-proof', ['submission' => $submission])
                    @if ($submission->review_note)
                        <flux:callout :color="$submission->status === \App\Enums\SubmissionStatus::Rejected ? 'red' : 'zinc'" icon="chat-bubble-left" :heading="__('From the team')" :text="$submission->review_note" />
                    @endif
                    @if ($submission->status !== \App\Enums\SubmissionStatus::Approved)
                        <div class="flex justify-end">
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="deleteSubmission({{ $submission->id }})" data-test="delete-submission-{{ $submission->id }}"
                                wire:confirm="{{ __('Delete what you handed in? Your proof is removed and the team won’t review it.') }}">{{ __('Delete') }}</flux:button>
                        </div>
                    @endif
                </flux:card>
            @endforeach
        </section>
    @endif
</div>
