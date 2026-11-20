<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="bg-background flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-sm flex-col gap-2">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium" wire:navigate>
                    <span class="flex h-9 w-9 mb-1 items-center justify-center rounded-md">
                        <x-app-logo-icon class="size-9 fill-current text-black dark:text-white" />
                    </span>
                    <span class="sr-only">{{ config('app.name', 'Laravel') }}</span>
                </a>
                <div class="flex flex-col gap-6">
                    {{ $slot }}
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @include('partials.offline-banner')

        @fluxScripts

        <script>
            // One tap per form. On slow phones people tapped "Log in" / "Reset password" again while
            // the first tap was still working; the second one then failed (e.g. "token is invalid")
            // even though the first had already worked. The button shows Flux's spinner meanwhile.
            document.addEventListener('submit', (event) => {
                const form = event.target;

                if (form.dataset.submitting) {
                    event.preventDefault();

                    return;
                }

                form.dataset.submitting = '1';
                setTimeout(() => form.querySelectorAll('button[type=submit]').forEach((button) => button.disabled = true));
            }, true);

            // Coming back with the browser's back button re-enables the form.
            window.addEventListener('pageshow', () => document.querySelectorAll('form[data-submitting]').forEach((form) => {
                delete form.dataset.submitting;
                form.querySelectorAll('button[type=submit]').forEach((button) => button.disabled = false);
            }));
        </script>
    </body>
</html>
