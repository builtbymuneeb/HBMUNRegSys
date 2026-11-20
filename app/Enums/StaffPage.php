<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * Staff pages an admin can open up to individual team members, one by one, as
 * view only or with edit rights. Registrations and admins have every page already.
 */
enum StaffPage: string
{
    case Registrations = 'registrations';
    case Allocations = 'allocations';
    case Committees = 'committees';
    case Guides = 'guides';
    case Announcements = 'announcements';
    case Schedule = 'schedule';
    case Ambassadors = 'ambassadors';
    case Emails = 'emails';

    public function label(): string
    {
        return match ($this) {
            self::Registrations => 'Registrations',
            self::Allocations => 'Allocations',
            self::Committees => 'Committees',
            self::Guides => 'Study guides',
            self::Announcements => 'Announcements',
            self::Schedule => 'Schedule',
            self::Ambassadors => 'Campus ambassadors',
            self::Emails => 'Email log',
        };
    }

    /**
     * What "Can edit" lets someone do here, shown when granting access.
     */
    public function editDescription(): string
    {
        return match ($this) {
            self::Registrations => 'Verify, reject, flag and edit registrations and payments.',
            self::Allocations => 'Allocate committees and portfolios and send allocation emails.',
            self::Committees => 'Edit agendas and portfolios.',
            self::Guides => 'Upload and delete study guides.',
            self::Announcements => 'Post and delete announcements.',
            self::Schedule => 'Edit the timetable, room links and what delegates see.',
            self::Ambassadors => 'Add, import and message ambassadors, run tasks and points.',
            self::Emails => 'Nothing to edit; the log is read only.',
        };
    }

    /**
     * Whether the page has anything to change. The email log only shows what was sent.
     */
    public function isEditable(): bool
    {
        return $this !== self::Emails;
    }

    /**
     * The Livewire page components that belong to this page.
     *
     * @return list<string>
     */
    public function componentPatterns(): array
    {
        return match ($this) {
            self::Registrations => ['pages::staff.registrations.*'],
            self::Allocations => ['pages::staff.allocations'],
            self::Committees => ['pages::staff.committees.*'],
            self::Guides => ['pages::staff.guides'],
            self::Announcements => ['pages::staff.announcements'],
            self::Schedule => ['pages::staff.schedule'],
            self::Ambassadors => ['pages::staff.ambassadors.*'],
            self::Emails => ['pages::staff.emails'],
        };
    }

    /**
     * Route names that belong to this page, for the "view only" banner.
     *
     * @return list<string>
     */
    public function routePatterns(): array
    {
        return match ($this) {
            self::Registrations => ['staff.registrations.*'],
            self::Allocations => ['staff.allocations'],
            self::Committees => ['staff.committees*'],
            self::Guides => ['staff.guides'],
            self::Announcements => ['staff.announcements'],
            self::Schedule => ['staff.schedule'],
            self::Ambassadors => ['staff.ambassadors*'],
            self::Emails => ['staff.emails'],
        };
    }

    public static function forComponent(string $name): ?self
    {
        foreach (self::cases() as $page) {
            foreach ($page->componentPatterns() as $pattern) {
                if (Str::is($pattern, $name)) {
                    return $page;
                }
            }
        }

        return null;
    }

    public static function forCurrentRoute(): ?self
    {
        foreach (self::cases() as $page) {
            if (request()->routeIs(...$page->routePatterns())) {
                return $page;
            }
        }

        return null;
    }
}
