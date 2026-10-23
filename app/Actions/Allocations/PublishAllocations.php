<?php

namespace App\Actions\Allocations;

use App\Models\AuditLog;
use App\Models\Delegate;
use App\Models\User;
use App\Notifications\AllocationPublished;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class PublishAllocations
{
    /**
     * Show allocations on the delegates' dashboards and email each delegate theirs.
     *
     * @param  Collection<int, Delegate>  $delegates
     * @return int The number of allocations sent.
     */
    public function handle(Collection $delegates, User $staff): int
    {
        $sent = 0;

        foreach ($delegates as $delegate) {
            if (! $delegate->isAllocated() || $delegate->allocation_published_at !== null) {
                continue;
            }

            $delegate->forceFill(['allocation_published_at' => now()])->save();

            AuditLog::record('allocation_sent', $delegate->registration, $staff, [
                'delegate' => $delegate->name,
                'committee' => $delegate->allocatedCommittee?->code,
                'portfolio' => $delegate->allocated_portfolio,
            ]);

            $notification = new AllocationPublished($delegate);
            $delegate->user
                ? $delegate->user->notify($notification)
                : Notification::route('mail', $delegate->email)->notify($notification);

            $sent++;
        }

        return $sent;
    }
}
