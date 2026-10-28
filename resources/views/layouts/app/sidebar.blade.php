<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="hb-app min-h-screen text-zinc-100">
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

                @can('viewAny', \App\Models\Registration::class)
                    <flux:sidebar.group :heading="__('Staff')" class="grid">
                        <flux:sidebar.item icon="clipboard-document-check" :href="route('staff.registrations.index')" :current="request()->routeIs('staff.registrations.*')" wire:navigate>
                            {{ __('Registrations') }}
                        </flux:sidebar.item>
                        @can('manage-conference')
                            <flux:sidebar.item icon="user-group" :href="route('staff.allocations')" :current="request()->routeIs('staff.allocations')" wire:navigate>
                                {{ __('Allocations') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="building-library" :href="route('staff.committees')" :current="request()->routeIs('staff.committees*')" wire:navigate>
                                {{ __('Committees') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="book-open" :href="route('staff.guides')" :current="request()->routeIs('staff.guides')" wire:navigate>
                                {{ __('Study guides') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="megaphone" :href="route('staff.announcements')" :current="request()->routeIs('staff.announcements')" wire:navigate>
                                {{ __('Announcements') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="envelope" :href="route('staff.emails')" :current="request()->routeIs('staff.emails')" wire:navigate>
                                {{ __('Email log') }}
                            </flux:sidebar.item>
                        @endcan
                        @can('manage-staff')
                            <flux:sidebar.item icon="users" :href="route('staff.team')" :current="request()->routeIs('staff.team')" wire:navigate>
                                {{ __('Team') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="chart-bar" :href="route('staff.analytics')" :current="request()->routeIs('staff.analytics')" wire:navigate>
                                {{ __('Visitors') }}
                            </flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endcan
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
        <flux:header class="border-b border-white/10 bg-zinc-950/80 backdrop-blur lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

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
                    </flux:menu.radio.group>

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

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @include('partials.offline-banner')

        @fluxScripts
    </body>
</html>
