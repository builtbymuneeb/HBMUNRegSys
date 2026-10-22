<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Conference
    |--------------------------------------------------------------------------
    |
    | Public details about Homebridge MUN shown on the landing page, the
    | registration form and in emails.
    |
    */

    'name' => env('CONFERENCE_NAME', 'Homebridge MUN ’26'),

    'starts_at' => env('CONFERENCE_STARTS_AT', '2026-11-21 09:00:00'),

    'dates_label' => env('CONFERENCE_DATES_LABEL', '21–22 November 2026'),

    'reference_prefix' => env('CONFERENCE_REFERENCE_PREFIX', 'HB26'),

    /*
    |--------------------------------------------------------------------------
    | Fees (PKR)
    |--------------------------------------------------------------------------
    */

    'fees' => [
        'per_delegate' => (int) env('CONFERENCE_FEE_PER_DELEGATE', 1000),
        'per_delegation' => (int) env('CONFERENCE_FEE_PER_DELEGATION', 1000),
    ],

    'delegation' => [
        'min_delegates' => 3,
        'max_delegates' => 6,
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Details
    |--------------------------------------------------------------------------
    */

    'payment' => [
        'account_title' => env('PAYMENT_ACCOUNT_TITLE'),
        'service' => env('PAYMENT_SERVICE'),
        'account_number' => env('PAYMENT_ACCOUNT_NUMBER'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Registration Contacts
    |--------------------------------------------------------------------------
    */

    'contacts' => [
        'phones' => array_filter([
            env('REGISTRATION_HEAD_1_PHONE'),
            env('REGISTRATION_HEAD_2_PHONE'),
        ]),
        'email' => env('REGISTRATION_EMAIL'),
    ],

];
