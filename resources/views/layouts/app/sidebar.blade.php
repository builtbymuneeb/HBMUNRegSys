<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        @include('partials.theme-head')
    </head>
    <body class="hb-app min-h-screen text-zinc-800 dark:text-zinc-100">
        @php($navUser = auth()->user())
        @php($staffNav = \App\Support\AppNavigation::staffItems($navUser))
        @php($ambassadorNav = \App\Support\AppNavigation::ambassadorItems($navUser))
        @php($phoneNav = \App\Support\AppNavigation::phoneTabs($navUser))
        <flux:sidebar sticky collapsible="mobile" class="border-e">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                @if ($ambassadorNav)
                    <flux:sidebar.group :heading="__('Campus ambassador')" class="grid">
                        @foreach ($ambassadorNav as $item)
                            <flux:sidebar.item :icon="$item['icon']" :href="$item['url']" :current="$item['current']" wire:navigate>
                                {{ $item['label'] }}
                            </flux:sidebar.item>
                        @endforeach
                    </flux:sidebar.group>
                @endif

                @if ($staffNav)
                    <flux:sidebar.group :heading="__('Staff')" class="grid">
                        @foreach ($staffNav as $item)
                            <flux:sidebar.item :icon="$item['icon']" :href="$item['url']" :current="$item['current']" :badge="in_array($item['key'], ['inbox', 'ambassador-tasks'], true) ? $item['badge'] : null" wire:navigate>
                                {{ $item['label'] }}
                            </flux:sidebar.item>
                        @endforeach
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="globe-alt" :href="route('home')">
                    {{ __('Conference website') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="hb-tabbar sticky top-0 z-30 !min-h-0 border-b !px-3 py-2 backdrop-blur-xl lg:hidden" style="padding-top: max(0.5rem, env(safe-area-inset-top)); padding-bottom: 0.5rem">
            <a href="{{ route('dashboard') }}" wire:navigate class="flex min-w-0 items-center gap-2">
                <x-app-logo-icon class="size-8 shrink-0" />
                <span class="truncate font-display text-[15px] font-semibold text-zinc-900 dark:text-white">{{ $title ?? config('app.name') }}</span>
            </a>

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                        <flux:menu.item :href="route('appearance.edit')" icon="swatch" wire:navigate>
                            {{ __('Theme') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    @include('partials.view-as-menu')

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @include('partials.phone-tabs', ['tabs' => $phoneNav['tabs'], 'more' => $phoneNav['more']])
        @include('partials.theme-welcome')

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @include('partials.offline-banner')
        @include('partials.role-preview-banner')

        @fluxScripts
    </body>
</html>
