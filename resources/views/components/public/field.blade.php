@props(['label', 'name', 'hint' => null])

<div {{ $attributes->class(['flex min-w-0 flex-col gap-2', '[&_.hb-input]:border-red-400' => $errors->has($name)]) }}>
    <label class="flex flex-col gap-2">
        <span class="hb-label">{{ $label }}</span>
        {{ $slot }}
    </label>
    @if ($hint)
        <span class="text-xs text-hb-subtle">{{ $hint }}</span>
    @endif
    <x-public.error :name="$name" />
</div>
