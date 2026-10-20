@props([
    'level' => null,
    'product' => null,
])

@php
    $resolved = $level instanceof \App\Enums\StockLevel
        ? $level
        : ($product instanceof \App\Models\Product ? $product->stockLevel() : \App\Enums\StockLevel::InStock);

    $accessibleLabel = match ($resolved) {
        \App\Enums\StockLevel::OutOfStock => 'Critical: '.$resolved->label(),
        \App\Enums\StockLevel::LowStock => 'Warning: '.$resolved->label(),
        default => $resolved->label(),
    };
@endphp

<x-badge :variant="$resolved->badgeVariant()" :aria-label="$accessibleLabel" {{ $attributes }}>
    {{ $resolved->label() }}
</x-badge>
