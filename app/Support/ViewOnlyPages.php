<?php

namespace App\Support;

use App\Enums\StaffPage;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

use function Livewire\on;

/**
 * Keeps "view only" team members from changing anything on a staff page. Every
 * action a page runs goes through here first, so a page needs no checks of its own:
 * anyone who can't edit the page only gets the actions that just look around.
 */
class ViewOnlyPages
{
    /**
     * Actions that only read: polling, paging and switching filters.
     *
     * @var list<string>
     */
    public const READ_ACTIONS = [
        'poll',
        'gotoPage',
        'nextPage',
        'previousPage',
        'setPage',
        'resetPage',
        'showStatus',
        'showTag',
    ];

    public static function register(): void
    {
        on('call', function (Component $component, string $method, array $params, $context, callable $returnEarly): void {
            if (self::allows($component, $method)) {
                return;
            }

            // Someone without any access to the page gets a plain refusal, as before.
            abort_unless(Auth::user()?->canViewPage(StaffPage::forComponent($component->getName())), 403);

            Flux::toast(variant: 'warning', text: __('You have view-only access to this page, so nothing was changed.'));

            $returnEarly();
        });
    }

    public static function allows(Component $component, string $method): bool
    {
        $page = StaffPage::forComponent($component->getName());

        if ($page === null) {
            return true;
        }

        // Livewire's own $refresh, $set and friends only re-render.
        if (str_starts_with($method, '$') || in_array($method, self::READ_ACTIONS, true)) {
            return true;
        }

        return (bool) Auth::user()?->canEditPage($page);
    }
}
