<div class="space-y-6">
    <x-page-header
        :title="'Welcome, '.Auth::user()->name"
        subtitle="Overview of active inventory and purchasing attention items. Metrics reuse the same read-only calculations as Reports."
    />

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <x-metric-card
            label="Active products"
            :value="$display->quantity($metrics['active_products'])"
            :hint="'Inactive products: '.$display->quantity($metrics['inactive_products'])"
        />
        <x-metric-card
            label="Units on hand"
            :value="$display->quantity($metrics['total_units_on_hand'])"
            hint="Active products only"
        />
        <x-metric-card
            label="Inventory value at cost"
            :value="$inventoryValueDisplay"
            hint="Display symbol only; stored costs unchanged"
        />
        <x-metric-card
            label="Low-stock products"
            :value="$display->quantity($metrics['low_stock_products'])"
            :hint="'Out of stock: '.$display->quantity($metrics['out_of_stock_products'])"
            :tone="$metrics['out_of_stock_products'] > 0 ? 'danger' : ($metrics['low_stock_products'] > 0 ? 'warning' : 'default')"
            :href="$metrics['low_stock_products'] > 0 ? route('reports.low-stock') : null"
        />
        <x-metric-card
            label="Outstanding purchase orders"
            :value="$display->quantity($outstandingPurchaseOrders)"
            hint="Ordered or partially received"
            :tone="$outstandingPurchaseOrders > 0 ? 'brand' : 'default'"
            :href="$outstandingPurchaseOrders > 0 ? route('purchase-orders.index') : null"
            class="sm:col-span-2 xl:col-span-1"
        />
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
        <x-panel class="sm:p-6" :padding="true">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Needs attention</h2>
                    <p class="mt-1 text-xs text-slate-500">Active products at or below reorder level, plus open purchase orders.</p>
                </div>
                <a href="{{ route('reports.low-stock') }}" class="text-xs font-medium text-brand-700 hover:text-brand-800" wire:navigate>
                    Low stock report
                </a>
            </div>

            <div class="mt-4 space-y-4">
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Low stock</h3>
                    @if ($attentionProducts->isEmpty())
                        <p class="mt-2 text-sm text-slate-500">No active products need replenishment right now.</p>
                    @else
                        <ul class="mt-2 divide-y divide-slate-100 rounded-lg border border-slate-200">
                            @foreach ($attentionProducts as $product)
                                <li class="flex items-center gap-3 px-3 py-2.5">
                                    <x-product-image :product="$product" size="xs" />
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-slate-900">{{ $product->name }}</p>
                                        <p class="font-mono text-xs text-slate-500">{{ $product->sku }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-medium tabular-nums text-slate-900">{{ $display->quantity($product->quantity_on_hand) }}</p>
                                        <x-stock-level-badge :product="$product" class="mt-1" />
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Open purchase orders</h3>
                    @if ($attentionOrders->isEmpty())
                        <p class="mt-2 text-sm text-slate-500">No ordered or partially received purchase orders.</p>
                    @else
                        <ul class="mt-2 divide-y divide-slate-100 rounded-lg border border-slate-200">
                            @foreach ($attentionOrders as $order)
                                <li>
                                    <a
                                        href="{{ route('purchase-orders.show', $order) }}"
                                        wire:navigate
                                        class="flex items-center gap-3 px-3 py-2.5 transition hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500"
                                    >
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-medium text-slate-900">{{ $order->number }}</p>
                                            <p class="truncate text-xs text-slate-500">{{ $order->supplier?->name ?? 'Supplier unavailable' }}</p>
                                        </div>
                                        <x-purchase-order-status :status="$order->status" />
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </x-panel>

        <x-panel class="sm:p-6" :padding="true">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Recent stock movements</h2>
                    <p class="mt-1 text-xs text-slate-500">Latest inventory changes across all products.</p>
                </div>
                <a href="{{ route('stock-movements.index') }}" class="text-xs font-medium text-brand-700 hover:text-brand-800" wire:navigate>
                    View all
                </a>
            </div>

            @if ($recentMovements->isEmpty())
                <x-empty-state
                    class="py-10"
                    title="No stock movements yet"
                    description="Use Adjust Stock on the Products page to record inventory changes."
                />
            @else
                <ul class="mt-4 divide-y divide-slate-100 rounded-lg border border-slate-200">
                    @foreach ($recentMovements as $movement)
                        <li class="flex items-start gap-3 px-3 py-2.5">
                            <x-product-image :product="$movement->product" size="xs" :alt="$movement->product?->name ?? 'Product'" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-slate-900">{{ $movement->product?->name ?? 'Deleted product' }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    {{ $display->dateTime($movement->created_at) }}
                                    · {{ $movement->user?->name ?? 'System' }}
                                </p>
                            </div>
                            <div class="text-right">
                                <x-badge :variant="$movement->type->badgeVariant()">{{ $movement->type->label() }}</x-badge>
                                <p @class([
                                    'mt-1 text-sm font-medium tabular-nums',
                                    'text-brand-700' => $movement->quantity_change > 0,
                                    'text-red-600' => $movement->quantity_change < 0,
                                    'text-slate-700' => $movement->quantity_change === 0,
                                ])>
                                    {{ $display->quantityChange((int) $movement->quantity_change) }}
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-panel>
    </div>

    <x-panel class="sm:p-6" :padding="true">
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
    </x-panel>
</div>
