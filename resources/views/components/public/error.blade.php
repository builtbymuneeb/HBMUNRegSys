@props(['name'])

@error($name)
    <p {{ $attributes->merge(['class' => 'hb-error']) }}>{{ $message }}</p>
@enderror
