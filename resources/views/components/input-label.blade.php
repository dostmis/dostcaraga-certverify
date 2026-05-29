@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-medium text-sm']) }} style="color:#334155; margin-bottom:2px;">
    {{ $value ?? $slot }}
</label>
