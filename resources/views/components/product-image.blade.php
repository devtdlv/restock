@props([
    'product' => null,
    'path' => null,
    'url' => null,
    'alt' => 'Product image',
    'size' => 'md',
])

@php
    $sizeClass = match ($size) {
        'xs' => 'h-8 w-8',
        'sm' => 'h-10 w-10',
        'lg' => 'h-24 w-24',
        default => 'h-12 w-12',
    };

    $resolvedUrl = $url;
    if ($resolvedUrl === null && $product instanceof \App\Models\Product) {
        $resolvedUrl = $product->imageUrl();
        if ($alt === 'Product image') {
            $alt = $product->name;
        }
    } elseif ($resolvedUrl === null && filled($path)) {
        $resolvedUrl = app(\App\Services\Catalog\ProductImageService::class)->url($path);
    }
@endphp

<span {{ $attributes->class([$sizeClass, 'inline-flex shrink-0 overflow-hidden rounded-lg border border-slate-200 bg-slate-50']) }}>
    @if ($resolvedUrl)
        <img
            src="{{ $resolvedUrl }}"
            alt="{{ $alt }}"
            class="h-full w-full object-cover"
            loading="lazy"
        >
    @else
        <span class="flex h-full w-full items-center justify-center bg-brand-50 text-brand-700" role="img" aria-label="{{ $alt }} (no photo)">
            <svg class="h-1/2 w-1/2" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
                <rect x="5" y="8" width="22" height="16" rx="2.5" class="fill-brand-200"/>
                <rect x="8" y="11" width="10" height="2" rx="1" class="fill-brand-500"/>
                <rect x="8" y="15" width="16" height="2" rx="1" class="fill-brand-400"/>
                <circle cx="21.5" cy="20" r="3" class="fill-brand-600"/>
            </svg>
        </span>
    @endif
</span>
