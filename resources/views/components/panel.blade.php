@props([
    'padding' => true,
])

<div
    {{ $attributes->class([
        'rounded-xl border border-slate-200 bg-white shadow-sm',
        'p-4 sm:p-5' => $padding,
    ]) }}
>
    {{ $slot }}
</div>
