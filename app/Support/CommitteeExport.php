<?php

namespace App\Support;

use App\Models\Committee;
use App\Models\Delegate;
use Closure;

/**
 * The columns staff can pick when exporting a committee's allocated delegates.
 */
class CommitteeExport
{
    /**
     * Columns ticked when the export dialog opens.
     *
     * @var list<string>
     */
    public const DEFAULT_COLUMNS = ['committee', 'portfolio', 'name', 'delegation', 'email', 'phone'];

    /**
     * Every column, in the order it appears in the file: key => label.
     *
     * @return array<string, string>
     */
    public static function columns(): array
    {
        return [
            'committee' => __('Committee'),
            'portfolio' => __('Portfolio'),
            'name' => __('Delegate name'),
            'email' => __('Email'),
            'phone' => __('Phone'),
            'cnic' => __('CNIC / B-Form'),
            'reference' => __('Registration reference'),
            'delegation' => __('Institution / delegation'),
            'type' => __('Registration type'),
            'city' => __('City'),
            'contact_name' => __('Contact name'),
            'contact_phone' => __('Contact phone'),
            'homebridge' => __('Homebridge student'),
            'experience' => __('MUN experience'),
            'preference' => __('Their preference here'),
            'allocation_sent' => __('Allocation sent'),
        ];
    }

    /**
     * The picked column keys that exist, in file order; nothing valid picked means the defaults.
     *
     * @return list<string>
     */
    public static function pick(mixed $picked): array
    {
        $picked = is_array($picked) ? array_map('strval', $picked) : [];
        $keys = array_values(array_filter(array_keys(self::columns()), fn (string $key) => in_array($key, $picked, true)));

        return $keys ?: self::DEFAULT_COLUMNS;
    }

    /**
     * One delegate's value for a column.
     */
    public static function value(string $column, Delegate $delegate, Committee $committee): string
    {
        $registration = $delegate->registration;

        $resolve = match ($column) {
            'committee' => fn () => $committee->code,
            'portfolio' => fn () => $delegate->allocated_portfolio,
            'name' => fn () => $delegate->name,
            'email' => fn () => $delegate->email,
            'phone' => fn () => $delegate->phone,
            'cnic' => fn () => $delegate->cnic,
            'reference' => fn () => $registration->reference,
            'delegation' => fn () => $registration->displayName(),
            'type' => fn () => $registration->type->label(),
            'city' => fn () => $registration->city,
            'contact_name' => fn () => $registration->contact_name,
            'contact_phone' => fn () => $registration->contact_phone,
            'homebridge' => fn () => $delegate->is_homebridge ? __('Yes') : __('No'),
            'experience' => fn () => $delegate->mun_experience?->label(),
            'preference' => fn () => $delegate->preferenceFor($committee),
            'allocation_sent' => fn () => $delegate->hasPublishedAllocation()
                ? $delegate->allocation_published_at?->timezone('Asia/Karachi')->format('Y-m-d H:i') ?? __('Yes')
                : __('Not sent'),
            default => fn () => '',
        };

        /** @var Closure(): ?string $resolve */
        return (string) $resolve();
    }
}
