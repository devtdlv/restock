@props(['active' => true])

<x-badge :variant="$active ? 'active' : 'inactive'" {{ $attributes->class(['uppercase']) }}>
    {{ $active ? 'Active' : 'Inactive' }}
</x-badge>
