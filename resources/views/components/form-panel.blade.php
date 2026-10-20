@props([
    'title',
    'subtitle' => null,
    'titleId' => null,
])

@php
    $resolvedTitleId = $titleId ?: 'form-panel-title-'.str_replace('.', '', uniqid('', true));
@endphp

<section
    {{ $attributes->class(['rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6']) }}
    role="region"
    aria-labelledby="{{ $resolvedTitleId }}"
>
    <div class="border-b border-slate-100 pb-4">
        <h2 id="{{ $resolvedTitleId }}" class="text-lg font-semibold text-slate-900">{{ $title }}</h2>
        @if (filled($subtitle))
            <p class="mt-1 text-sm text-slate-500">{{ $subtitle }}</p>
        @endif
    </div>

    <div class="mt-5">
        {{ $slot }}
    </div>
</section>
