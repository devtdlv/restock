<div>
    <div class="mb-8">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">
            Welcome, {{ Auth::user()->name }}
        </h1>
        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500">
            StockPilot is ready for your inventory workflow. Product catalogs, stock movements,
            suppliers, and reporting will appear here as modules are enabled.
        </p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['label' => 'Products', 'description' => 'SKU catalog and stock levels'],
            ['label' => 'Categories', 'description' => 'Organize inventory groupings'],
            ['label' => 'Suppliers', 'description' => 'Vendor records and contacts'],
            ['label' => 'Stock movements', 'description' => 'Inbound and outbound activity'],
        ] as $card)
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">{{ $card['label'] }}</p>
                <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-300">N/A</p>
                <p class="mt-2 text-xs text-slate-400">{{ $card['description'] }}</p>
                <p class="mt-4 inline-flex rounded-md bg-slate-100 px-2 py-1 text-[11px] font-medium uppercase tracking-wide text-slate-500">
                    Coming soon
                </p>
            </div>
        @endforeach
    </div>

    <div class="mt-8 rounded-xl border border-dashed border-slate-300 bg-white/70 p-6">
        <h2 class="text-sm font-semibold text-slate-900">Getting started</h2>
        <p class="mt-2 text-sm text-slate-500">
            Authentication is active. Next milestones will introduce inventory data models and operational screens.
            Until then, use the sidebar placeholders to preview the product structure.
        </p>
    </div>
</div>
