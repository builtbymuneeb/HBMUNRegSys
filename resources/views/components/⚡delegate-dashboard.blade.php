<?php

use App\Enums\RegistrationStatus;
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

    /**
     * What the delegate could see at the last check, used to spot what is new.
     *
     * @var array{verified: bool, allocation: ?string, announcements: list<int>, guides: list<int>, sessions: array<int, string>}
     */
    public array $seen = ['verified' => false, 'allocation' => null, 'announcements' => [], 'guides' => [], 'sessions' => []];

    /**
     * What changed since the page loaded, shown with a "New" highlight.
     *
     * @var array{allocation: bool, announcements: list<int>, guides: list<int>, sessions: list<int>}
     */
    public array $fresh = ['allocation' => false, 'announcements' => [], 'guides' => [], 'sessions' => []];

    public function mount(): void
    {
        $this->stamp = $this->fingerprint();
        $this->seen = $this->snapshot();
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

        $now = $this->snapshot();
        $updates = $this->updatesSince($this->seen, $now);
        $this->seen = $now;

        if ($updates === []) {
            return;
        }

        if (count($updates) > 2) {
            Flux::toast(heading: __(':count new updates', ['count' => count($updates)]), text: collect($updates)->pluck('title')->unique()->join(', '));
        } else {
            foreach ($updates as $update) {
                Flux::toast(heading: $update['title'], text: $update['body']);
            }
        }

        $this->dispatch('dashboard-updates', updates: $updates);
    }

    /**
     * Turn the difference between two snapshots into updates for the delegate, and mark them to be highlighted.
     *
     * @param  array{verified: bool, allocation: ?string, announcements: list<int>, guides: list<int>, sessions: array<int, string>}  $before
     * @param  array{verified: bool, allocation: ?string, announcements: list<int>, guides: list<int>, sessions: array<int, string>}  $after
     * @return list<array{title: string, body: string, tag: string}>
     */
    protected function updatesSince(array $before, array $after): array
    {
        $updates = [];

        if (! $before['verified'] && $after['verified']) {
            $updates[] = ['title' => __('Registration verified'), 'body' => __('Your payment is confirmed. Welcome to :name!', ['name' => config('conference.name')]), 'tag' => 'verified'];
        }

        $allocationChanged = $after['allocation'] !== $before['allocation'];
        if ($allocationChanged && $after['allocation'] !== null) {
            $this->fresh['allocation'] = true;
            $updates[] = ['title' => __('Your allocation is out'), 'body' => $after['allocation'], 'tag' => 'allocation'];
        }

        $newAnnouncements = array_values(array_diff($after['announcements'], $before['announcements']));
        $this->fresh['announcements'] = array_values(array_unique([...$this->fresh['announcements'], ...$newAnnouncements]));
        foreach (Announcement::whereKey($newAnnouncements)->latest('id')->get() as $announcement) {
            $updates[] = ['title' => __('New announcement'), 'body' => $announcement->title, 'tag' => 'announcement-'.$announcement->id];
        }

        $newGuides = array_values(array_diff($after['guides'], $before['guides']));
        $this->fresh['guides'] = array_values(array_unique([...$this->fresh['guides'], ...$newGuides]));
        foreach (StudyGuide::whereKey($newGuides)->latest('id')->get() as $guide) {
            $updates[] = ['title' => __('New study guide'), 'body' => $guide->title, 'tag' => 'guide-'.$guide->id];
        }

        // A fresh allocation already covers that committee's sessions, so only report changes within the same committee.
        if (! $allocationChanged) {
            $changedSessions = array_keys(array_diff_assoc($after['sessions'], $before['sessions']));
            $this->fresh['sessions'] = array_values(array_unique([...$this->fresh['sessions'], ...$changedSessions]));
            foreach (CommitteeSession::whereKey($changedSessions)->orderBy('starts_at')->get() as $session) {
                $updates[] = [
                    'title' => array_key_exists($session->id, $before['sessions']) ? __('Session updated') : __('New session scheduled'),
                    'body' => $session->title.' · '.$session->timeLabel(),
                    'tag' => 'session-'.$session->id,
                ];
            }
        }

        return $updates;
    }

    /**
     * The ids and versions of everything on the dashboard the delegate is allowed to see.
     *
     * @return array{verified: bool, allocation: ?string, announcements: list<int>, guides: list<int>, sessions: array<int, string>}
     */
    protected function snapshot(): array
    {
        $delegate = Auth::user()->delegate()->first();
        $verified = Registration::whereKey($delegate?->registration_id)->value('status') === RegistrationStatus::Verified;
        $committee = $delegate?->hasPublishedAllocation() ? $delegate->allocatedCommittee : null;

        return [
            'verified' => $verified,
            'allocation' => $committee ? $delegate->allocated_portfolio.' · '.$committee->label() : null,
            'announcements' => $verified ? Announcement::visibleToCommittee($committee?->id)->pluck('id')->all() : [],
            'guides' => $verified ? StudyGuide::visibleToCommittee($committee?->id)->pluck('id')->all() : [],
            'sessions' => $committee
                ? CommitteeSession::where('committee_id', $committee->id)->get()
                    ->mapWithKeys(fn (CommitteeSession $session) => [$session->id => md5($session->starts_at.'|'.$session->ends_at.'|'.$session->meeting_url)])
                    ->all()
                : [],
        ];
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
}; ?>

<div wire:poll.30s="refresh" x-data x-init="window.hbNotifications.sync()" x-on:dashboard-updates.window="window.hbNotifications.show($event.detail.updates)" class="flex flex-col gap-6">
    @include('partials.notification-prompt')
    @include('dashboard.delegate', ['user' => auth()->user(), 'fresh' => $fresh])
</div>
