@props([
    'subtitle' => 'Inventory',
    'compact' => false,
])

@php
    $name = app(\App\Support\AppDisplay::class)->businessName();
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-3']) }}>
    <x-brand-mark :size="$compact ? 'sm' : 'md'" />
    <span class="min-w-0 text-left">
        <span @class([
            'block font-semibold tracking-tight text-inherit',
            'text-sm' => $compact,
            'text-base' => ! $compact,
        ])>{{ $name }}</span>
        @if ($subtitle)
            <span class="block text-xs text-slate-400">{{ $subtitle }}</span>
        @endif
    </span>
</span>
