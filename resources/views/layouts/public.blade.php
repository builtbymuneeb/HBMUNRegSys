<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />

        <title>{{ filled($title ?? null) ? $title.' · '.config('conference.name') : config('conference.name') }}</title>

        @include('partials.icons')

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @include('partials.clarity')
    </head>
    <body class="min-h-screen bg-hb-ink font-sans text-white antialiased">
        <header class="border-b border-hb-line bg-black/85">
            <div class="relative mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6 sm:py-4">
                <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-2.5 sm:gap-3">
                    <span class="hb-art hb-art-crest block w-8 shrink-0 sm:w-10" aria-hidden="true"></span>
                    <span class="font-display text-[13px] leading-tight font-bold tracking-[0.04em] min-[400px]:text-[15px] sm:text-lg sm:tracking-[0.12em]">HOMEBRIDGE MUN ’26</span>
                </a>

                @if (isset($nav))
                    {{ $nav }}
                @else
                    <a href="{{ route('registration.status') }}" class="shrink-0 text-sm text-hb-soft underline-offset-4 hover:text-white hover:underline">
                        <span class="sm:hidden">{{ __('Check status') }}</span>
                        <span class="hidden sm:inline">{{ __('Already registered? Check your status') }}</span>
                    </a>
                @endif
            </div>
        </header>

        <main>
            {{ $slot }}
        </main>

        <footer class="border-t border-hb-line bg-black">
            <div class="mx-auto flex max-w-6xl flex-col gap-6 px-6 pt-10 pb-8 lg:gap-8 lg:pt-14 lg:pb-10">
                <div class="flex flex-wrap justify-between gap-6 lg:gap-8">
                    <div class="flex items-center gap-3.5">
                        <span class="hb-art hb-art-crest block w-11 lg:w-14" aria-hidden="true"></span>
                        <div>
                            <div class="font-display font-bold tracking-[0.12em] lg:text-lg">HOMEBRIDGE MUN ’26</div>
                            <div class="mt-1 text-sm text-hb-subtle">{{ __('Virtual') }} · {{ config('conference.dates_label') }}</div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-x-6 gap-y-3 text-sm">
                        <a href="{{ route('home') }}#committees" class="text-hb-soft hover:text-white">{{ __('Committees') }}</a>
                        <a href="{{ route('home') }}#fees" class="text-hb-soft hover:text-white">{{ __('Fees') }}</a>
                        <a href="{{ route('registration.status') }}" class="text-hb-soft hover:text-white">{{ __('Registration status') }}</a>
                        <button type="button" onclick="document.getElementById('ambassador-dialog').showModal()" class="cursor-pointer text-hb-soft hover:text-white">{{ __('Campus Ambassadors') }}</button>
                        @if (config('conference.contacts.email'))
                            <a href="mailto:{{ config('conference.contacts.email') }}" class="text-hb-soft hover:text-white">{{ config('conference.contacts.email') }}</a>
                        @endif
                    </div>
                </div>

                <div class="flex flex-wrap justify-between gap-x-4 gap-y-2 border-t border-[#2a2630] pt-6 font-display text-[11px] tracking-[0.18em] text-[#8f8996] sm:text-xs sm:tracking-[0.22em]">
                    <span>DIPLOMACY · LEADERSHIP · DISCUSSION · IMPACT</span>
                    <span>© {{ date('Y') }} HOMEBRIDGE MUN</span>
                </div>
            </div>
        </footer>

        @include('partials.ambassador-dialog')
    </body>
</html>
