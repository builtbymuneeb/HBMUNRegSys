@php
    $contacts = config('conference.contacts');

    $links = [
        ['label' => __('Register your delegation'), 'note' => __('Registrations are open'), 'href' => route('register'), 'primary' => true],
        ['label' => __('Check your registration status'), 'href' => route('registration.status')],
        ['label' => __('Apply as a Regular Campus Ambassador'), 'href' => 'https://forms.gle/r7Lk6X6m5gQYrUgp7', 'external' => true],
        ['label' => __('Apply as an HB Campus Ambassador'), 'href' => 'https://forms.gle/JC5Kqun1i1weDtk7A', 'external' => true],
    ];

    if ($contacts['email']) {
        $links[] = ['label' => __('Email us'), 'note' => $contacts['email'], 'href' => 'mailto:'.$contacts['email']];
    }

    $socialNames = ['instagram' => 'Instagram', 'whatsapp' => 'WhatsApp channel', 'linkedin' => 'LinkedIn', 'facebook' => 'Facebook', 'tiktok' => 'TikTok'];
    $socials = array_filter(config('conference.socials'));
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />

        <title>{{ __('Links') }} · {{ config('conference.name') }}</title>

        @include('partials.icons')

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @include('partials.clarity')
    </head>
    <body class="min-h-screen bg-[#2f1942] font-sans text-white antialiased">
        <div class="relative isolate min-h-screen">
            {{-- Ornamental frame: corners keep their shape, the straight edges stretch with the page --}}
            <div class="pointer-events-none absolute inset-0 -z-10 [border-style:solid] [border-width:120px_92px] [border-image:url('/images/links-frame.webp')_390_300_fill] sm:[border-width:165px_127px]" aria-hidden="true"></div>

        <main class="mx-auto flex min-h-screen w-full max-w-md flex-col items-center px-9 pt-16 pb-28 sm:px-4 sm:pt-24 sm:pb-36">
            <span class="hb-art hb-art-crest block w-24 sm:w-28" aria-hidden="true"></span>

            <h1 class="mt-5 text-center font-display text-[21px] font-bold tracking-[0.05em] whitespace-nowrap sm:text-[28px] sm:tracking-[0.08em]">HOMEBRIDGE MUN ’26</h1>
            <p class="mt-2 text-center font-script text-xl text-[#e6e1ec] italic">{{ __('Different voices. One purpose.') }}</p>
            <p class="mt-3 text-center font-display text-[12px] tracking-[0.24em] text-hb-lavender uppercase">{{ __('Virtual') }} · {{ config('conference.dates_label') }}</p>

            <nav class="mt-8 flex w-full flex-col gap-3" aria-label="{{ __('Links') }}">
                @foreach ($links as $link)
                    <a
                        href="{{ $link['href'] }}"
                        @if ($link['external'] ?? false) target="_blank" rel="noopener" @endif
                        @class([
                            'flex min-h-14 flex-col items-center justify-center rounded-xs px-5 py-3 text-center transition',
                            'bg-white text-hb-purple hover:bg-hb-lavender' => $link['primary'] ?? false,
                            'border border-white/25 bg-black/25 hover:border-hb-lavender hover:bg-hb-purple/60' => ! ($link['primary'] ?? false),
                        ])
                    >
                        <span class="font-semibold">{{ $link['label'] }}</span>
                        @isset($link['note'])
                            <span @class(['mt-0.5 text-sm', 'text-hb-purple/75' => $link['primary'] ?? false, 'text-hb-soft/80' => ! ($link['primary'] ?? false)])>{{ $link['note'] }}</span>
                        @endisset
                    </a>
                @endforeach
            </nav>

            @if ($socials)
                <div class="mt-8 flex flex-wrap justify-center gap-x-6 gap-y-3 text-sm">
                    @foreach ($socials as $key => $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener" class="text-hb-soft underline-offset-4 hover:text-white hover:underline">{{ $socialNames[$key] }}</a>
                    @endforeach
                </div>
            @endif

            <p class="mt-auto pt-12 text-center font-display text-[11px] tracking-[0.2em] text-hb-lavender/70">DIPLOMACY · LEADERSHIP · DISCUSSION · IMPACT</p>
        </main>
        </div>
    </body>
</html>
