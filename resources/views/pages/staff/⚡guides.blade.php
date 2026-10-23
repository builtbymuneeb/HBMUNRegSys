<?php

use App\Models\AuditLog;
use App\Models\Committee;
use App\Models\StudyGuide;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Study guides')] class extends Component {
    use WithFileUploads;

    public string $title = '';

    public string $description = '';

    public string $committeeId = '';

    /** @var TemporaryUploadedFile|null */
    public $file;

    /**
     * @return Collection<int, Committee>
     */
    #[Computed]
    public function committees(): Collection
    {
        return Committee::ordered()->get();
    }

    /**
     * @return Collection<int, StudyGuide>
     */
    #[Computed]
    public function guides(): Collection
    {
        return StudyGuide::with(['committee', 'uploader'])->latest()->get();
    }

    public function saveGuide(): void
    {
        Gate::authorize('manage-conference');

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'committeeId' => ['nullable', 'exists:committees,id'],
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,ppt,pptx', 'max:10240'],
        ], attributes: ['committeeId' => 'committee']);

        $file = $validated['file'];

        $guide = StudyGuide::create([
            'committee_id' => $validated['committeeId'] ?: null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?: null,
            'file_path' => $file->store('study-guides', 'local'),
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'uploaded_by' => Auth::id(),
        ]);

        AuditLog::record('study_guide_uploaded', $guide, Auth::user(), ['title' => $guide->title]);

        $this->reset('title', 'description', 'committeeId', 'file');
        Flux::toast(variant: 'success', text: __('Study guide uploaded.'));
    }

    public function delete(int $guideId): void
    {
        Gate::authorize('manage-conference');

        $guide = StudyGuide::findOrFail($guideId);
        Storage::disk('local')->delete($guide->file_path);
        AuditLog::record('study_guide_deleted', $guide, Auth::user(), ['title' => $guide->title]);
        $guide->delete();

        Flux::toast(text: __('Study guide deleted.'));
    }
}; ?>

