<div class="space-y-6">
    <x-page-header
        title="Purchasing Report"
        :back-href="route('reports.index')"
        back-label="Back to reports"
    >
        <x-slot:description>
            Status counts include every matching purchase order.
            Ordered value, received quantity, and outstanding quantity use
            <span class="font-medium text-slate-700">committed orders only</span>
            (Ordered, Partially Received, Received). Draft and Cancelled are excluded from those aggregates.
            Monetary totals use historical purchase-order unit costs.
        </x-slot:description>
        <x-slot:actions>
            <x-secondary-button type="button" class="w-full sm:w-auto" wire:click="export" wire:loading.attr="disabled" wire:target="export">
                <span wire:loading.remove wire:target="export">Export CSV</span>
                <span wire:loading wire:target="export">Exporting…</span>
            </x-secondary-button>
        </x-slot:actions>
    </x-page-header>

    <x-panel>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <div class="xl:col-span-1">
                <x-input-label for="po-report-search" value="Search" />
                <x-text-input
                    wire:model.live.debounce.300ms="search"
                    id="po-report-search"
                    type="search"
                    placeholder="PO number or supplier…"
                />
            </div>
            <div>
                <x-input-label for="po-date-from" value="From order date" />
                <x-text-input wire:model.live="dateFrom" id="po-date-from" type="date" />
            </div>
            <div>
                <x-input-label for="po-date-to" value="To order date" />
                <x-text-input wire:model.live="dateTo" id="po-date-to" type="date" />
            </div>
            <div>
                <x-input-label for="po-supplier" value="Supplier" />
                <x-select-input wire:model.live="supplierId" id="po-supplier">
                    <option value="">All suppliers</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                    @endforeach
                </x-select-input>
            </div>
            <div>
                <x-input-label for="po-status" value="Status" />
                <x-select-input wire:model.live="status" id="po-status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $statusOption)
                        <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
                    @endforeach
                </x-select-input>
            </div>
        </div>
    </x-panel>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <x-metric-card label="Total purchase orders" :value="$display->quantity($summary['total_orders'])" />
        <x-metric-card label="Draft" :value="$display->quantity($summary['draft_orders'])" />
        <x-metric-card label="Ordered" :value="$display->quantity($summary['ordered_orders'])" />
        <x-metric-card label="Partially received" :value="$display->quantity($summary['partially_received_orders'])" tone="warning" />
        <x-metric-card label="Fully received" :value="$display->quantity($summary['received_orders'])" tone="brand" />
        <x-metric-card label="Cancelled" :value="$display->quantity($summary['cancelled_orders'])" />
        <x-metric-card label="Committed ordered value" :value="$committedOrderedValueDisplay" />
        <x-metric-card label="Committed received qty" :value="$display->quantity($summary['committed_received_quantity'])" />
        <x-metric-card label="Committed outstanding qty" :value="$display->quantity($summary['committed_outstanding_quantity'])" />
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Purchase orders</h2>
        </div>
        @if ($orders->isEmpty())
            <x-empty-state
                title="No purchase orders match"
                description="Adjust search or filters to see results."
            />
        @else
            <x-table caption="Purchasing report orders">
                <x-table.header>
                    <tr>
                        <th scope="col" class="px-4 py-3">PO</th>
                        <th scope="col" class="px-4 py-3">Supplier</th>
                        <th scope="col" class="px-4 py-3">Status</th>
                        <th scope="col" class="hidden px-4 py-3 md:table-cell">Order date</th>
                        <th scope="col" class="hidden px-4 py-3 lg:table-cell">Expected</th>
                        <th scope="col" class="px-4 py-3 text-right">Ordered total</th>
                        <th scope="col" class="hidden px-4 py-3 text-right sm:table-cell">Received</th>
                        <th scope="col" class="hidden px-4 py-3 text-right sm:table-cell">Outstanding</th>
                    </tr>
                </x-table.header>
                <x-table.body>
                    @foreach ($orders as $order)
                        @php
                            $orderedQty = (int) $order->items->sum('ordered_quantity');
                            $receivedQty = (int) $order->items->sum('received_quantity');
                        @endphp
                        <tr wire:key="po-report-{{ $order->id }}" class="hover:bg-slate-50/80">
                            <td class="px-4 py-3 font-medium text-slate-900">
                                {{ $order->number }}
                                <div class="mt-0.5 text-xs tabular-nums text-slate-500 md:hidden">
                                    {{ $display->date($order->order_date) }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $order->supplier?->name ?? 'Unavailable' }}</td>
                            <td class="px-4 py-3">
                                <x-purchase-order-status :status="$order->status" />
                            </td>
                            <td class="hidden whitespace-nowrap px-4 py-3 tabular-nums text-slate-600 md:table-cell">{{ $display->date($order->order_date) }}</td>
                            <td class="hidden whitespace-nowrap px-4 py-3 tabular-nums text-slate-600 lg:table-cell">{{ $order->expected_delivery_date ? $display->date($order->expected_delivery_date) : '-' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-700">{{ $display->money($order->totalAmount()) }}</td>
                            <td class="hidden px-4 py-3 text-right tabular-nums text-slate-700 sm:table-cell">{{ $display->quantity($receivedQty) }}</td>
                            <td class="hidden px-4 py-3 text-right tabular-nums text-slate-700 sm:table-cell">{{ $display->quantity(max(0, $orderedQty - $receivedQty)) }}</td>
                        </tr>
                    @endforeach
                </x-table.body>
            </x-table>
            @if ($orders->hasPages())
                <div class="border-t border-slate-200 px-4 py-3">
                    {{ $orders->links() }}
                </div>
            @endif
        @endif
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Supplier summary</h2>
            <p class="mt-1 text-sm text-slate-500">
                Order counts include all matching statuses. Committed ordered value excludes Draft and Cancelled.
            </p>
        </div>
        @if ($supplierSummary->isEmpty())
            <x-empty-state
                title="No supplier activity"
                description="No supplier activity matches the current filters."
            />
        @else
            <x-table caption="Purchasing report supplier summary">
                <x-table.header>
                    <tr>
                        <th scope="col" class="px-4 py-3">Supplier</th>
                        <th scope="col" class="hidden px-4 py-3 sm:table-cell">Code</th>
                        <th scope="col" class="px-4 py-3 text-right">Orders</th>
                        <th scope="col" class="px-4 py-3 text-right">Committed ordered value</th>
                    </tr>
                </x-table.header>
                <x-table.body>
                    @foreach ($supplierSummary as $row)
                        <tr wire:key="supplier-summary-{{ $row['supplier_id'] }}">
                            <td class="px-4 py-3 font-medium text-slate-900">
                                {{ $row['supplier_name'] }}
                                <div class="font-mono text-xs text-slate-500 sm:hidden">{{ $row['supplier_code'] }}</div>
                            </td>
                            <td class="hidden px-4 py-3 font-mono text-xs text-slate-600 sm:table-cell">{{ $row['supplier_code'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-700">{{ $display->quantity($row['order_count']) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-700">{{ $display->money($row['committed_ordered_value']) }}</td>
                        </tr>
                    @endforeach
                </x-table.body>
            </x-table>
        @endif
    </div>
</div>
