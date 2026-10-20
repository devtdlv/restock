@props([
    'variant' => 'neutral',
])

@php
    $variantClasses = match ($variant) {
        'brand' => 'bg-brand-50 text-brand-800',
        'success' => 'bg-brand-50 text-brand-800',
        'info' => 'bg-sky-50 text-sky-800',
        'warning' => 'bg-amber-50 text-amber-800',
        'danger' => 'bg-red-50 text-red-700',
        'active' => 'bg-brand-50 text-brand-800',
        'inactive' => 'bg-slate-100 text-slate-500',
        default => 'bg-slate-100 text-slate-700',
    };
@endphp

<span {{ $attributes->class([
    'inline-flex items-center rounded-md px-2 py-1 text-[11px] font-medium tracking-wide',
    $variantClasses,
]) }}>
    {{ $slot }}
</span>