<div class="flex w-full flex-col gap-6 p-6 lg:p-8">
    <div>
        <flux:heading size="xl" level="1">{{ __('Study guides') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Guides for every committee are shown to all verified delegates. Committee guides are shown once a delegate’s allocation to that committee has been sent.') }}</flux:text>
    </div>

    <flux:card>
        <form wire:submit="saveGuide" class="grid gap-4 md:grid-cols-2">
            <flux:input wire:model="title" :label="__('Title')" :placeholder="__('e.g. UNSC Study Guide')" />
            <flux:select wire:model="committeeId" :label="__('For')">
                <flux:select.option value="">{{ __('Every committee') }}</flux:select.option>
                @foreach ($this->committees as $committee)
                    <flux:select.option value="{{ $committee->id }}">{{ $committee->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:textarea wire:model="description" :label="__('Description (optional)')" rows="2" class="md:col-span-2" />
            <div
                class="md:col-span-2"
                x-data="{
                    uploading: false,
                    progress: 0,
                    totalBytes: 0,
                    startedAt: 0,
                    lastProgressAt: 0,
                    now: 0,
                    bytesPerSecond: 0,
                    stalled: false,
                    offline: ! navigator.onLine,
                    failed: false,
                    timer: null,
                    start(event) {
                        this.totalBytes = event.target.files?.[0]?.size ?? 0;
                        this.uploading = true;
                        this.failed = false;
                        this.progress = 0;
                        this.bytesPerSecond = 0;
                        this.stalled = false;
                        this.startedAt = this.lastProgressAt = this.now = Date.now();
                        clearInterval(this.timer);
                        this.timer = setInterval(() => {
                            this.now = Date.now();
                            this.stalled = this.now - this.lastProgressAt > 5000;
                        }, 1000);
                    },
                    update(percent) {
                        if (percent !== this.progress) {
                            this.lastProgressAt = Date.now();
                            this.stalled = false;
                        }
                        this.progress = percent;
                        const seconds = (Date.now() - this.startedAt) / 1000;
                        if (seconds > 0) {
                            this.bytesPerSecond = (this.totalBytes * percent / 100) / seconds;
                        }
                    },
                    stop(failed = false) {
                        clearInterval(this.timer);
                        this.uploading = false;
                        this.failed = failed;
                    },
                    get slow() {
                        return this.uploading && this.now - this.startedAt > 3000 && this.bytesPerSecond < 100 * 1024;
                    },
                    size(bytes) {
                        return bytes >= 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.round(bytes / 1024) + ' KB';
                    },
                    get eta() {
                        if (! this.bytesPerSecond) return '';
                        const seconds = Math.ceil(this.totalBytes * (100 - this.progress) / 100 / this.bytesPerSecond);
                        return seconds >= 60 ? Math.ceil(seconds / 60) + ' min left' : seconds + ' s left';
                    },
                }"
                x-on:livewire-upload-start="start($event)"
                x-on:livewire-upload-progress="update($event.detail.progress)"
                x-on:livewire-upload-finish="stop()"
                x-on:livewire-upload-error="stop(true)"
                x-on:livewire-upload-cancel="stop()"
                x-on:online.window="offline = false"
                x-on:offline.window="offline = true"
            >
                <flux:input type="file" wire:model="file" :label="__('File')" :description="__('PDF, Word or PowerPoint, up to 10 MB.')" accept=".pdf,.doc,.docx,.ppt,.pptx" />

                <div x-show="uploading" x-cloak class="mt-3 space-y-2">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-zinc-700 dark:text-zinc-200">
                            {{ __('Uploading') }} <span x-text="progress + '%'"></span>
                            <span class="text-zinc-500" x-show="totalBytes" x-text="'· ' + size(totalBytes * progress / 100) + ' of ' + size(totalBytes)"></span>
                        </span>
                        <span class="flex items-center gap-3">
                            <span class="text-zinc-500" x-show="bytesPerSecond" x-text="size(bytesPerSecond) + '/s · ' + eta"></span>
                            <button type="button" class="text-sm text-zinc-500 underline hover:text-zinc-800 dark:hover:text-white" x-on:click="$wire.cancelUpload('file')">{{ __('Cancel') }}</button>
                        </span>
                    </div>
                    <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-white/10" role="progressbar" aria-valuemin="0" aria-valuemax="100" x-bind:aria-valuenow="progress">
                        <div class="h-full rounded-full bg-accent transition-[width] duration-300" x-bind:style="'width: ' + progress + '%'"></div>
                    </div>
                    <flux:text x-show="offline" class="text-sm !text-red-500">{{ __('You appear to be offline. If the upload fails, choose the file again once your connection is back.') }}</flux:text>
                    <flux:text x-show="! offline && stalled" class="text-sm !text-amber-500">{{ __('No progress for a few seconds — your connection may have dropped or is very slow.') }}</flux:text>
                    <flux:text x-show="! offline && ! stalled && slow" class="text-sm !text-amber-500">{{ __('Your internet connection looks slow, so this may take a while. Keep this page open.') }}</flux:text>
                </div>

                <div x-show="! uploading && progress === 100 && ! failed" x-cloak class="mt-2 text-sm text-emerald-500">{{ __('File ready. Click “Upload guide” to save it.') }}</div>
                <div x-show="failed" x-cloak class="mt-2 text-sm text-red-500">{{ __('The upload failed. Check your connection and choose the file again.') }}</div>
            </div>
            <div class="md:col-span-2">
                <flux:button type="submit" variant="primary" icon="arrow-up-tray" :loading="false" wire:loading.attr="disabled" wire:target="file,saveGuide">
                    <span wire:loading.remove wire:target="file,saveGuide">{{ __('Upload guide') }}</span>
                    <span wire:loading wire:target="file">{{ __('Waiting for file…') }}</span>
                    <span wire:loading wire:target="saveGuide">{{ __('Saving…') }}</span>
                </flux:button>
            </div>
        </form>
    </flux:card>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Guide') }}</flux:table.column>
            <flux:table.column>{{ __('For') }}</flux:table.column>
            <flux:table.column>{{ __('Size') }}</flux:table.column>
            <flux:table.column>{{ __('Uploaded') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->guides as $guide)
                <flux:table.row :key="$guide->id">
                    <flux:table.cell>
                        <div class="font-medium text-zinc-800 dark:text-white">{{ $guide->title }}</div>
                        <div class="text-xs">{{ $guide->file_name }}</div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $guide->committee?->code ?? __('Every committee') }}</flux:table.cell>
                    <flux:table.cell>{{ $guide->humanFileSize() }}</flux:table.cell>
                    <flux:table.cell>
                        {{ $guide->created_at->timezone('Asia/Karachi')->format('j M, g:i a') }}
                        <div class="text-xs">{{ $guide->uploader?->name }}</div>
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <div class="flex justify-end gap-2">
                            <flux:button size="sm" icon="arrow-down-tray" :href="route('study-guides.download', $guide)">{{ __('Download') }}</flux:button>
                            <flux:button size="sm" variant="danger" wire:click="delete({{ $guide->id }})" wire:confirm="{{ __('Delete this study guide?') }}">{{ __('Delete') }}</flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="py-10 text-center">{{ __('No study guides yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
