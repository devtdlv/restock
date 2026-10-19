@props(['active' => true])

<span
    @class([
        'inline-flex rounded-md px-2 py-1 text-[11px] font-medium uppercase tracking-wide',
        'bg-brand-50 text-brand-800' => $active,
        'bg-slate-100 text-slate-500' => ! $active,
    ])
>
    {{ $active ? 'Active' : 'Inactive' }}
</span>
