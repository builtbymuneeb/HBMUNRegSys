@php
    $delegate = $user->delegate?->load(['registration.delegates.allocatedCommittee', 'allocatedCommittee.sessions', 'firstCommittee', 'secondCommittee']);
    $registration = $delegate?->registration;
    $isVerified = $registration?->status === \App\Enums\RegistrationStatus::Verified;
    $committee = $delegate?->hasPublishedAllocation() ? $delegate->allocatedCommittee : null;
    $guides = $isVerified ? \App\Models\StudyGuide::visibleToCommittee($committee?->id)->with('committee')->latest()->get() : collect();
    $announcements = $isVerified ? \App\Models\Announcement::visibleToCommittee($committee?->id)->with('committee')->latest()->limit(20)->get() : collect();
    $startsAt = \Illuminate\Support\Carbon::parse(config('conference.starts_at'), 'Asia/Karachi');
    $fresh ??= ['allocation' => false, 'announcements' => [], 'guides' => [], 'sessions' => []];
@endphp

@if (! $isVerified)
    <flux:callout icon="sparkles" :heading="__('Your dashboard is on its way')"
        :text="__('Your committee, guides and session links appear here once your payment is verified.')" />
@else
    @php($sessions = $committee?->sessions ?? collect())
    @php($next = $sessions->first(fn ($session) => $session->isUpcoming()))
    <div class="grid gap-4 lg:gap-6 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
        <div class="flex flex-col gap-4 lg:gap-6">
            {{-- Allocation --}}
            <div @class(['hb-hero relative overflow-hidden rounded-[20px] p-4 text-white lg:p-6', 'ring-2 ring-white/60' => $fresh['allocation']])>
                <div class="pointer-events-none absolute -top-12 -right-12 size-44 rounded-full bg-[radial-gradient(circle,rgb(255_255_255/0.18),transparent_70%)]"></div>
                <div class="relative flex items-start justify-between gap-3">
                    <div class="text-[10.5px] font-semibold tracking-[0.16em] text-white/75 uppercase">{{ __('Your allocation') }}</div>
                    @if ($fresh['allocation'])
                        <span class="rounded-full bg-white/20 px-2 py-0.5 text-[11px] font-bold">{{ __('New') }}</span>
                    @endif
                </div>
                @if ($committee)
                    <div class="relative mt-1 font-display text-[26px] leading-tight font-semibold lg:text-3xl">{{ $delegate->allocated_portfolio }}</div>
                    <div class="relative mt-3 flex flex-wrap gap-1.5 text-xs font-semibold">
                        <span class="rounded-full border border-white/25 bg-white/15 px-2.5 py-1">{{ $committee->label() }}</span>
                        @if ($next)
                            <span class="rounded-full border border-white/25 bg-white/15 px-2.5 py-1">{{ $next->starts_at->timezone('Asia/Karachi')->format('D j M · g:i a') }}</span>
                        @endif
                    </div>
                    @if ($committee->agenda)
                        <p class="relative mt-3 text-sm whitespace-pre-line text-white/85">{{ $committee->agenda }}</p>
                    @endif
                @else
                    <div class="relative mt-1 font-display text-[22px] leading-tight font-semibold">{{ __('Allocation pending') }}</div>
                    <div class="relative mt-3 flex flex-wrap gap-1.5 text-xs font-semibold">
                        @foreach ([$delegate->firstCommittee, $delegate->secondCommittee] as $choice)
                            <span class="rounded-full border border-white/25 bg-white/15 px-2.5 py-1">{{ $loop->iteration }}. {{ $choice->code }}@if ($pref = $delegate->preferenceFor($choice)) · {{ $pref }}@endif</span>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Countdown --}}
            <div x-data="{ s: Math.max(0, Math.floor(({{ $startsAt->getTimestampMs() }} - Date.now()) / 1000)) }" x-init="setInterval(() => s = Math.max(0, s - 1), 1000)">
                <div x-show="s > 0" class="grid grid-cols-4 gap-2">
                    @foreach (['days' => 'Math.floor(s / 86400)', 'hrs' => 'Math.floor(s % 86400 / 3600)', 'min' => 'Math.floor(s % 3600 / 60)', 'sec' => 's % 60'] as $unit => $expression)
                        <div class="rounded-2xl border border-zinc-200 bg-white py-2 text-center dark:border-white/8 dark:bg-zinc-900/70">
                            <div class="font-display text-xl font-semibold text-zinc-900 dark:text-white" x-text="String({{ $expression }}).padStart(2, '0')"></div>
                            <div class="text-[10px] font-semibold tracking-wider text-zinc-500 uppercase dark:text-zinc-400">{{ __($unit) }}</div>
                        </div>
                    @endforeach
                </div>
                <div x-show="s === 0" x-cloak class="rounded-2xl border border-zinc-200 bg-white p-3 text-center font-semibold text-zinc-900 dark:border-white/8 dark:bg-zinc-900/70 dark:text-white">{{ __('The conference is live.') }}</div>
            </div>

            {{-- Shortcuts --}}
            <div class="grid grid-cols-4 gap-2 xl:hidden">
                @foreach ([
                    ['#sessions', 'video-camera', __('Sessions')],
                    ['#guides', 'book-open', __('Guides')],
                    ['#updates', 'megaphone', __('Updates')],
                    $committee ? ['#card', 'identification', __('Card')] : ['#delegation', 'user-group', __('Team')],
                ] as [$href, $icon, $label])
                    <a href="{{ $href }}" class="flex flex-col items-center gap-1.5 text-[11.5px] font-medium text-zinc-700 dark:text-zinc-200">
                        <span class="grid h-14 w-full place-items-center rounded-2xl border border-zinc-200 bg-white text-hb-lavender dark:border-white/8 dark:bg-zinc-900/70"><flux:icon :name="$icon" class="size-6" /></span>
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            {{-- Sessions --}}
            <section id="sessions" class="flex scroll-mt-20 flex-col gap-2">
                <h2 class="px-1 text-[15px] font-semibold text-zinc-900 dark:text-white">{{ __('Sessions') }}</h2>
                <div class="hb-list">
                    @forelse ($sessions as $session)
                        <div @class(['hb-row', 'opacity-60' => ! $session->isUpcoming(), 'hb-fresh !mx-0 !rounded-none' => in_array($session->id, $fresh['sessions'])])>
                            <div class="min-w-0 flex-1">
                                <div class="truncate font-semibold text-zinc-900 dark:text-white">
                                    {{ $session->title }}
                                    @if (in_array($session->id, $fresh['sessions']))
                                        <flux:badge size="sm" color="lime" class="ms-1">{{ __('Updated') }}</flux:badge>
                                    @endif
                                </div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $session->timeLabel() }}</div>
                            </div>
                            @if ($session->meeting_url && $session->isUpcoming())
                                <flux:button size="sm" :variant="$next?->is($session) ? 'primary' : 'filled'" icon="video-camera" :href="$session->meeting_url" target="_blank">{{ __('Join') }}</flux:button>
                            @elseif ($session->isUpcoming())
                                <span class="text-xs text-zinc-500">{{ __('Link soon') }}</span>
                            @else
                                <span class="text-xs text-zinc-500">{{ __('Ended') }}</span>
                            @endif
                        </div>
                    @empty
                        <div class="hb-row text-sm text-zinc-500 dark:text-zinc-400">{{ $committee ? __('Times and links coming soon.') : __('Shown once you’re allocated.') }}</div>
                    @endforelse
                </div>
            </section>

            {{-- Study guides --}}
            <section id="guides" class="flex scroll-mt-20 flex-col gap-2">
                <h2 class="px-1 text-[15px] font-semibold text-zinc-900 dark:text-white">{{ __('Study guides') }}</h2>
                <div class="hb-list">
                    @forelse ($guides as $guide)
                        <a href="{{ route('study-guides.download', $guide) }}" @class(['hb-row hover:bg-zinc-50 dark:hover:bg-white/4', 'hb-fresh !mx-0 !rounded-none' => in_array($guide->id, $fresh['guides'])])>
                            <span class="hb-icon-tile"><flux:icon name="book-open" class="size-5" /></span>
                            <div class="min-w-0 flex-1">
                                <div class="truncate font-semibold text-zinc-900 dark:text-white">
                                    {{ $guide->title }}
                                    @if (in_array($guide->id, $fresh['guides']))
                                        <flux:badge size="sm" color="purple" class="ms-1">{{ __('New') }}</flux:badge>
                                    @endif
                                </div>
                                @if ($guide->description)
                                    <div class="line-clamp-2 text-xs text-zinc-500 dark:text-zinc-400">{{ $guide->description }}</div>
                                @endif
                            </div>
                            <span class="text-xs text-zinc-500">{{ $guide->humanFileSize() }}</span>
                            <flux:icon.arrow-down-tray variant="micro" class="text-zinc-400" />
                        </a>
                    @empty
                        <div class="hb-row text-sm text-zinc-500 dark:text-zinc-400">{{ __('Coming soon.') }}</div>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="flex flex-col gap-4 lg:gap-6">
            {{-- Delegate card --}}
            @if ($committee)
                <section id="card" class="flex scroll-mt-20 flex-col gap-2">
                    <h2 class="px-1 text-[15px] font-semibold text-zinc-900 dark:text-white">{{ __('Delegate card') }}</h2>
                    <div class="hb-list flex flex-col items-start gap-3 p-3">
                    <div class="overflow-x-auto">
                        <div id="delegate-card" class="relative w-full max-w-[360px] overflow-hidden rounded-md border border-[#5b2a8c] bg-[#120a1c] p-6 text-white" style="background-image: radial-gradient(circle at 85% 0%, #3b0a6b 0%, transparent 60%);">
                            <div class="flex items-center gap-3">
                                <x-app-logo-icon class="h-14" />
                                <div>
                                    <div class="font-display text-[15px] font-bold tracking-[0.12em]">HOMEBRIDGE MUN ’26</div>
                                    <div class="font-display text-[10px] tracking-[0.3em] text-[#c9b6ea] uppercase">{{ config('conference.dates_label') }}</div>
                                </div>
                            </div>
                            <div class="mt-6 font-display text-[10px] tracking-[0.35em] text-[#c9b6ea] uppercase">{{ __('Delegate') }}</div>
                            <div class="mt-1 font-display text-2xl leading-tight font-semibold">{{ $delegate->name }}</div>
                            <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <div class="text-[10px] tracking-[0.2em] text-[#a79bb5] uppercase">{{ __('Committee') }}</div>
                                    <div class="font-semibold">{{ $committee->code }}</div>
                                </div>
                                <div>
                                    <div class="text-[10px] tracking-[0.2em] text-[#a79bb5] uppercase">{{ $committee->portfolioLabel() }}</div>
                                    <div class="font-semibold">{{ $delegate->allocated_portfolio }}</div>
                                </div>
                                <div class="col-span-2">
                                    <div class="text-[10px] tracking-[0.2em] text-[#a79bb5] uppercase">{{ __('Delegation') }}</div>
                                    <div class="font-semibold">{{ $registration->displayName() }}</div>
                                </div>
                            </div>
                            <div class="mt-5 flex items-center justify-between border-t border-[#3a2a4d] pt-3">
                                <span class="font-script text-base text-[#c9b6ea] italic">{{ __('Different voices. One purpose.') }}</span>
                                <span class="text-[10px] tracking-[0.2em] text-[#a79bb5]">{{ $registration->reference }}</span>
                            </div>
                        </div>
                    </div>
                    <div x-data="{ saving: false }">
                        <flux:button icon="arrow-down-tray" x-bind:disabled="saving"
                            x-on:click="saving = true; window.hbDownloadCard(document.getElementById('delegate-card'), @js('hbmun-delegate-card-'.\Illuminate\Support\Str::slug($delegate->name).'.png')).finally(() => saving = false)">
                            {{ __('Download card') }}
                        </flux:button>
                    </div>
                    </div>
                </section>
            @endif

            {{-- Announcements --}}
            <section id="updates" class="flex scroll-mt-20 flex-col gap-2">
                <h2 class="px-1 text-[15px] font-semibold text-zinc-900 dark:text-white">{{ __('Updates') }}</h2>
                <div class="hb-list">
                    @forelse ($announcements as $announcement)
                        <div @class(['hb-row !items-start', 'hb-fresh !mx-0 !rounded-none' => in_array($announcement->id, $fresh['announcements'])])>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="font-semibold text-zinc-900 dark:text-white">
                                        {{ $announcement->title }}
                                        @if (in_array($announcement->id, $fresh['announcements']))
                                            <flux:badge size="sm" color="purple" class="ms-1">{{ __('New') }}</flux:badge>
                                        @endif
                                    </div>
                                    <span class="shrink-0 text-[11px] text-zinc-500">{{ $announcement->created_at->timezone('Asia/Karachi')->format('j M') }}</span>
                                </div>
                                <p class="mt-0.5 text-sm whitespace-pre-line text-zinc-600 dark:text-zinc-300">{{ $announcement->body }}</p>
                            </div>
                        </div>
                    @empty
                        <div class="hb-row text-sm text-zinc-500 dark:text-zinc-400">{{ __('Nothing yet.') }}</div>
                    @endforelse
                </div>
            </section>

            {{-- Delegation --}}
            <section id="delegation" class="flex scroll-mt-20 flex-col gap-2">
                <h2 class="px-1 text-[15px] font-semibold text-zinc-900 dark:text-white">{{ $registration->displayName() }}</h2>
                <div class="hb-list">
                    @foreach ($registration->delegates as $member)
                        <div class="hb-row text-sm">
                            <span class="flex-1 truncate font-medium text-zinc-900 dark:text-white">{{ $member->name }}@if ($member->is($delegate)) <span class="font-normal text-zinc-500">({{ __('you') }})</span>@endif</span>
                            <span class="text-zinc-500">{{ $member->hasPublishedAllocation() ? $member->allocatedCommittee->code.' · '.$member->allocated_portfolio : __('Pending') }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
@endif
