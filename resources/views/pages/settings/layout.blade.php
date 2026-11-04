@php($settingsLinks = [
    ['route' => 'profile.edit', 'pattern' => 'profile.*', 'label' => __('Profile')],
    ['route' => 'security.edit', 'pattern' => 'security.*', 'label' => __('Security')],
    ['route' => 'appearance.edit', 'pattern' => 'appearance.*', 'label' => __('Theme')],
])

<div class="flex items-start max-md:flex-col">
    {{-- Phone: a row of pills --}}
    <div class="mb-4 flex w-full gap-1 rounded-xl bg-zinc-100 p-1 md:hidden dark:bg-white/6">
        @foreach ($settingsLinks as $link)
            <a href="{{ route($link['route']) }}" wire:navigate @class([
                'flex-1 rounded-lg py-2 text-center text-sm font-semibold',
                'bg-white text-zinc-900 shadow-sm dark:bg-zinc-700 dark:text-white' => request()->routeIs($link['pattern']),
                'text-zinc-500 dark:text-zinc-400' => ! request()->routeIs($link['pattern']),
            ])>{{ $link['label'] }}</a>
        @endforeach
    </div>

    <div class="me-10 hidden w-[220px] pb-4 md:block">
        <flux:navlist aria-label="{{ __('Settings') }}">
            @foreach ($settingsLinks as $link)
                <flux:navlist.item :href="route($link['route'])" wire:navigate>{{ $link['label'] }}</flux:navlist.item>
            @endforeach
        </flux:navlist>
    </div>

    <div class="flex-1 self-stretch">
        <flux:heading class="max-md:hidden">{{ $heading ?? '' }}</flux:heading>
        @if (filled($subheading ?? null))
            <flux:subheading>{{ $subheading }}</flux:subheading>
        @endif

        <div class="w-full max-w-lg md:mt-5">
            {{ $slot }}
        </div>

        <form method="POST" action="{{ route('logout') }}" class="mt-8 md:hidden">
            @csrf
            <button type="submit" class="w-full rounded-2xl border border-zinc-200 py-3 text-[15px] font-semibold text-red-600 dark:border-white/10 dark:text-red-400">{{ __('Log out') }}</button>
        </form>
    </div>
</div>
