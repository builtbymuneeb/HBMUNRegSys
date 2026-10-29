@php
    $contacts = config('conference.contacts');

    $links = [
        ['label' => __('Register your delegation'), 'note' => __('Registrations are open'), 'href' => route('register'), 'primary' => true],
        ['label' => __('Check your registration status'), 'href' => route('registration.status')],
        ['label' => __('Committees'), 'href' => route('home').'#committees'],
        ['label' => __('Fees and payment'), 'href' => route('home').'#fees'],
        ['label' => __('Official website'), 'href' => route('home')],
    ];

    foreach ($contacts['heads'] as $i => $head) {
        $links[] = [
            'label' => __('WhatsApp the registration desk').(count($contacts['heads']) > 1 ? ' '.($i + 1) : ''),
            'note' => $head['phone'],
            'href' => 'https://wa.me/'.preg_replace('/^0/', '92', preg_replace('/\D/', '', $head['phone'])),
        ];
    }

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
    <body class="min-h-screen bg-hb-ink font-sans text-white antialiased">
        <main class="relative mx-auto flex min-h-screen w-full max-w-md flex-col items-center px-4 pt-12 pb-10 sm:pt-16">
            <div class="pointer-events-none absolute inset-x-0 top-0 -z-0 h-72 bg-[radial-gradient(ellipse_at_top,rgba(59,10,107,0.55),transparent_70%)]" aria-hidden="true"></div>

            <span class="hb-art hb-art-crest relative block w-24 sm:w-28" aria-hidden="true"></span>

            <h1 class="relative mt-5 text-center font-display text-2xl font-bold tracking-[0.08em] sm:text-[28px]">HOMEBRIDGE MUN ’26</h1>
            <p class="relative mt-2 text-center font-script text-xl text-[#e6e1ec] italic">{{ __('Different voices. One purpose.') }}</p>
            <p class="relative mt-3 text-center font-display text-[12px] tracking-[0.24em] text-hb-lavender uppercase">{{ __('Virtual') }} · {{ config('conference.dates_label') }}</p>

            <nav class="relative mt-8 flex w-full flex-col gap-3" aria-label="{{ __('Links') }}">
                @foreach ($links as $link)
                    <a
                        href="{{ $link['href'] }}"
                        @if (str_starts_with($link['href'], 'https://wa.me')) target="_blank" rel="noopener" @endif
                        @class([
                            'flex min-h-14 flex-col items-center justify-center rounded-xs px-5 py-3 text-center transition',
                            'bg-white text-hb-purple hover:bg-hb-lavender' => $link['primary'] ?? false,
                            'border border-hb-line-strong bg-white/[0.03] hover:border-hb-lavender hover:bg-hb-purple/40' => ! ($link['primary'] ?? false),
                        ])
                    >
                        <span class="font-semibold">{{ $link['label'] }}</span>
                        @isset($link['note'])
                            <span @class(['mt-0.5 text-sm', 'text-hb-purple/75' => $link['primary'] ?? false, 'text-hb-subtle' => ! ($link['primary'] ?? false)])>{{ $link['note'] }}</span>
                        @endisset
                    </a>
                @endforeach
            </nav>

            @if ($socials)
                <div class="relative mt-8 flex flex-wrap justify-center gap-x-6 gap-y-3 text-sm">
                    @foreach ($socials as $key => $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener" class="text-hb-soft underline-offset-4 hover:text-white hover:underline">{{ $socialNames[$key] }}</a>
                    @endforeach
                </div>
            @endif

            <p class="relative mt-auto pt-12 text-center font-display text-[11px] tracking-[0.2em] text-[#8f8996]">DIPLOMACY · LEADERSHIP · DISCUSSION · IMPACT</p>
        </main>
    </body>
</html>
