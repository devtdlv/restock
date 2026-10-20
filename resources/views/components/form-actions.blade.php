@props([])

<div {{ $attributes->class(['flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:flex-wrap sm:items-center']) }}>
    {{ $slot }}
</div>
