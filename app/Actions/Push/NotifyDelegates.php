<?php

namespace App\Actions\Push;

use App\Enums\RegistrationStatus;
use App\Models\Announcement;
use App\Models\CommitteeSession;
use App\Models\Delegate;
use App\Models\StudyGuide;
use App\Models\User;
use App\Notifications\DashboardUpdate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;

/**
 * Push dashboard updates to the delegates who can see them, using the same titles and tags as the
 * open dashboard (see the delegate-dashboard component) so the two collapse into one notification.
 */
class NotifyDelegates
{
    public function aboutAnnouncement(Announcement $announcement): void
    {
        $this->send(
            $this->verifiedDelegatesSeeing($announcement->committee_id),
            new DashboardUpdate(__('New announcement'), $announcement->title, 'announcement-'.$announcement->id),
        );
    }

    public function aboutStudyGuide(StudyGuide $guide): void
    {
        $this->send(
            $this->verifiedDelegatesSeeing($guide->committee_id),
            new DashboardUpdate(__('New study guide'), $guide->title, 'guide-'.$guide->id),
        );
    }

    public function aboutAllocation(Delegate $delegate): void
    {
        if (! $delegate->hasPublishedAllocation() || ! $delegate->user_id) {
            return;
        }

        $this->send(
            User::whereKey($delegate->user_id)->whereHas('delegate', fn (Builder $query) => $query->whereKey($delegate->id)),
            new DashboardUpdate(__('Your allocation is out'), $delegate->allocated_portfolio.' · '.$delegate->allocatedCommittee->label(), 'allocation'),
        );
    }

    public function aboutSession(CommitteeSession $session, bool $isNew): void
    {
        $this->send(
            User::whereHas('delegate', fn (Builder $query) => $this->withPublishedAllocationTo($query, $session->committee_id)),
            new DashboardUpdate($isNew ? __('New session scheduled') : __('Session updated'), $session->title.' · '.$session->timeLabel(), 'session-'.$session->id),
        );
    }

    /**
     * Verified delegates who see an item for the given committee, or everyone verified when it is for all committees.
     *
     * @return Builder<User>
     */
    protected function verifiedDelegatesSeeing(?int $committeeId): Builder
    {
        return User::whereHas('delegate', fn (Builder $query) => $query
            ->whereHas('registration', fn (Builder $query) => $query->where('status', RegistrationStatus::Verified))
            ->when($committeeId, fn (Builder $query) => $this->withPublishedAllocationTo($query, $committeeId)));
    }

    /**
     * Mirrors Delegate::hasPublishedAllocation() for the given committee.
     *
     * @param  Builder<Delegate>  $query
     * @return Builder<Delegate>
     */
    protected function withPublishedAllocationTo(Builder $query, int $committeeId): Builder
    {
        return $query
            ->where('allocated_committee_id', $committeeId)
            ->whereNotNull('allocated_portfolio')
            ->where('allocated_portfolio', '!=', '')
            ->whereNotNull('allocation_published_at');
    }

    /**
     * @param  Builder<User>  $users
     */
    protected function send(Builder $users, DashboardUpdate $notification): void
    {
        Notification::send($users->whereHas('pushSubscriptions')->get(), $notification);
    }
}
