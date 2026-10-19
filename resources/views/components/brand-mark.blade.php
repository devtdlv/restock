@props([
    'size' => 'md',
])

@php
    $sizeClass = match ($size) {
        'sm' => 'h-8 w-8',
        'lg' => 'h-12 w-12',
        default => 'h-9 w-9',
    };
@endphp

<span {{ $attributes->class([$sizeClass, 'inline-flex shrink-0 items-center justify-center']) }} aria-hidden="true">
    <svg class="h-full w-full" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg" focusable="false">
        <rect width="32" height="32" rx="8" class="fill-brand-700"/>
        <rect x="7" y="8" width="18" height="4" rx="1.2" class="fill-brand-200"/>
        <rect x="7" y="14" width="18" height="4" rx="1.2" class="fill-brand-300"/>
        <rect x="7" y="20" width="12" height="4" rx="1.2" class="fill-brand-400"/>
        <circle cx="22.5" cy="22" r="3.2" class="fill-brand-50"/>
    </svg>
</span>
