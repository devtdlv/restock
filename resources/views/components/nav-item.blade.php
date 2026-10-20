@props([
    'label',
    'route',
    'active' => null,
    'icon' => 'circle',
])

@php
    $isActive = request()->routeIs($active ?? $route);

    $classes = [
        'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-300',
        $isActive ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white',
    ];
@endphp

<a
    href="{{ route($route) }}"
    wire:navigate
    x-on:click="closeSidebar()"
    {{ $attributes
        ->class($classes)
        ->merge($isActive ? ['aria-current' => 'page'] : []) }}
>
    <span class="inline-flex h-4 w-4 shrink-0 items-center justify-center opacity-90" aria-hidden="true">
        @switch($icon)
            @case('home')
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0h4" />
                </svg>
                @break
            @case('cube')
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 8l-9-5-9 5v8l9 5 9-5V8z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l9 5 9-5M12 13v8" />
                </svg>
                @break
            @case('tag')
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M4 7a3 3 0 013-3h4.586a1 1 0 01.707.293l6.414 6.414a1 1 0 010 1.414l-7.586 7.586a1 1 0 01-1.414 0L4.293 12.707A1 1 0 014 12V7z" />
                </svg>
                @break
            @case('arrows')
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                </svg>
                @break
            @case('building')
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 21V5a1 1 0 011-1h9a1 1 0 011 1v16M9 21h11V9a1 1 0 00-1-1h-5M8 8h.01M8 12h.01M12 8h.01M12 12h.01M8 16h.01" />
                </svg>
                @break
            @case('clipboard')
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5h6a2 2 0 012 2v12a2 2 0 01-2 2H9a2 2 0 01-2-2V7a2 2 0 012-2zm0 0V4a1 1 0 011-1h4a1 1 0 011 1v1" />
                </svg>
                @break
            @case('chart')
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 19V9m5 10V5m5 14v-7m5 7v-4" />
                </svg>
                @break
            @case('document')
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h8M8 11h8M8 15h5M7 3h7l4 4v13a1 1 0 01-1 1H7a1 1 0 01-1-1V4a1 1 0 011-1z" />
                </svg>
                @break
            @case('cog')
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <circle cx="12" cy="12" r="3" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2m0 14v2m9-9h-2M5 12H3m15.4-6.4l-1.4 1.4M7 17l-1.4 1.4m12.8 0L17 17M7 7L5.6 5.6" />
                </svg>
                @break
            @default
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
        @endswitch
    </span>
    <span class="truncate">{{ $label }}</span>
</a>
