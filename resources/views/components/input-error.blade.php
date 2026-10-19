@props(['messages'])

@php
    $resolvedMessages = collect(\Illuminate\Support\Arr::wrap($messages))
        ->flatten()
        ->filter(fn ($message) => filled($message))
        ->values()
        ->all();
@endphp

@if ($resolvedMessages)
    <ul {{ $attributes->merge(['class' => 'mt-1.5 space-y-1 text-sm text-red-600']) }}>
        @foreach ($resolvedMessages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
