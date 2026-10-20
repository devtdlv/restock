@props([
    'title',
    'variant' => 'default',
    'closeAction' => null,
    'closeOnEscape' => true,
    'closeOnBackdrop' => true,
])

@php
    $titleId = 'dialog-title-'.str_replace('.', '', uniqid('', true));

    $panelTone = match ($variant) {
        'danger' => 'border-red-200 bg-white',
        'info' => 'border-sky-200 bg-white',
        default => 'border-slate-200 bg-white',
    };

    $titleTone = match ($variant) {
        'danger' => 'text-red-800',
        'info' => 'text-sky-900',
        default => 'text-slate-900',
    };
@endphp

<div
    {{ $attributes->class(['fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center']) }}
    x-data="stockPilotDialog(@js($closeAction))"
    x-init="init()"
    @if ($closeOnEscape)
        x-on:keydown.escape.window="requestClose()"
    @endif
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $titleId }}"
>
    <div
        class="fixed inset-0 bg-slate-900/45"
        aria-hidden="true"
        @if ($closeOnBackdrop)
            x-on:click="requestClose()"
        @endif
    ></div>

    <div
        x-ref="panel"
        tabindex="-1"
        @class([
            'relative z-10 flex max-h-[min(90vh,40rem)] w-full max-w-lg flex-col overflow-hidden rounded-xl border shadow-xl outline-none',
            $panelTone,
        ])
        x-on:click.stop
    >
        <div class="overflow-y-auto p-5 sm:p-6">
            <h2 id="{{ $titleId }}" @class(['text-lg font-semibold', $titleTone])>
                {{ $title }}
            </h2>

            <div class="mt-2 text-sm text-slate-600">
                {{ $slot }}
            </div>

            @isset($footer)
                <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:flex-wrap sm:justify-end">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</div>
