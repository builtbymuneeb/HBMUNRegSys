<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main>
        @php($viewOnlyPage = auth()->user()?->isStaff() ? \App\Enums\StaffPage::forCurrentRoute() : null)
        @if ($viewOnlyPage && ! auth()->user()->canEditPage($viewOnlyPage) && $viewOnlyPage->isEditable())
            <div class="flex items-center gap-2 border-b border-amber-200 bg-amber-50 px-6 py-2 text-sm text-amber-800 lg:px-8 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-200" data-test="view-only-banner">
                <flux:icon.eye variant="micro" />
                {{ __('View only: you can look around :page, but changes won’t be saved.', ['page' => __($viewOnlyPage->label())]) }}
            </div>
        @endif
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
