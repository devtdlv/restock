<div class="space-y-6">
    <x-page-header
        title="Reports & Analytics"
        subtitle="Read-only insights from current inventory, stock history, and purchase orders. Reports never change stock or purchasing data."
    />

    <x-panel class="sm:p-6">
        <h2 class="text-lg font-semibold text-slate-900">Inventory overview</h2>
        <p class="mt-1 text-sm text-slate-500">
            Units on hand, inventory value, and stock alerts use <span class="font-medium text-slate-700">active products only</span>.
            Inventory value = quantity on hand × stored cost price (decimal-safe).
        </p>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <x-metric-card
                label="Active products"
                :value="$display->quantity($metrics['active_products'])"
                :hint="'Inactive products: '.$display->quantity($metrics['inactive_products'])"
            />
            <x-metric-card
                label="Units on hand (active)"
                :value="$display->quantity($metrics['total_units_on_hand'])"
            />
            <x-metric-card
                label="Inventory value at cost"
                :value="$inventoryValueDisplay"
                hint="Active products only"
            />
            <x-metric-card
                label="Low-stock products"
                :value="$display->quantity($metrics['low_stock_products'])"
                hint="Quantity on hand ≤ reorder level"
                :tone="$metrics['low_stock_products'] > 0 ? 'warning' : 'default'"
                :href="$metrics['low_stock_products'] > 0 ? route('reports.low-stock') : null"
            />
            <x-metric-card
                label="Out-of-stock products"
                :value="$display->quantity($metrics['out_of_stock_products'])"
                :tone="$metrics['out_of_stock_products'] > 0 ? 'danger' : 'default'"
                :href="$metrics['out_of_stock_products'] > 0 ? route('reports.low-stock') : null"
                class="sm:col-span-2 xl:col-span-1"
            />
        </div>
    </x-panel>

    <div class="grid gap-4 md:grid-cols-3">
        @foreach ($sections as $section)
            <a
                href="{{ route($section['route']) }}"
                wire:navigate
                class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-brand-300 hover:shadow focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500"
            >
                <h2 class="text-base font-semibold text-slate-900">{{ $section['title'] }}</h2>
                <p class="mt-2 text-sm text-slate-500">{{ $section['description'] }}</p>
                <p class="mt-4 text-sm font-medium text-brand-700">Open report →</p>
            </a>
        @endforeach
    </div>
</div>
