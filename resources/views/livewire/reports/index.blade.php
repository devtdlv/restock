<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Reports &amp; Analytics</h1>
        <p class="mt-1 text-sm text-slate-500">
            Read-only insights from current inventory, stock history, and purchase orders. Reports never change stock or purchasing data.
        </p>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="text-lg font-semibold text-slate-900">Inventory overview</h2>
        <p class="mt-1 text-sm text-slate-500">
            Units on hand, inventory value, and stock alerts use <span class="font-medium text-slate-700">active products only</span>.
            Inventory value = quantity on hand × stored cost price (decimal-safe).
        </p>

        <dl class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Active products</dt>
                <dd class="mt-2 text-2xl font-semibold text-slate-900">{{ $metrics['active_products'] }}</dd>
            </div>
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Inactive products</dt>
                <dd class="mt-2 text-2xl font-semibold text-slate-900">{{ $metrics['inactive_products'] }}</dd>
            </div>
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Units on hand (active)</dt>
                <dd class="mt-2 text-2xl font-semibold text-slate-900">{{ $metrics['total_units_on_hand'] }}</dd>
            </div>
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Inventory value at cost (active)</dt>
                <dd class="mt-2 text-2xl font-semibold text-slate-900">{{ $inventoryValueDisplay }}</dd>
            </div>
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Low-stock products (active)</dt>
                <dd class="mt-2 text-2xl font-semibold text-slate-900">{{ $metrics['low_stock_products'] }}</dd>
                <p class="mt-1 text-xs text-slate-500">Quantity on hand ≤ reorder level</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Out-of-stock products (active)</dt>
                <dd class="mt-2 text-2xl font-semibold text-slate-900">{{ $metrics['out_of_stock_products'] }}</dd>
            </div>
        </dl>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        @foreach ($sections as $section)
            <a
                href="{{ route($section['route']) }}"
                wire:navigate
                class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-brand-300 hover:shadow"
            >
                <h2 class="text-base font-semibold text-slate-900">{{ $section['title'] }}</h2>
                <p class="mt-2 text-sm text-slate-500">{{ $section['description'] }}</p>
                <p class="mt-4 text-sm font-medium text-brand-700">Open report →</p>
            </a>
        @endforeach
    </div>
</div>
