@props([
    'type' => 'status',
])

@php
    $classes = match ($type) {
        'error' => 'border-red-200 bg-red-50 text-red-700',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-800',
        default => 'border-brand-200 bg-brand-50 text-brand-800',
    };

    $role = $type === 'error' ? 'alert' : 'status';
@endphp

<div {{ $attributes->class(['rounded-lg border px-4 py-3 text-sm', $classes]) }} role="{{ $role }}">
    {{ $slot }}
</div>
