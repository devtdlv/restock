@props([
    'value' => null,
    'required' => false,
    'optional' => false,
])

<label {{ $attributes->merge(['class' => 'mb-1.5 block text-sm font-medium text-slate-700']) }}>
    <span>{{ $value ?? $slot }}</span>
    @if ($required)
        <span class="text-red-600" aria-hidden="true">*</span>
        <span class="sr-only">(required)</span>
    @elseif ($optional)
        <span class="font-normal text-slate-400">(optional)</span>
    @endif
</label>
