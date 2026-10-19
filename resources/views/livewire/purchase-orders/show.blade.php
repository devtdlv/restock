<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <a href="{{ route('purchase-orders.index') }}" class="text-sm font-medium text-brand-700 hover:text-brand-800" wire:navigate>
                ← Back to purchase orders
            </a>
            <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900">{{ $order->number }}</h1>
            <p class="mt-1 text-sm text-slate-500">
                {{ $order->supplier?->name ?? 'Supplier unavailable' }}
                @if ($order->supplier)
                    <span class="text-slate-400">({{ $order->supplier->code }})</span>
                @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($canEdit)
                <a
                    href="{{ route('purchase-orders.index') }}?edit={{ $order->id }}"
                    class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50"
                    wire:navigate
                >
                    Edit draft
                </a>
            @endif
            @if ($canMarkOrdered)
                <x-primary-button type="button" class="w-auto" wire:click="confirmMarkOrdered">
                    Mark as Ordered
                </x-primary-button>
            @endif
            @if ($canReceive)
                <x-primary-button type="button" class="w-auto" wire:click="openReceiveForm">
                    Receive Inventory
                </x-primary-button>
            @endif
            @if ($canCancel)
                <x-danger-button type="button" wire:click="confirmCancelOrder">
                    Cancel order
                </x-danger-button>
            @endif
        </div>
    </div>

    @if ($statusMessage)
        <div class="rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800" role="status">
            {{ $statusMessage }}
        </div>
    @endif

    @if ($errorMessage)
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
            {{ $errorMessage }}
        </div>
    @endif

    @if ($confirmingMarkOrdered)
        <div class="rounded-xl border border-sky-200 bg-sky-50 p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-sky-900">Mark as Ordered?</h2>
            <p class="mt-1 text-sm text-sky-800">
                This locks supplier, products, quantities, and unit costs. Inventory will not change until you receive goods.
            </p>
            <div class="mt-4 flex flex-wrap gap-3">
                <x-primary-button type="button" class="w-auto" wire:click="markOrdered" wire:loading.attr="disabled">
                    Confirm submit
                </x-primary-button>
                <x-secondary-button type="button" wire:click="cancelMarkOrdered">Keep as draft</x-secondary-button>
            </div>
        </div>
    @endif

    @if ($confirmingCancel)
        <div class="rounded-xl border border-red-200 bg-red-50 p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-red-800">Cancel purchase order?</h2>
            <p class="mt-1 text-sm text-red-700">
                Cancelled orders cannot receive inventory. Orders with received quantity cannot be cancelled.
            </p>
            <div class="mt-4 flex flex-wrap gap-3">
                <x-danger-button type="button" wire:click="cancelOrder" wire:loading.attr="disabled">
                    Confirm cancel
                </x-danger-button>
                <x-secondary-button type="button" wire:click="cancelCancelOrder">Keep order</x-secondary-button>
            </div>
        </div>
    @endif

    @if ($showReceiveForm)
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            @if (! $confirmingReceive)
                <h2 class="text-lg font-semibold text-slate-900">Receive inventory</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Enter quantities to receive. Leave blank or zero for lines you are not receiving now.
                </p>
                <p class="mt-2 text-xs text-slate-400">Receipt key: {{ $receiptKey }}</p>

                <div class="mt-6 space-y-3">
                    @foreach ($order->items as $item)
                        <div wire:key="receive-item-{{ $item->id }}" class="grid gap-3 rounded-lg border border-slate-200 p-3 md:grid-cols-12 md:items-end">
                            <div class="md:col-span-5">
                                <div class="text-sm font-medium text-slate-900">{{ $item->product?->name ?? 'Product #'.$item->product_id }}</div>
                                <div class="text-xs text-slate-500">
                                    Ordered {{ $item->ordered_quantity }}, received {{ $item->received_quantity }}, outstanding {{ $item->outstandingQuantity() }}
                                </div>
                            </div>
                            <div class="md:col-span-3">
                                <x-input-label for="receive-qty-{{ $item->id }}" value="Receive quantity" />
                                <x-text-input
                                    wire:model="receiveQuantities.{{ $item->id }}"
                                    id="receive-qty-{{ $item->id }}"
                                    type="number"
                                    min="0"
                                    max="{{ $item->outstandingQuantity() }}"
                                    step="1"
                                    placeholder="0"
                                    @disabled($item->outstandingQuantity() === 0)
                                />
                                <x-input-error :messages="$errors->get('receiveQuantities.'.$item->id)" />
                            </div>
                            <div class="md:col-span-4 text-sm text-slate-500">
                                Max outstanding: {{ $item->outstandingQuantity() }}
                            </div>
                        </div>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('receiveQuantities')" />

                <div class="mt-4">
                    <x-input-label for="receiveNotes" value="Receipt notes" />
                    <x-textarea-input wire:model="receiveNotes" id="receiveNotes" rows="2" placeholder="Optional notes for this delivery" />
                    <x-input-error :messages="$errors->get('receiveNotes')" />
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    <x-primary-button type="button" class="w-auto" wire:click="reviewReceive" wire:loading.attr="disabled">
                        Review receipt
                    </x-primary-button>
                    <x-secondary-button type="button" wire:click="cancelReceive">Cancel</x-secondary-button>
                </div>
            @else
                <h2 class="text-lg font-semibold text-slate-900">Confirm receipt</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Review the quantities below, then confirm. This receipt key will be reused if the submit is retried.
                </p>
                <p class="mt-2 text-xs text-slate-400">Receipt key: {{ $receiptKey }}</p>

                <ul class="mt-4 divide-y divide-slate-100 rounded-lg border border-slate-200">
                    @foreach ($receiptPreview as $preview)
                        <li class="flex items-center justify-between px-4 py-3 text-sm">
                            <span class="font-medium text-slate-900">{{ $preview['product'] }}</span>
                            <span class="text-slate-600">Receive {{ $preview['quantity'] }} (of {{ $preview['outstanding'] }} outstanding)</span>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-6 flex flex-wrap gap-3">
                    <x-primary-button type="button" class="w-auto" wire:click="submitReceive" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="submitReceive">Confirm receive</span>
                        <span wire:loading wire:target="submitReceive">Receiving…</span>
                    </x-primary-button>
                    <x-secondary-button type="button" wire:click="backToReceiveForm">Back</x-secondary-button>
                </div>
            @endif
        </div>
    @endif

    @php
        $statusClasses = match ($order->status) {
            \App\Enums\PurchaseOrderStatus::Draft => 'bg-slate-100 text-slate-700',
            \App\Enums\PurchaseOrderStatus::Ordered => 'bg-sky-50 text-sky-800',
            \App\Enums\PurchaseOrderStatus::PartiallyReceived => 'bg-amber-50 text-amber-800',
            \App\Enums\PurchaseOrderStatus::Received => 'bg-brand-50 text-brand-800',
            \App\Enums\PurchaseOrderStatus::Cancelled => 'bg-red-50 text-red-700',
        };
        $orderedQty = (int) $order->items->sum('ordered_quantity');
        $receivedQty = (int) $order->items->sum('received_quantity');
    @endphp

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Status</dt>
                <dd class="mt-1">
                    <span class="inline-flex rounded-md px-2 py-1 text-[11px] font-medium {{ $statusClasses }}">
                        {{ $order->status->label() }}
                    </span>
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Order date</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $display->date($order->order_date) }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Expected delivery</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $order->expected_delivery_date ? $display->date($order->expected_delivery_date) : '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Created by</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $order->creator?->name ?? 'Unavailable' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Receiving progress</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $receivedQty }} / {{ $orderedQty }} units</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Grand total</dt>
                <dd class="mt-1 text-sm font-medium text-slate-900">{{ $display->money($order->totalAmount()) }}</dd>
            </div>
            <div class="sm:col-span-2 lg:col-span-3">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Notes</dt>
                <dd class="mt-1 text-sm text-slate-700">{{ $order->notes ?: 'None' }}</dd>
            </div>
        </dl>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Ordered items</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Product</th>
                        <th class="px-4 py-3 font-medium">Ordered</th>
                        <th class="px-4 py-3 font-medium">Received</th>
                        <th class="px-4 py-3 font-medium">Outstanding</th>
                        <th class="px-4 py-3 font-medium">Unit cost</th>
                        <th class="px-4 py-3 font-medium">Line total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($order->items as $item)
                        <tr wire:key="detail-item-{{ $item->id }}">
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $item->product?->name ?? 'Product #'.$item->product_id }}</div>
                                <div class="text-xs text-slate-500">{{ $item->product?->sku }}</div>
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $item->ordered_quantity }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $item->received_quantity }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $item->outstandingQuantity() }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $display->money($item->unit_cost) }}</td>
                            <td class="px-4 py-3 text-slate-900">{{ $display->money($item->lineTotal()) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Receipt history</h2>
            <p class="mt-1 text-sm text-slate-500">Each receipt is linked to Stock In movements for traceability.</p>
        </div>

        @if ($order->receipts->isEmpty())
            <div class="px-6 py-12 text-center text-sm text-slate-500">
                No receipts recorded yet.
            </div>
        @else
            <div class="divide-y divide-slate-100">
                @foreach ($order->receipts->sortByDesc('received_at') as $receipt)
                    <div wire:key="receipt-{{ $receipt->id }}" class="px-5 py-4">
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div class="text-sm font-medium text-slate-900">{{ $receipt->receipt_key }}</div>
                                <div class="text-xs text-slate-500">
                                    {{ $display->dateTime($receipt->received_at) }}
                                    · {{ $receipt->user?->name ?? 'Unavailable' }}
                                </div>
                            </div>
                            @if ($receipt->notes)
                                <div class="text-xs text-slate-500">{{ $receipt->notes }}</div>
                            @endif
                        </div>
                        <ul class="mt-3 space-y-1 text-sm text-slate-700">
                            @foreach ($receipt->items as $receiptItem)
                                <li>
                                    {{ $receiptItem->purchaseOrderItem?->product?->name ?? 'Product' }}:
                                    {{ $receiptItem->quantity }} received
                                    @if ($receiptItem->stock_movement_id)
                                        <span class="text-xs text-slate-500">(Stock movement #{{ $receiptItem->stock_movement_id }})</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
