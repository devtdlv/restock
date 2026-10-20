@props([
    'status',
])

@php
    $resolved = $status instanceof \App\Enums\PurchaseOrderStatus
        ? $status
        : \App\Enums\PurchaseOrderStatus::from((string) $status);
@endphp

<x-badge :variant="$resolved->badgeVariant()" {{ $attributes }}>
    {{ $resolved->label() }}
</x-badge>
