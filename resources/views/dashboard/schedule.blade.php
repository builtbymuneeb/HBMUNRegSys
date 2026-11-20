{{-- Conference timetable. Links are only rendered once staff switch them on;
     until then a decoy "Join" button opens a little gotcha popup. --}}
@php($scheduleDays = \App\Models\ScheduleItem::ordered()->get()->groupBy('day'))
@if ($scheduleDays->isNotEmpty())
    <section id="schedule" class="flex scroll-mt-20 flex-col gap-2" x-data="{ day: {{ (int) $scheduleDays->keys()->first() }}, gotcha: false }" x-on:keydown.escape.window="gotcha = false">
        <div class="flex items-center justify-between gap-2 px-1">
            <h2 class="text-[15px] font-semibold text-zinc-900 dark:text-white">{{ __('Schedule') }}</h2>
            <div class="flex gap-1 rounded-full bg-zinc-100 p-0.5 dark:bg-white/6">
                @foreach ($scheduleDays->keys() as $dayNumber)
                    <button type="button" x-on:click="day = {{ (int) $dayNumber }}"
                        x-bind:class="day === {{ (int) $dayNumber }} ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-700 dark:text-white' : 'text-zinc-500 dark:text-zinc-400'"
                        class="rounded-full px-3 py-1 text-xs font-semibold">{{ __('Day :day', ['day' => $dayNumber]) }}</button>
                @endforeach
            </div>
        </div>
        @foreach ($scheduleDays as $dayNumber => $items)
            <div class="hb-list" x-show="day === {{ (int) $dayNumber }}" @if (! $loop->first) x-cloak @endif>
                @foreach ($items as $item)
                    @php($isBreak = strcasecmp($item->activity, 'Break') === 0)
                    <div class="hb-row">
                        <div class="w-36 shrink-0 text-xs font-medium tabular-nums text-zinc-500 dark:text-zinc-400">{{ $item->timeLabel() }}</div>
                        <div @class(['min-w-0 flex-1 truncate font-semibold', 'text-zinc-500 dark:text-zinc-400' => $isBreak, 'text-zinc-900 dark:text-white' => ! $isBreak])>{{ $item->activity }}</div>
                        @if ($url = $item->delegateLink())
                            <flux:button size="sm" variant="primary" icon="arrow-top-right-on-square" :href="$url" target="_blank">{{ __('Open') }}</flux:button>
                        @elseif (! $isBreak)
                            <flux:button size="sm" variant="filled" icon="video-camera" x-on:click="gotcha = true">{{ __('Join') }}</flux:button>
                        @endif
                    </div>
                @endforeach
            </div>
        @endforeach

        {{-- Easter egg --}}
        <template x-teleport="body">
            <div x-show="gotcha" x-cloak x-transition.opacity class="hb-gotcha-backdrop" x-on:click.self="gotcha = false" role="dialog" aria-modal="true" aria-labelledby="hb-gotcha-title">
                <div x-show="gotcha" x-transition.scale.80 class="hb-gotcha">
                    @foreach ([['6%', '8%', '0s', '✨'], ['84%', '6%', '.4s', '⭐'], ['8%', '80%', '.8s', '💫'], ['86%', '78%', '.2s', '✨'], ['46%', '2%', '.6s', '🌟']] as [$x, $y, $delay, $emoji])
                        <span class="hb-sparkle" style="left: {{ $x }}; top: {{ $y }}; animation-delay: {{ $delay }}" aria-hidden="true">{{ $emoji }}</span>
                    @endforeach
                    <div class="hb-wiggle" aria-hidden="true">😜</div>
                    <div id="hb-gotcha-title" class="hb-gotcha-title font-display">{{ __('Haha, gotcha!') }} 🎉</div>
                    <p class="hb-gotcha-text">{{ __('Links will be dropped 30 mins before the session.') }} ✨</p>
                    <p class="hb-gotcha-small">{{ __('Keep this tab open — we’ll ping you.') }} 🔔</p>
                    <button type="button" x-on:click="gotcha = false" class="hb-gotcha-button">{{ __('Okay okay') }} 🙈</button>
                </div>
            </div>
        </template>
    </section>

    @once
        <style>
            .hb-gotcha-backdrop { position: fixed; inset: 0; z-index: 100; display: grid; place-items: center; padding: 16px; background: rgb(0 0 0 / .65); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); }
            .hb-gotcha { position: relative; width: 100%; max-width: 360px; overflow: hidden; border-radius: 24px; border: 1px solid rgb(255 255 255 / .12); background: linear-gradient(145deg, #3b0a6b 0%, #1a0b2e 55%, #120a1c 100%); padding: 32px 24px 26px; text-align: center; color: #fff; box-shadow: 0 25px 60px rgb(0 0 0 / .55), 0 0 0 1px rgb(201 182 234 / .08) inset; }
            .hb-gotcha-title { margin-top: 10px; font-size: 26px; line-height: 1.2; font-weight: 600; }
            .hb-gotcha-text { margin-top: 10px; font-size: 14px; line-height: 1.5; color: rgb(255 255 255 / .85); }
            .hb-gotcha-small { margin-top: 4px; font-size: 12px; color: rgb(255 255 255 / .55); }
            .hb-gotcha-button { margin-top: 20px; border-radius: 999px; background: #fff; padding: 9px 22px; font-size: 14px; font-weight: 600; color: #2a0f4a; cursor: pointer; transition: transform .15s; }
            .hb-gotcha-button:hover { transform: scale(1.05); }
            .hb-sparkle { position: absolute; pointer-events: none; font-size: 20px; }
            .hb-wiggle { font-size: 56px; line-height: 1; }
            .hb-sparkle { animation: hb-sparkle 1.6s ease-in-out infinite; }
            @keyframes hb-sparkle { 0%, 100% { opacity: .2; transform: scale(.6) rotate(0deg); } 50% { opacity: 1; transform: scale(1.2) rotate(20deg); } }
            .hb-wiggle { display: inline-block; animation: hb-wiggle 1s ease-in-out infinite; }
            @keyframes hb-wiggle { 0%, 100% { transform: rotate(-10deg); } 50% { transform: rotate(10deg) scale(1.1); } }
            @media (prefers-reduced-motion: reduce) { .hb-sparkle, .hb-wiggle { animation: none; } }
        </style>
    @endonce
@endif
