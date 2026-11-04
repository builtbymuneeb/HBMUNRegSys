<?php

namespace App\Support;

use App\Enums\RegistrationStatus;
use App\Enums\UserRole;
use App\Models\AmbassadorSubmission;
use App\Models\InboxMessage;
use App\Models\Registration;
use App\Models\User;

/**
 * The dashboard's navigation, shared by the desktop sidebar and the phone tab bar.
 */
class AppNavigation
{
    /**
     * The staff pages this user may open, in sidebar order.
     *
     * @return list<array{key: string, label: string, short: string, icon: string, url: string, current: bool, badge: int|null}>
     */
    public static function staffItems(User $user): array
    {
        if (! $user->isStaff()) {
            return [];
        }

        $items = [];

        $add = function (string $key, string $label, string $short, string $icon, string $route, string|array $pattern, ?int $badge = null) use (&$items) {
            $items[] = [
                'key' => $key,
                'label' => __($label),
                'short' => __($short),
                'icon' => $icon,
                'url' => route($route),
                'current' => request()->routeIs($pattern),
                'badge' => $badge ?: null,
            ];
        };

        if ($user->can('viewAny', Registration::class)) {
            $add('registrations', 'Registrations', 'Registrations', 'clipboard-document-check', 'staff.registrations.index', 'staff.registrations.*', $user->canReviewRegistrations() ? Registration::where('status', RegistrationStatus::Pending)->count() : null);
        }

        if ($user->can('manage-conference')) {
            $add('allocations', 'Allocations', 'Allocate', 'user-group', 'staff.allocations', 'staff.allocations');
            $add('committees', 'Committees', 'Committees', 'building-library', 'staff.committees', 'staff.committees*');
            $add('guides', 'Study guides', 'Guides', 'book-open', 'staff.guides', 'staff.guides');
            $add('announcements', 'Announcements', 'Updates', 'megaphone', 'staff.announcements', 'staff.announcements');
        }

        if ($user->can('manage-ambassadors')) {
            $add('ambassadors', 'Campus ambassadors', 'Ambassadors', 'ticket', 'staff.ambassadors', ['staff.ambassadors', 'staff.ambassadors.show', 'staff.ambassadors.list']);
            $add('ambassador-tasks', 'Ambassador tasks', 'Tasks', 'clipboard-document-list', 'staff.ambassadors.tasks', 'staff.ambassadors.tasks*', AmbassadorSubmission::pending()->count());
            $add('leaderboard', 'Leaderboard', 'Leaderboard', 'trophy', 'staff.ambassadors.leaderboard', 'staff.ambassadors.leaderboard');
        }

        if ($user->can('use-inbox')) {
            $add('inbox', 'Inbox', 'Inbox', 'inbox', 'staff.inbox', 'staff.inbox*', InboxMessage::visibleTo($user)->unread()->count());
        }

        if ($user->can('manage-conference')) {
            $add('emails', 'Email log', 'Emails', 'envelope', 'staff.emails', 'staff.emails');
        }

        if ($user->can('manage-staff')) {
            $add('team', 'Team', 'Team', 'users', 'staff.team', 'staff.team');
            $add('analytics', 'Visitors', 'Visitors', 'chart-bar', 'staff.analytics', 'staff.analytics');
        }

        return $items;
    }

    /**
     * The campus ambassador portal pages, for anyone linked to an ambassador.
     *
     * @return list<array{key: string, label: string, short: string, icon: string, url: string, current: bool, badge: int|null}>
     */
    public static function ambassadorItems(User $user): array
    {
        if (! $user->isAmbassador()) {
            return [];
        }

        return [
            ['key' => 'ambassador-tasks', 'label' => __('My tasks'), 'short' => __('Tasks'), 'icon' => 'clipboard-document-list', 'url' => route('ambassador.tasks'), 'current' => request()->routeIs('ambassador.tasks*'), 'badge' => null],
            ['key' => 'ambassador-leaderboard', 'label' => __('Leaderboard'), 'short' => __('Leaderboard'), 'icon' => 'trophy', 'url' => route('ambassador.leaderboard'), 'current' => request()->routeIs('ambassador.leaderboard'), 'badge' => null],
        ];
    }

    /**
     * The phone tab bar: Home, up to three main pages, and "More" for the rest.
     *
     * @return array{tabs: list<array{key: string, label: string, icon: string, url: string, current: bool, badge: int|null}>, more: list<array{key: string, label: string, short: string, icon: string, url: string, current: bool, badge: int|null}>}
     */
    public static function phoneTabs(User $user): array
    {
        $home = ['key' => 'home', 'label' => __('Home'), 'icon' => 'home', 'url' => route('dashboard'), 'current' => request()->routeIs('dashboard'), 'badge' => null];

        $me = ['key' => 'me', 'label' => __('Me'), 'icon' => 'user-circle', 'url' => route('profile.edit'), 'current' => request()->routeIs('profile.*', 'appearance.*', 'security.*', 'user-password.*'), 'badge' => null];

        if ($user->role === UserRole::Ambassador) {
            $tabs = array_map(fn (array $item) => [...$item, 'label' => $item['short']], self::ambassadorItems($user));

            return ['tabs' => [$home, ...$tabs, $me], 'more' => []];
        }

        if (! $user->isStaff()) {
            $dashboard = route('dashboard');

            return [
                'tabs' => [
                    $home,
                    ['key' => 'sessions', 'label' => __('Sessions'), 'icon' => 'video-camera', 'url' => $dashboard.'#sessions', 'current' => false, 'badge' => null],
                    ['key' => 'guides', 'label' => __('Guides'), 'icon' => 'book-open', 'url' => $dashboard.'#guides', 'current' => false, 'badge' => null],
                    ['key' => 'updates', 'label' => __('Updates'), 'icon' => 'megaphone', 'url' => $dashboard.'#updates', 'current' => false, 'badge' => null],
                    $me,
                ],
                'more' => [],
            ];
        }

        $items = self::staffItems($user);
        $preferred = ['registrations', 'allocations', 'inbox', 'committees', 'announcements', 'ambassadors', 'ambassador-tasks'];

        usort($items, fn (array $a, array $b) => self::rank($a['key'], $preferred) <=> self::rank($b['key'], $preferred));

        // With four or fewer pages there is room for all of them; otherwise three plus "More".
        $main = count($items) <= 4 ? $items : array_slice($items, 0, 3);
        $more = count($items) <= 4 ? [] : array_slice($items, 3);

        $tabs = [$home, ...array_map(fn (array $item) => [...$item, 'label' => $item['short']], $main)];

        return ['tabs' => $tabs, 'more' => $more];
    }

    /**
     * @param  list<string>  $order
     */
    private static function rank(string $key, array $order): int
    {
        $position = array_search($key, $order, true);

        return $position === false ? PHP_INT_MAX : $position;
    }
}
