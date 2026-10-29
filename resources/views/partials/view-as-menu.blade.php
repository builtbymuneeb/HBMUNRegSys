{{-- "View as" submenu for admins, inside a user <flux:menu>. --}}
@if (auth()->user()->canPreviewRoles())
    <flux:menu.separator />
    <flux:menu.submenu :heading="__('View as')" icon="eye">
        @foreach ([\App\Enums\UserRole::Registrations, \App\Enums\UserRole::Ambassadors, \App\Enums\UserRole::Applicant, \App\Enums\UserRole::Delegate] as $previewRole)
            <form method="POST" action="{{ route('role-preview.store') }}" class="w-full">
                @csrf
                <input type="hidden" name="role" value="{{ $previewRole->value }}">
                <flux:menu.item as="button" type="submit" class="w-full cursor-pointer" data-test="view-as-{{ $previewRole->value }}">
                    {{ $previewRole->label() }}
                </flux:menu.item>
            </form>
        @endforeach
    </flux:menu.submenu>
@endif
