<?php

use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\Committee;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Announcements')] class extends Component {
    public string $title = '';

    public string $body = '';

    public string $committeeId = '';

    /**
     * @return Collection<int, Committee>
     */
    #[Computed]
    public function committees(): Collection
    {
        return Committee::ordered()->get();
    }

    /**
     * @return Collection<int, Announcement>
     */
    #[Computed]
    public function announcements(): Collection
    {
        return Announcement::with(['committee', 'author'])->latest()->get();
    }

    public function post(): void
    {
        Gate::authorize('manage-conference');

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:5000'],
            'committeeId' => ['nullable', 'exists:committees,id'],
        ], attributes: ['committeeId' => 'committee', 'body' => 'message']);

        $announcement = Announcement::create([
            'title' => $validated['title'],
            'body' => $validated['body'],
            'committee_id' => $validated['committeeId'] ?: null,
            'user_id' => Auth::id(),
        ]);

        AuditLog::record('announcement_posted', $announcement, Auth::user(), ['title' => $announcement->title]);

        $this->reset('title', 'body', 'committeeId');
        Flux::toast(variant: 'success', text: __('Announcement posted.'));
    }

    public function delete(int $announcementId): void
    {
        Gate::authorize('manage-conference');

        $announcement = Announcement::findOrFail($announcementId);
        AuditLog::record('announcement_deleted', $announcement, Auth::user(), ['title' => $announcement->title]);
        $announcement->delete();

        Flux::toast(text: __('Announcement deleted.'));
    }
}; ?>

<div class="flex w-full flex-col gap-6 p-6 lg:p-8">
    <div>
        <flux:heading size="xl" level="1">{{ __('Announcements') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Posts appear on delegates’ dashboards straight away. Nothing is emailed, so share anything urgent on WhatsApp too.') }}</flux:text>
    </div>

    <flux:card>
        <form wire:submit="post" class="grid gap-4 md:grid-cols-2">
            <flux:input wire:model="title" :label="__('Title')" />
            <flux:select wire:model="committeeId" :label="__('Audience')">
                <flux:select.option value="">{{ __('All delegates') }}</flux:select.option>
                @foreach ($this->committees as $committee)
                    <flux:select.option value="{{ $committee->id }}">{{ __(':committee delegates', ['committee' => $committee->code]) }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:textarea wire:model="body" :label="__('Message')" rows="4" class="md:col-span-2" />
            <div class="md:col-span-2">
                <flux:button type="submit" variant="primary" icon="megaphone">{{ __('Post announcement') }}</flux:button>
            </div>
        </form>
    </flux:card>

    <div class="flex flex-col gap-3">
        @forelse ($this->announcements as $announcement)
            <flux:card class="flex flex-col gap-2" wire:key="announcement-{{ $announcement->id }}">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <flux:heading>{{ $announcement->title }}</flux:heading>
                        <flux:text class="text-xs">
                            {{ $announcement->committee ? __(':committee delegates', ['committee' => $announcement->committee->code]) : __('All delegates') }}
                            · {{ $announcement->created_at->timezone('Asia/Karachi')->format('j M, g:i a') }}
                            @if ($announcement->author)
                                · {{ $announcement->author->name }}
                            @endif
                        </flux:text>
                    </div>
                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $announcement->id }})" wire:confirm="{{ __('Delete this announcement?') }}" :aria-label="__('Delete')" />
                </div>
                <flux:text class="whitespace-pre-line">{{ $announcement->body }}</flux:text>
            </flux:card>
        @empty
            <flux:text>{{ __('No announcements yet.') }}</flux:text>
        @endforelse
    </div>
</div>
