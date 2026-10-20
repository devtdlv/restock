@props([
    'caption' => null,
])

<div {{ $attributes->class(['app-table-scroll overflow-x-auto']) }}>
    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
        @if (filled($caption))
            <caption class="sr-only">{{ $caption }}</caption>
        @endif
        {{ $slot }}
    </table>
</div>
