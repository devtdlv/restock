<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <a href="{{ route('reports.index') }}" class="text-sm font-medium text-brand-700 hover:text-brand-800" wire:navigate>
                ← Back to reports
            </a>
            <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900">Purchasing Report</h1>
            <p class="mt-1 text-sm text-slate-500">
                Status counts include every matching purchase order.
                Ordered value, received quantity, and outstanding quantity use
                <span class="font-medium text-slate-700">committed orders only</span>
                (Ordered, Partially Received, Received). Draft and Cancelled are excluded from those aggregates.
                Monetary totals use historical purchase-order unit costs.
            </p>
        </div>
        <x-secondary-button type="button" class="w-auto" wire:click="export" wire:loading.attr="disabled">
            Export CSV
        </x-secondary-button>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <div class="xl:col-span-1">
                <label for="po-report-search" class="sr-only">Search</label>
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
                <select
                    wire:model.live="supplierId"
                    id="po-supplier"
                    class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                >
                    <option value="">All suppliers</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="po-status" value="Status" />
                <select
                    wire:model.live="status"
                    id="po-status"
                    class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                >
                    <option value="">All statuses</option>
                    @foreach ($statuses as $statusOption)
                        <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <dl class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ([
            ['Total purchase orders', $summary['total_orders']],
            ['Draft', $summary['draft_orders']],
            ['Ordered', $summary['ordered_orders']],
            ['Partially received', $summary['partially_received_orders']],
            ['Fully received', $summary['received_orders']],
            ['Cancelled', $summary['cancelled_orders']],
            ['Committed ordered value', $committedOrderedValueDisplay],
            ['Committed received qty', $summary['committed_received_quantity']],
            ['Committed outstanding qty', $summary['committed_outstanding_quantity']],
        ] as [$label, $value])
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $label }}</dt>
                <dd class="mt-2 text-2xl font-semibold text-slate-900">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Purchase orders</h2>
        </div>
        @if ($orders->isEmpty())
            <div class="px-6 py-16 text-center">
                <p class="text-base font-semibold text-slate-900">No purchase orders match</p>
                <p class="mt-2 text-sm text-slate-500">Adjust search or filters to see results.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">PO</th>
                            <th class="px-4 py-3 font-medium">Supplier</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Order date</th>
                            <th class="px-4 py-3 font-medium">Expected</th>
                            <th class="px-4 py-3 font-medium">Ordered total</th>
                            <th class="px-4 py-3 font-medium">Received</th>
                            <th class="px-4 py-3 font-medium">Outstanding</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($orders as $order)
                            @php
                                $orderedQty = (int) $order->items->sum('ordered_quantity');
                                $receivedQty = (int) $order->items->sum('received_quantity');
                            @endphp
                            <tr wire:key="po-report-{{ $order->id }}" class="hover:bg-slate-50/80">
                                <td class="px-4 py-3 font-medium text-slate-900">{{ $order->number }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $order->supplier?->name ?? 'Unavailable' }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $order->status->label() }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $display->date($order->order_date) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $order->expected_delivery_date ? $display->date($order->expected_delivery_date) : '—' }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $display->money($order->totalAmount()) }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $receivedQty }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ max(0, $orderedQty - $receivedQty) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
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
            <div class="px-6 py-12 text-center text-sm text-slate-500">No supplier activity matches the current filters.</div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">Supplier</th>
                            <th class="px-4 py-3 font-medium">Code</th>
                            <th class="px-4 py-3 font-medium">Orders</th>
                            <th class="px-4 py-3 font-medium">Committed ordered value</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($supplierSummary as $row)
                            <tr wire:key="supplier-summary-{{ $row['supplier_id'] }}">
                                <td class="px-4 py-3 font-medium text-slate-900">{{ $row['supplier_name'] }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $row['supplier_code'] }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $row['order_count'] }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $display->money($row['committed_ordered_value']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
