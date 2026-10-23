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

    public function upload(): void
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
        <form wire:submit="upload" class="grid gap-4 md:grid-cols-2">
            <flux:input wire:model="title" :label="__('Title')" :placeholder="__('e.g. UNSC Study Guide')" />
            <flux:select wire:model="committeeId" :label="__('For')">
                <flux:select.option value="">{{ __('Every committee') }}</flux:select.option>
                @foreach ($this->committees as $committee)
                    <flux:select.option value="{{ $committee->id }}">{{ $committee->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:textarea wire:model="description" :label="__('Description (optional)')" rows="2" class="md:col-span-2" />
            <div class="md:col-span-2">
                <flux:input type="file" wire:model="file" :label="__('File')" :description="__('PDF, Word or PowerPoint, up to 10 MB.')" accept=".pdf,.doc,.docx,.ppt,.pptx" />
                <div wire:loading wire:target="file" class="mt-2 text-sm text-zinc-500">{{ __('Uploading…') }}</div>
            </div>
            <div class="md:col-span-2">
                <flux:button type="submit" variant="primary" icon="arrow-up-tray" wire:loading.attr="disabled" wire:target="file,upload">{{ __('Upload guide') }}</flux:button>
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
