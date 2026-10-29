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
        'heads' => array_values(array_filter([
            ['name' => env('REGISTRATION_HEAD_1_NAME'), 'phone' => env('REGISTRATION_HEAD_1_PHONE')],
            ['name' => env('REGISTRATION_HEAD_2_NAME'), 'phone' => env('REGISTRATION_HEAD_2_PHONE')],
        ], fn (array $head): bool => filled($head['phone']))),
        'email' => env('REGISTRATION_EMAIL'),
    ],

    /*
    | Google Forms for campus ambassador applications, linked from the
    | landing page and /links.
    */

    'ambassador_forms' => [
        'private' => env('AMBASSADOR_FORM_PRIVATE_URL', 'https://forms.gle/r7Lk6X6m5gQYrUgp7'),
        'hb' => env('AMBASSADOR_FORM_HB_URL', 'https://forms.gle/JC5Kqun1i1weDtk7A'),
    ],

    /*
    | Social profiles shown on the /links page. Leave one empty to hide it.
    */

    'socials' => [
        'instagram' => env('SOCIAL_INSTAGRAM_URL'),
        'whatsapp' => env('SOCIAL_WHATSAPP_URL'),
        'linkedin' => env('SOCIAL_LINKEDIN_URL'),
        'facebook' => env('SOCIAL_FACEBOOK_URL'),
        'tiktok' => env('SOCIAL_TIKTOK_URL'),
    ],

];
