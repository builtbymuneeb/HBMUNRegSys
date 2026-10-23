<?php

use App\Models\Announcement;
use App\Models\Committee;
use App\Models\CommitteeSession;
use App\Models\Delegate;
use App\Models\Registration;
use App\Models\StudyGuide;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

new class extends Component
{
    public string $stamp = '';

    public int $lastAnnouncementId = 0;

    public function mount(): void
    {
        $this->stamp = $this->fingerprint();
        $this->lastAnnouncementId = $this->latestVisibleAnnouncementId();
    }

    /**
     * Polled by the browser. Skips the re-render unless something the delegate can see has changed.
     */
    public function refresh(): void
    {
        $stamp = $this->fingerprint();

        if ($stamp === $this->stamp) {
            $this->skipRender();

            return;
        }

        $this->stamp = $stamp;

        $latest = $this->latestVisibleAnnouncementId();
        if ($latest > $this->lastAnnouncementId) {
            Flux::toast(heading: __('New announcement'), text: Announcement::find($latest)?->title ?? '');
        }
        $this->lastAnnouncementId = $latest;
    }

    /**
     * Cheap summary of everything the dashboard shows. Conference-wide parts are cached briefly so
     * every open dashboard shares the same few queries.
     */
    protected function fingerprint(): string
    {
        $shared = Cache::remember('delegate-dashboard:stamp', 10, fn () => collect([Announcement::class, StudyGuide::class, CommitteeSession::class, Committee::class])
            ->map(fn (string $model) => $model::query()->selectRaw('count(*) as c, max(updated_at) as u')->first()?->only(['c', 'u']))
            ->toJson());

        $delegate = Auth::user()->delegate;

        $own = [
            $delegate?->updated_at,
            Registration::whereKey($delegate?->registration_id)->value('updated_at'),
            Delegate::where('registration_id', $delegate?->registration_id)->max('updated_at'),
        ];

        return md5($shared.json_encode($own));
    }

    protected function latestVisibleAnnouncementId(): int
    {
        $delegate = Auth::user()->delegate;
        $committeeId = $delegate?->hasPublishedAllocation() ? $delegate->allocated_committee_id : null;

        return (int) Announcement::visibleToCommittee($committeeId)->max('id');
    }
}; ?>

<div wire:poll.30s="refresh">
    @include('dashboard.delegate', ['user' => auth()->user()])
</div>
