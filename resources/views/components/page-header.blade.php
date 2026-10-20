@props([
    'title',
    'subtitle' => null,
    'backHref' => null,
    'backLabel' => 'Back',
])

<div {{ $attributes->class(['flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between']) }}>
    <div class="min-w-0">
        @if (filled($backHref))
            <a
                href="{{ $backHref }}"
                class="inline-flex text-sm font-medium text-brand-700 hover:text-brand-800"
                wire:navigate
            >
                ← {{ $backLabel }}
            </a>
        @endif

        <h1 @class([
            'text-2xl font-bold tracking-tight text-slate-900',
            'mt-2' => filled($backHref),
        ])>{{ $title }}</h1>

        @isset($description)
            <div class="mt-1 max-w-3xl text-sm leading-relaxed text-slate-500">
                {{ $description }}
            </div>
        @elseif (filled($subtitle))
            <p class="mt-1 max-w-3xl text-sm leading-relaxed text-slate-500">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex w-full shrink-0 flex-wrap items-center gap-2 sm:w-auto sm:justify-end">
            {{ $actions }}
        </div>
    @endisset
</div>
