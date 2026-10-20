@props([
    'title',
    'description' => null,
])

<div {{ $attributes->class(['px-6 py-16 text-center']) }}>
    <p class="text-base font-semibold text-slate-900">{{ $title }}</p>

    @if (filled($description))
        <p class="mt-2 text-sm text-slate-500">{{ $description }}</p>
    @elseif (! $slot->isEmpty())
        <div class="mt-2 text-sm text-slate-500">
            {{ $slot }}
        </div>
    @endif

    @isset($actions)
        <div class="mt-6 flex flex-wrap justify-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
