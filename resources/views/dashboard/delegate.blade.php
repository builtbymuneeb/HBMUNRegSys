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
    <flux:callout icon="sparkles" :heading="__('Your delegate dashboard is on its way')"
        :text="__('Your committee, portfolio, study guides and session links will appear here once your registration is verified and allocations are made.')" />
@else
    <div class="grid gap-6 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
        <div class="flex flex-col gap-6">
            {{-- Allocation --}}
            <flux:card @class(['flex flex-col gap-4', 'ring-2 ring-[#a98bd6]/60' => $fresh['allocation']])>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="hb-kicker">{{ __('Your allocation') }}</div>
                        @if ($committee)
                            <flux:heading size="xl" class="mt-1">{{ $delegate->allocated_portfolio }}</flux:heading>
                            <flux:text class="mt-1">{{ $committee->label() }}</flux:text>
                        @else
                            <flux:heading size="lg" class="mt-1">{{ __('Allocation pending') }}</flux:heading>
                            <flux:text class="mt-1">
                                {{ __('Your choices: :first (:firstPref), then :second (:secondPref). You’ll get an email when your allocation is ready.', [
                                    'first' => $delegate->firstCommittee->code,
                                    'firstPref' => $delegate->preferenceFor($delegate->firstCommittee) ?: __('no preference'),
                                    'second' => $delegate->secondCommittee->code,
                                    'secondPref' => $delegate->preferenceFor($delegate->secondCommittee) ?: __('no preference'),
                                ]) }}
                            </flux:text>
                        @endif
                    </div>
                    <div class="flex items-center gap-2">
                        @if ($fresh['allocation'])
                            <flux:badge size="sm" color="purple">{{ __('New') }}</flux:badge>
                        @endif
                        <flux:badge :color="$committee ? 'green' : 'amber'">{{ $committee ? __('Allocated') : __('Pending') }}</flux:badge>
                    </div>
                </div>

                @if ($committee?->agenda)
                    <div>
                        <div class="hb-kicker">{{ __('Agenda') }}</div>
                        <flux:text class="whitespace-pre-line">{{ $committee->agenda }}</flux:text>
                    </div>
                @endif
            </flux:card>

            {{-- Session --}}
            <flux:card class="flex flex-col gap-3">
                <div class="hb-kicker">{{ __('Your sessions') }}</div>
                <div x-data="{ s: Math.max(0, Math.floor(({{ $startsAt->getTimestampMs() }} - Date.now()) / 1000)) }" x-init="setInterval(() => s = Math.max(0, s - 1), 1000)">
                    <template x-if="s > 0">
                        <flux:heading size="lg">
                            <span x-text="Math.floor(s / 86400)"></span> {{ __('days') }},
                            <span x-text="Math.floor(s % 86400 / 3600)"></span> {{ __('hours') }},
                            <span x-text="Math.floor(s % 3600 / 60)"></span> {{ __('minutes to go') }}
                        </flux:heading>
                    </template>
                    <template x-if="s === 0">
                        <flux:heading size="lg">{{ __('The conference is live.') }}</flux:heading>
                    </template>
                    <flux:text class="mt-1">{{ config('conference.dates_label') }} · {{ __('Online') }}</flux:text>
                </div>

                @if ($committee)
                    @php($sessions = $committee->sessions)
                    @php($next = $sessions->first(fn ($session) => $session->isUpcoming()))
                    @forelse ($sessions as $session)
                        <div @class([
                            'flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 pt-3 dark:border-zinc-700',
                            'opacity-60' => ! $session->isUpcoming(),
                            'hb-fresh' => in_array($session->id, $fresh['sessions']),
                        ])>
                            <div>
                                <div class="font-medium text-zinc-800 dark:text-white">
                                    {{ $session->title }}
                                    @if ($next?->is($session))
                                        <flux:badge size="sm" color="purple" class="ms-1">{{ __('Next') }}</flux:badge>
                                    @endif
                                    @if (in_array($session->id, $fresh['sessions']))
                                        <flux:badge size="sm" color="lime" class="ms-1">{{ __('Updated') }}</flux:badge>
                                    @endif
                                </div>
                                <flux:text class="text-sm">{{ $session->timeLabel() }}</flux:text>
                            </div>
                            @if ($session->meeting_url && $session->isUpcoming())
                                <flux:button size="sm" :variant="$next?->is($session) ? 'primary' : 'filled'" icon="video-camera" :href="$session->meeting_url" target="_blank">{{ __('Join') }}</flux:button>
                            @elseif ($session->isUpcoming())
                                <flux:text class="text-xs">{{ __('Link coming soon') }}</flux:text>
                            @else
                                <flux:text class="text-xs">{{ __('Ended') }}</flux:text>
                            @endif
                        </div>
                    @empty
                        <flux:text class="text-sm">{{ __('Your session times and meeting links will appear here before the conference.') }}</flux:text>
                    @endforelse
                @else
                    <flux:text class="text-sm">{{ __('Your session times and meeting links will appear here once you’re allocated.') }}</flux:text>
                @endif
            </flux:card>

            {{-- Study guides --}}
            <flux:card class="flex flex-col gap-3">
                <div class="hb-kicker">{{ __('Study guides') }}</div>
                @forelse ($guides as $guide)
                    <div @class([
                        'flex items-center justify-between gap-4 border-t border-zinc-200 pt-3 first-of-type:border-0 first-of-type:pt-0 dark:border-zinc-700',
                        'hb-fresh' => in_array($guide->id, $fresh['guides']),
                    ])>
                        <div class="min-w-0">
                            <div class="font-medium text-zinc-800 dark:text-white">
                                {{ $guide->title }}
                                @if (in_array($guide->id, $fresh['guides']))
                                    <flux:badge size="sm" color="purple" class="ms-1">{{ __('New') }}</flux:badge>
                                @endif
                            </div>
                            <flux:text class="text-xs">{{ $guide->committee?->code ?? __('All committees') }} · {{ $guide->humanFileSize() }}</flux:text>
                            @if ($guide->description)
                                <flux:text class="mt-1 text-sm">{{ $guide->description }}</flux:text>
                            @endif
                        </div>
                        <flux:button size="sm" icon="arrow-down-tray" :href="route('study-guides.download', $guide)">{{ __('Download') }}</flux:button>
                    </div>
                @empty
                    <flux:text>{{ __('Study guides will be posted here.') }}</flux:text>
                @endforelse
            </flux:card>
        </div>

        <div class="flex flex-col gap-6">
            {{-- Delegate card --}}
            @if ($committee)
                <flux:card class="flex flex-col gap-4">
                    <div class="hb-kicker">{{ __('Your delegate card') }}</div>
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
                </flux:card>
            @endif

            {{-- Announcements --}}
            <flux:card class="flex flex-col gap-3">
                <div class="hb-kicker">{{ __('Announcements') }}</div>
                @forelse ($announcements as $announcement)
                    <div @class([
                        'border-t border-zinc-200 pt-3 first-of-type:border-0 first-of-type:pt-0 dark:border-zinc-700',
                        'hb-fresh' => in_array($announcement->id, $fresh['announcements']),
                    ])>
                        <div class="font-medium text-zinc-800 dark:text-white">
                            {{ $announcement->title }}
                            @if (in_array($announcement->id, $fresh['announcements']))
                                <flux:badge size="sm" color="purple" class="ms-1">{{ __('New') }}</flux:badge>
                            @endif
                        </div>
                        <flux:text class="text-xs">
                            {{ $announcement->created_at->timezone('Asia/Karachi')->format('j M, g:i a') }}
                            @if ($announcement->committee)
                                · {{ $announcement->committee->code }}
                            @endif
                        </flux:text>
                        <flux:text class="mt-1 text-sm whitespace-pre-line">{{ $announcement->body }}</flux:text>
                    </div>
                @empty
                    <flux:text>{{ __('No announcements yet.') }}</flux:text>
                @endforelse
            </flux:card>

            {{-- Delegation --}}
            <flux:card class="flex flex-col gap-3">
                <div>
                    <div class="hb-kicker">{{ __('Your delegation') }}</div>
                    <flux:text class="mt-1">{{ $registration->displayName() }} · {{ $registration->reference }}</flux:text>
                </div>
                @foreach ($registration->delegates as $member)
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span class="font-medium text-zinc-800 dark:text-white">{{ $member->name }}@if ($member->is($delegate)) <span class="font-normal text-zinc-500">({{ __('you') }})</span>@endif</span>
                        <span class="text-zinc-500">{{ $member->hasPublishedAllocation() ? $member->allocatedCommittee->code.' · '.$member->allocated_portfolio : __('Pending') }}</span>
                    </div>
                @endforeach
            </flux:card>
        </div>
    </div>
@endif
