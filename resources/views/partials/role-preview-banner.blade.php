@php($previewUser = auth()->user())
@if ($previewUser?->isPreviewingRole())
    <div class="pointer-events-none fixed inset-x-0 top-16 z-40 flex justify-center px-4 lg:top-3" role="status">
        <div class="pointer-events-auto flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg bg-amber-500 px-4 py-2 text-sm font-medium text-zinc-950 shadow-lg" data-test="role-preview-banner">
            <flux:icon.eye variant="mini" />
            <span>
                {{ __('Viewing as :role', ['role' => $previewUser->role->label()]) }}
                @if ($previewUser->role === \App\Enums\UserRole::Delegate)
                    · {{ $previewUser->delegate?->name ?? __('no delegates yet') }}
                @endif
            </span>
            <form method="POST" action="{{ route('role-preview.destroy') }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="cursor-pointer rounded-md bg-zinc-950 px-2.5 py-1 text-xs font-semibold text-white hover:bg-zinc-800" data-test="stop-role-preview">
                    {{ __('Back to admin') }}
                </button>
            </form>
        </div>
    </div>
@endif
