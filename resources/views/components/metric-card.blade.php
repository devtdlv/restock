@props([
    'label',
    'value',
    'hint' => null,
    'tone' => 'default',
    'href' => null,
])

@php
    $toneClasses = match ($tone) {
        'warning' => 'border-amber-200 bg-amber-50/70',
        'danger' => 'border-red-200 bg-red-50/70',
        'brand' => 'border-brand-200 bg-brand-50/50',
        default => 'border-slate-200 bg-white',
    };

    $valueClasses = match ($tone) {
        'warning' => 'text-amber-900',
        'danger' => 'text-red-800',
        'brand' => 'text-brand-900',
        default => 'text-slate-900',
    };

    $labelClasses = match ($tone) {
        'warning' => 'text-amber-800',
        'danger' => 'text-red-700',
        'brand' => 'text-brand-800',
        default => 'text-slate-500',
    };

    $hintClasses = match ($tone) {
        'warning' => 'text-amber-700/90',
        'danger' => 'text-red-700/90',
        'brand' => 'text-brand-700/90',
        default => 'text-slate-500',
    };

    $interactive = filled($href);
    $cardClasses = [
        'rounded-xl border p-5 shadow-sm',
        $toneClasses,
        'block transition hover:shadow focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500' => $interactive,
    ];
@endphp

@if ($interactive)
    <a href="{{ $href }}" wire:navigate {{ $attributes->class($cardClasses) }}>
@else
    <div {{ $attributes->class($cardClasses) }}>
@endif
        <p @class(['text-xs font-medium uppercase tracking-wide', $labelClasses])>{{ $label }}</p>
        <p @class(['mt-2 text-3xl font-semibold tracking-tight tabular-nums', $valueClasses])>{{ $value }}</p>
        @if (filled($hint))
            <p @class(['mt-2 text-xs', $hintClasses])>{{ $hint }}</p>
        @endif
        @if ($interactive)
            <p @class(['mt-3 text-xs font-medium', $labelClasses])>View details</p>
        @endif
@if ($interactive)
    </a>
@else
    </div>
@endif
