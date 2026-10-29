<?php

namespace App\Http\Controllers;

use App\Models\CampusAmbassador;
use App\Support\ReferralCode;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AmbassadorListController extends Controller
{
    /**
     * Rows that fit on the first page, under the crest.
     */
    public const FIRST_PAGE_ROWS = 16;

    /**
     * Rows that fit on each "continued" page.
     */
    public const CONTINUED_PAGE_ROWS = 27;

    /**
     * Rows given up on the last page to make room for the closing motto.
     */
    public const CLOSING_ROWS = 4;

    /**
     * The branded, print-ready A4 list of ambassadors (Save as PDF from the browser),
     * optionally limited to a range of codes, e.g. ?from=HB05&to=HB20.
     */
    public function __invoke(Request $request): View
    {
        $all = self::inCodeOrder(CampusAmbassador::all(['id', 'code', 'code_key', 'name', 'institution', 'city']));
        $ambassadors = self::between($all, $request->query('from'), $request->query('to'));
        $isRange = $ambassadors->count() !== $all->count();

        return view('ambassador-list', [
            'pages' => self::paginate($ambassadors),
            'count' => $ambassadors->count(),
            'range' => $isRange && $ambassadors->isNotEmpty() ? $ambassadors->first()->code.' – '.$ambassadors->last()->code : null,
            'autoPrint' => $request->boolean('print'),
        ]);
    }

    /**
     * Sort by the number in the code (HB2 before HB10), with codes of any other shape after, alphabetically.
     *
     * @param  Collection<int, CampusAmbassador>  $ambassadors
     * @return Collection<int, CampusAmbassador>
     */
    public static function inCodeOrder(Collection $ambassadors): Collection
    {
        return $ambassadors->sortBy([
            fn (CampusAmbassador $a, CampusAmbassador $b) => (ReferralCode::number($a->code) ?? PHP_INT_MAX) <=> (ReferralCode::number($b->code) ?? PHP_INT_MAX),
            fn (CampusAmbassador $a, CampusAmbassador $b) => strnatcasecmp($a->code, $b->code),
        ])->values();
    }

    /**
     * The ambassadors from one code to another, inclusive and in either order; an unknown or empty end means "from the start" or "to the end".
     *
     * @param  Collection<int, CampusAmbassador>  $ambassadors
     * @return Collection<int, CampusAmbassador>
     */
    public static function between(Collection $ambassadors, mixed $from, mixed $to): Collection
    {
        $position = function (mixed $code, int $default) use ($ambassadors): int {
            $key = is_string($code) ? ReferralCode::key($code) : null;
            $index = $key === null ? false : $ambassadors->search(fn (CampusAmbassador $ambassador) => $ambassador->code_key === $key);

            return $index === false ? $default : $index;
        };

        $start = $position($from, 0);
        $end = $position($to, $ambassadors->count() - 1);

        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        return $ambassadors->slice($start, $end - $start + 1)->values();
    }

    /**
     * Split the rows into A4 pages, leaving room for the closing motto on the last one.
     *
     * @template T
     *
     * @param  Collection<int, T>  $rows
     * @return list<Collection<int, T>>
     */
    public static function paginate(Collection $rows): array
    {
        $pages = [];
        $remaining = $rows->values();

        do {
            $capacity = $pages === [] ? self::FIRST_PAGE_ROWS : self::CONTINUED_PAGE_ROWS;

            if ($remaining->count() <= $capacity - self::CLOSING_ROWS) {
                $take = $remaining->count();
            } elseif ($remaining->count() <= $capacity) {
                // Everything fits, but not with the motto too: move a few rows over so the last page isn't a lone motto.
                $take = $remaining->count() - self::CLOSING_ROWS;
            } else {
                $take = $capacity;
            }

            $pages[] = $remaining->take($take)->values();
            $remaining = $remaining->skip($take)->values();
        } while ($remaining->isNotEmpty());

        return $pages;
    }
}
