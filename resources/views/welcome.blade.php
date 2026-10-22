@php
    $startsAt = \Illuminate\Support\Carbon::parse(config('conference.starts_at'), 'Asia/Karachi');
    $remaining = now()->diff($startsAt);
    $hasStarted = $startsAt->isPast();
    $feePerDelegate = number_format(config('conference.fees.per_delegate'));
    $feePerDelegation = number_format(config('conference.fees.per_delegation'));
    $exampleDelegationTotal = number_format(\App\Enums\RegistrationType::InstitutionDelegation->amountDueFor(4));
    $minDelegates = config('conference.delegation.min_delegates');
    $maxDelegates = config('conference.delegation.max_delegates');
    $committeeArt = ['UNW' => 'hb-art-seal', 'UNSC' => 'hb-art-globe', 'PNA' => 'hb-art-column', 'SPECIAL' => 'hb-art-banner'];
    $tickerItems = [__('Registrations now open'), config('conference.dates_label'), __('Virtual conference'), __('Delegate fee PKR :fee', ['fee' => $feePerDelegate])];
@endphp

<x-layouts::public>
    <x-slot:nav>
        @php($sections = ['#about' => __('About'), '#committees' => __('Committees'), '#fees' => __('Fees'), '#how' => __('How to register'), '#faq' => __('FAQ')])

        {{-- Desktop --}}
        <nav class="hidden items-center gap-7 text-sm lg:flex" aria-label="{{ __('Main') }}">
            @foreach ($sections as $href => $label)
                <a href="{{ $href }}" class="text-hb-soft hover:text-white">{{ $label }}</a>
            @endforeach
        </nav>
        <div class="hidden gap-2.5 lg:flex">
            @auth
                <a href="{{ route('dashboard') }}" class="hb-button-secondary min-h-11 px-5 text-sm">{{ __('Dashboard') }}</a>
            @else
                <a href="{{ route('login') }}" class="hb-button-secondary min-h-11 px-5 text-sm">{{ __('Login') }}</a>
                <a href="{{ route('register') }}" class="hb-button-primary min-h-11 px-5 text-sm">{{ __('Join Now') }}</a>
            @endauth
        </div>

        {{-- Phone and tablet --}}
        <div class="flex shrink-0 items-center gap-2 lg:hidden">
            @guest
                <a href="{{ route('register') }}" class="hb-button-primary min-h-10 px-4 text-sm">{{ __('Join') }}</a>
            @endguest
            <details class="group">
                <summary class="flex size-10 cursor-pointer list-none items-center justify-center rounded-xs border border-[#6e6a75] [&::-webkit-details-marker]:hidden" aria-label="{{ __('Menu') }}">
                    <svg class="size-5 group-open:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" /></svg>
                    <svg class="hidden size-5 group-open:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
                </summary>
                <nav class="absolute inset-x-0 top-full z-20 flex flex-col border-b border-hb-line bg-black px-4 pt-2 pb-5" aria-label="{{ __('Main') }}">
                    @foreach ($sections as $href => $label)
                        <a href="{{ $href }}" onclick="this.closest('details').removeAttribute('open')" class="border-b border-hb-line py-3.5 text-[15px] text-hb-soft hover:text-white">{{ $label }}</a>
                    @endforeach
                    <a href="{{ route('registration.status') }}" class="border-b border-hb-line py-3.5 text-[15px] text-hb-soft hover:text-white">{{ __('Check registration status') }}</a>
                    @auth
                        <a href="{{ route('dashboard') }}" class="hb-button-secondary mt-4">{{ __('Dashboard') }}</a>
                    @else
                        <a href="{{ route('login') }}" class="hb-button-secondary mt-4">{{ __('Login') }}</a>
                    @endauth
                </nav>
            </details>
        </div>
    </x-slot:nav>

    {{-- Ticker --}}
    <div class="overflow-hidden border-b border-[#5a2d8c] bg-hb-purple">
        <div class="hb-marquee flex w-max py-2.5 font-display text-[13px] tracking-[0.22em] whitespace-nowrap uppercase">
            @foreach ([...$tickerItems, ...$tickerItems] as $item)
                <span class="px-7">{{ $item }}</span><span class="text-hb-lavender" aria-hidden="true">✦</span>
            @endforeach
        </div>
    </div>

    {{-- Hero --}}
    <section id="top" class="border-b border-[#2a2630]">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-8 px-6 pt-10 pb-14 lg:gap-16 lg:pt-22 lg:pb-24">
            <div class="flex min-w-0 flex-[1_1_520px] flex-col gap-5 lg:gap-7">
                <div class="flex items-center gap-3.5 font-display text-[12px] tracking-[0.2em] text-hb-lavender uppercase sm:text-[13px] sm:tracking-[0.28em]">
                    <span class="hidden h-px w-10 bg-[#6e6a75] sm:block"></span>
                    {{ __('Online') }} · {{ config('conference.dates_label') }}
                </div>
                <h1 class="font-display text-[clamp(44px,6vw,80px)] leading-[1.02] font-bold tracking-[0.03em]">Homebridge<br>MUN ’26</h1>
                <p class="font-script text-[clamp(26px,3vw,38px)] leading-tight text-[#e6e1ec] italic">{{ __('Different voices. One purpose.') }}</p>
                <p class="max-w-xl text-[17px] leading-relaxed text-hb-muted">
                    {{ __('Two days of diplomacy, debate, negotiation and collaboration across four committees, from the Security Council to the Pakistan National Assembly. Join from anywhere.') }}
                </p>
                <div class="grid gap-3 sm:flex sm:flex-wrap">
                    <a href="{{ route('register') }}" class="hb-button-primary min-h-13 px-8 text-base">
                        {{ __('Join Now') }}
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14" /><path d="m13 6 6 6-6 6" /></svg>
                    </a>
                    <a href="#committees" class="hb-button-secondary min-h-13 text-base">{{ __('Explore committees') }}</a>
                </div>
                @unless ($hasStarted)
                    <dl class="flex flex-wrap gap-2.5 pt-2" aria-label="{{ __('Time until the conference') }}">
                        @foreach ([__('Days') => $remaining->days, __('Hours') => $remaining->h] as $label => $value)
                            <div class="min-w-21 border border-hb-line bg-hb-field px-3.5 py-3 text-center">
                                <dd class="font-display text-3xl font-semibold">{{ str_pad($value, 2, '0', STR_PAD_LEFT) }}</dd>
                                <dt class="mt-0.5 text-[11px] tracking-[0.18em] text-hb-subtle uppercase">{{ $label }}</dt>
                            </div>
                        @endforeach
                    </dl>
                @endunless
            </div>
            <div class="order-first flex min-w-0 basis-full justify-center lg:order-none lg:flex-[0_1_420px]">
                <div class="w-full max-w-[150px] border border-hb-line-strong bg-hb-field p-2 sm:max-w-[220px] lg:max-w-[420px] lg:p-3.5">
                    <div class="hb-art hb-art-crest w-full" role="img" aria-label="{{ __('Homebridge MUN ’26 crest: a bridge over a globe framed by laurels') }}"></div>
                </div>
            </div>
        </div>
    </section>

    {{-- Pillars & about --}}
    <section id="about" class="border-b border-[#2a2630] bg-hb-surface">
        <div class="mx-auto flex max-w-6xl flex-col gap-8 px-6 py-14 lg:gap-12 lg:py-22">
            <div class="flex flex-col items-center gap-3.5 text-center">
                <div class="hb-eyebrow">{{ __('The conference') }}</div>
                <h2 class="font-display text-[clamp(30px,3.6vw,44px)] font-semibold">Ideas · Dialogue · Action</h2>
                <x-public.star-divider />
            </div>
            <div class="grid gap-3 md:grid-cols-3 md:gap-6">
                @foreach ([
                    'Ideas' => __('Prepare with study guides for every committee, published on your delegate dashboard before the conference.'),
                    'Dialogue' => __('Speak, negotiate and caucus in live online sessions, moderated by experienced chairs.'),
                    'Action' => __('Turn debate into resolutions and directives, and compete for awards in every committee.'),
                ] as $pillar => $description)
                    <div class="flex flex-col gap-1.5 bg-hb-parchment px-5 py-5 text-[#1b1622] md:gap-3 md:px-8 md:py-9">
                        <div class="font-script text-[28px] leading-tight font-semibold text-hb-purple italic md:text-[34px]">{{ __($pillar) }}</div>
                        <p class="text-[15px] leading-relaxed text-[#3a3440]">{{ $description }}</p>
                    </div>
                @endforeach
            </div>

            <div class="flex flex-wrap items-center gap-12 lg:pt-6">
                <div class="hb-art hb-art-window hidden min-w-0 flex-[1_1_420px] border border-hb-line-strong md:block" role="img" aria-label="{{ __('Engraved illustration of a gothic window overlooking spires') }}"></div>
                <div class="flex min-w-0 flex-[1_1_420px] flex-col gap-5">
                    <h3 class="font-display text-2xl leading-tight font-semibold sm:text-3xl">{{ __('A bridge between institutes, from your own home.') }}</h3>
                    <p class="leading-relaxed text-hb-muted">{{ __('Homebridge MUN ’26 is fully online. Register, get verified, and your committee, portfolio, study guide and session links all arrive on your personal delegate dashboard.') }}</p>
                    <dl class="grid grid-cols-2 gap-px border border-hb-line bg-hb-line">
                        @foreach ([__('Days of sessions') => '2', __('Committees') => $committees->count(), __('Online') => '100%', __('PKR per delegate') => $feePerDelegate] as $label => $value)
                            <div class="flex flex-col-reverse bg-hb-surface p-4 sm:p-5">
                                <dt class="text-[13px] tracking-[0.06em] text-hb-subtle sm:text-sm">{{ $label }}</dt>
                                <dd class="font-display text-2xl font-semibold sm:text-3xl">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </div>
        </div>
    </section>

    {{-- Committees --}}
    <section id="committees" class="border-b border-[#2a2630]">
        <div class="mx-auto flex max-w-6xl flex-col gap-8 px-6 py-14 lg:gap-12 lg:py-24">
            <div class="flex flex-wrap items-end justify-between gap-4 lg:gap-6">
                <div class="flex flex-col gap-3">
                    <div class="hb-eyebrow">{{ __('The committees') }}</div>
                    <h2 class="font-display text-[clamp(30px,3.6vw,44px)] font-semibold">{{ __('Four rooms. Pick your arena.') }}</h2>
                </div>
                <p class="max-w-md text-[15px] leading-relaxed text-hb-muted">{{ __('Choose two committee preferences when you register. Allocations are emailed and shown on your dashboard after verification.') }}</p>
            </div>
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-5">
                @foreach ($committees as $committee)
                    <article @class([
                        'flex flex-col border',
                        'border-[#5a2d8c] bg-[#160a24]' => $committee->code === 'SPECIAL',
                        'border-hb-line bg-hb-surface' => $committee->code !== 'SPECIAL',
                    ])>
                        <div class="hb-art {{ $committeeArt[$committee->code] ?? 'hb-art-globe' }} w-full" aria-hidden="true"></div>
                        <div class="flex grow flex-col gap-1.5 p-3.5 lg:gap-2.5 lg:p-6">
                            <h3 class="font-display text-lg font-bold tracking-[0.06em] lg:text-2xl">{{ $committee->code === 'SPECIAL' ? __('Special') : $committee->code }}</h3>
                            <div class="text-[13px] leading-snug text-hb-soft lg:text-sm">{{ $committee->name }}</div>
                            <div class="mt-auto pt-1 text-[10px] tracking-[0.1em] text-hb-lavender uppercase lg:pt-2 lg:text-xs lg:tracking-[0.12em]">
                                {{ __('Portfolio') }} · {{ $committee->portfolio_type === \App\Enums\PortfolioType::Country ? __('Countries') : __('Characters') }}
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Fees --}}
    <section id="fees" class="border-b border-[#2a2630] bg-hb-surface">
        <div class="mx-auto flex max-w-6xl flex-col gap-8 px-6 py-14 lg:gap-12 lg:py-24">
            <div class="flex flex-col items-center gap-3 text-center">
                <div class="hb-eyebrow">{{ __('Registration fees') }}</div>
                <h2 class="font-display text-[clamp(30px,3.6vw,44px)] font-semibold">{{ __('Come alone, or bring your team.') }}</h2>
            </div>
            <div class="mx-auto grid w-full max-w-4xl gap-4 md:grid-cols-2 md:gap-6">
                <div class="flex flex-col gap-3.5 border border-hb-line-strong px-6 py-7 md:gap-4.5 md:px-9 md:py-10">
                    <div class="font-display tracking-[0.14em] uppercase md:text-lg">{{ __('Private delegate') }}</div>
                    <div class="flex items-baseline gap-2"><span class="text-hb-subtle">PKR</span><span class="font-display text-[44px] leading-none font-bold md:text-[56px]">{{ $feePerDelegate }}</span></div>
                    <p class="leading-relaxed text-hb-muted">{{ __('Register on your own. One delegate, one seat, with your committee and portfolio preferences.') }}</p>
                    <a href="{{ route('register', ['type' => 'private_delegate']) }}" class="hb-button-secondary mt-auto border-white">{{ __('Register as a delegate') }}</a>
                </div>
                <div class="flex flex-col gap-3.5 border border-hb-purple-line bg-hb-purple px-6 py-7 md:gap-4.5 md:px-9 md:py-10">
                    <div class="flex items-center justify-between gap-3">
                        <div class="font-display tracking-[0.14em] uppercase md:text-lg">{{ __('Delegation') }}</div>
                        <div class="bg-white px-2.5 py-1 text-[11px] font-semibold tracking-[0.14em] whitespace-nowrap text-hb-purple uppercase">{{ __(':min–:max members', ['min' => $minDelegates, 'max' => $maxDelegates]) }}</div>
                    </div>
                    <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1"><span class="text-[#dccff0]">PKR</span><span class="font-display text-[44px] leading-none font-bold md:text-[56px]">{{ $feePerDelegate }}</span><span class="text-[#dccff0]">{{ __('/ delegate + :fee', ['fee' => $feePerDelegation]) }}</span></div>
                    <p class="leading-relaxed text-[#e6ddf3]">{!! __('Institution or private delegations, registered together by the head. A delegation of 4 pays :total.', ['total' => '<strong class="text-white">PKR '.$exampleDelegationTotal.'</strong>']) !!}</p>
                    <p class="text-sm leading-relaxed text-[#dccff0]">{{ __('Want to discuss a fee adjustment for your institute? Contact the registration team.') }}</p>
                    <a href="{{ route('register') }}" class="hb-button-primary mt-auto">{{ __('Register a delegation') }}</a>
                </div>
            </div>
        </div>
    </section>

    {{-- How to register --}}
    <section id="how" class="border-b border-[#2a2630]">
        <div class="mx-auto flex max-w-6xl flex-wrap items-start gap-6 px-6 py-14 lg:gap-14 lg:py-24">
            <div class="flex min-w-0 flex-[1_1_320px] flex-col gap-5">
                <div class="hb-eyebrow">{{ __('How to register') }}</div>
                <h2 class="font-display text-[clamp(30px,3.6vw,44px)] leading-tight font-semibold">{{ __('Four steps to your seat.') }}</h2>
                <div class="hb-art hb-art-books hidden w-full max-w-sm border border-hb-line-strong lg:block" role="img" aria-label="{{ __('Stacked books: Diplomacy, Politics, International Relations, Global Affairs') }}"></div>
            </div>
            <ol class="flex min-w-0 flex-[2_1_560px] flex-col">
                @foreach ([
                    'I' => [__('Fill in the form'), __('Register as an institution delegation, a private delegate or a private delegation, with two committee preferences per delegate.')],
                    'II' => [__('Send the fee'), __('Transfer the amount shown on the form to our account.')],
                    'III' => [__('Upload your payment screenshot'), __('You’ll get a reference number to track your registration status.')],
                    'IV' => [__('Get verified, set your password'), __('Once our team confirms your payment, each delegate receives an email to activate their dashboard.')],
                ] as $numeral => [$step, $description])
                    <li class="flex gap-4 border-t border-hb-line py-5 last:border-b lg:gap-6 lg:py-7">
                        <div class="w-10 shrink-0 font-display text-2xl text-hb-lilac lg:w-16 lg:text-4xl" aria-hidden="true">{{ $numeral }}</div>
                        <div class="flex flex-col gap-1">
                            <div class="font-semibold lg:text-lg">{{ $step }}</div>
                            <p class="leading-relaxed text-hb-muted">{{ $description }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- FAQ --}}
    <section id="faq" class="border-b border-[#2a2630] bg-hb-surface">
        <div class="mx-auto flex max-w-3xl flex-col gap-6 px-6 py-14 lg:gap-9 lg:py-24">
            <div class="flex flex-col items-center gap-3 text-center">
                <div class="hb-eyebrow">{{ __('Questions') }}</div>
                <h2 class="font-display text-[clamp(30px,3.6vw,44px)] font-semibold">{{ __('Before you ask.') }}</h2>
            </div>
            <div class="flex flex-col border-t border-hb-line">
                @foreach ([
                    __('Is the conference fully online?') => __('Yes. Session links appear on your delegate dashboard on both conference days.'),
                    __('Can I register my whole team?') => __('Yes, as an institution or private delegation of :min–:max delegates. The head fills one form for everyone and pays PKR :delegate per delegate plus PKR :delegation per delegation.', ['min' => $minDelegates, 'max' => $maxDelegates, 'delegate' => $feePerDelegate, 'delegation' => $feePerDelegation]),
                    __('When will I know my committee and portfolio?') => __('After your payment is verified, our team allocates seats. You’ll get an email and see it on your dashboard.'),
                    __('What if my payment screenshot is rejected?') => __('You’ll see the reason on the status page and can upload a new screenshot there.'),
                ] as $question => $answer)
                    <details class="group border-b border-hb-line px-1 py-4 lg:py-5.5" @if ($loop->first) open @endif>
                        <summary class="flex cursor-pointer list-none justify-between gap-4 font-medium lg:text-lg [&::-webkit-details-marker]:hidden">
                            {{ $question }}
                            <span class="text-hb-lavender group-open:rotate-45 transition" aria-hidden="true">+</span>
                        </summary>
                        <p class="mt-3.5 leading-relaxed text-hb-muted">{{ $answer }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Closing call to action --}}
    <section class="bg-hb-purple">
        <div class="mx-auto flex max-w-4xl flex-col items-center gap-5 px-6 py-16 text-center lg:gap-7 lg:py-24">
            <svg class="size-5.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 0 L14 10 L24 12 L14 14 L12 24 L10 14 L0 12 L10 10 Z" fill="#fff" /></svg>
            <p class="font-script text-[clamp(36px,5vw,60px)] leading-[1.1] italic">{{ __('Better dialogue.') }}<br>{{ __('Bigger perspectives.') }}</p>
            <p class="text-[17px] text-[#e6ddf3]">{{ __('Seats are allocated after verification. Register early for your preferred portfolio.') }}</p>
            <a href="{{ route('register') }}" class="hb-button-primary min-h-13 px-9 text-base">{{ __('Join Now') }}</a>
        </div>
    </section>
</x-layouts::public>
