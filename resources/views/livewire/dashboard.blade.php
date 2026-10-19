<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">
            Welcome, {{ Auth::user()->name }}
        </h1>
        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500">
            Overview of active inventory and purchasing attention items.
            Metrics reuse the same read-only calculations as Reports.
        </p>
    </div>

    <dl class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Active products</dt>
            <dd class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{{ $metrics['active_products'] }}</dd>
            <p class="mt-2 text-xs text-slate-500">Inactive products: {{ $metrics['inactive_products'] }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Units on hand</dt>
            <dd class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{{ $metrics['total_units_on_hand'] }}</dd>
            <p class="mt-2 text-xs text-slate-500">Active products only</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Low-stock products</dt>
            <dd class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{{ $metrics['low_stock_products'] }}</dd>
            <p class="mt-2 text-xs text-slate-500">Out of stock: {{ $metrics['out_of_stock_products'] }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Inventory value at cost</dt>
            <dd class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{{ $inventoryValueDisplay }}</dd>
            <p class="mt-2 text-xs text-slate-500">Display symbol only; stored costs unchanged</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:col-span-2 xl:col-span-1">
            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Outstanding purchase orders</dt>
            <dd class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{{ $outstandingPurchaseOrders }}</dd>
            <p class="mt-2 text-xs text-slate-500">Ordered or partially received</p>
        </div>
    </dl>

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="text-sm font-semibold text-slate-900">Quick links</h2>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['label' => 'Products', 'route' => 'products.index', 'description' => 'Manage SKUs and stock levels'],
                ['label' => 'Low stock report', 'route' => 'reports.low-stock', 'description' => 'Review replenishment gaps'],
                ['label' => 'Purchase orders', 'route' => 'purchase-orders.index', 'description' => 'Track receiving progress'],
                ['label' => 'Settings', 'route' => 'settings.index', 'description' => 'Business and display preferences'],
            ] as $link)
                <a
                    href="{{ route($link['route']) }}"
                    wire:navigate
                    class="rounded-lg border border-slate-200 px-4 py-3 transition hover:border-brand-300 hover:bg-brand-50/40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500"
                >
                    <p class="text-sm font-semibold text-slate-900">{{ $link['label'] }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $link['description'] }}</p>
                </a>
            @endforeach
        </div>
    </div>
</div>
