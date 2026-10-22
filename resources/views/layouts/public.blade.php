<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />

        <title>{{ filled($title ?? null) ? $title.' · '.config('conference.name') : config('conference.name') }}</title>

        @include('partials.icons')

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-hb-ink font-sans text-white antialiased">
        <header class="border-b border-hb-line bg-black/85">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-6 py-4">
                <a href="{{ route('home') }}" class="flex items-center gap-3">
                    <span class="hb-art hb-art-crest block w-10" aria-hidden="true"></span>
                    <span class="font-display text-lg font-bold tracking-[0.12em]">HOMEBRIDGE MUN ’26</span>
                </a>

                @if (isset($nav))
                    {{ $nav }}
                @else
                    <a href="{{ route('registration.status') }}" class="text-sm text-hb-soft underline-offset-4 hover:text-white hover:underline">
                        {{ __('Already registered? Check your status') }}
                    </a>
                @endif
            </div>
        </header>

        <main>
            {{ $slot }}
        </main>

        <footer class="border-t border-hb-line bg-black">
            <div class="mx-auto flex max-w-6xl flex-col gap-8 px-6 pt-14 pb-10">
                <div class="flex flex-wrap justify-between gap-8">
                    <div class="flex items-center gap-3.5">
                        <span class="hb-art hb-art-crest block w-14" aria-hidden="true"></span>
                        <div>
                            <div class="font-display text-lg font-bold tracking-[0.12em]">HOMEBRIDGE MUN ’26</div>
                            <div class="mt-1 text-sm text-hb-subtle">{{ __('Virtual') }} · {{ config('conference.dates_label') }}</div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-6 text-sm">
                        <a href="{{ route('home') }}#committees" class="text-hb-soft hover:text-white">{{ __('Committees') }}</a>
                        <a href="{{ route('home') }}#fees" class="text-hb-soft hover:text-white">{{ __('Fees') }}</a>
                        <a href="{{ route('registration.status') }}" class="text-hb-soft hover:text-white">{{ __('Registration status') }}</a>
                        @if (config('conference.contacts.email'))
                            <a href="mailto:{{ config('conference.contacts.email') }}" class="text-hb-soft hover:text-white">{{ config('conference.contacts.email') }}</a>
                        @endif
                    </div>
                </div>

                <div class="flex flex-wrap justify-between gap-4 border-t border-[#2a2630] pt-6 font-display text-xs tracking-[0.22em] text-[#8f8996]">
                    <span>DIPLOMACY · LEADERSHIP · DISCUSSION · IMPACT</span>
                    <span>© {{ date('Y') }} HOMEBRIDGE MUN</span>
                </div>
            </div>
        </footer>
    </body>
</html>
