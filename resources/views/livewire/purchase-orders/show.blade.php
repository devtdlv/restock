<div class="space-y-6">
    <x-page-header
        :title="$order->number"
        :back-href="route('purchase-orders.index')"
        back-label="Back to purchase orders"
    >
        <x-slot:description>
            {{ $order->supplier?->name ?? 'Supplier unavailable' }}
            @if ($order->supplier)
                <span class="text-slate-400">({{ $order->supplier->code }})</span>
            @endif
        </x-slot:description>
        <x-slot:actions>
            @if ($canEdit)
                <a
                    href="{{ route('purchase-orders.index') }}?edit={{ $order->id }}"
                    class="inline-flex w-full min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 sm:w-auto"
                    wire:navigate
                >
                    Edit draft
                </a>
            @endif
            @if ($canMarkOrdered)
                <x-primary-button type="button" class="w-full sm:w-auto" wire:click="confirmMarkOrdered">
                    Mark as Ordered
                </x-primary-button>
            @endif
            @if ($canReceive)
                <x-primary-button type="button" class="w-full sm:w-auto" wire:click="openReceiveForm">
                    Receive Inventory
                </x-primary-button>
            @endif
            @if ($canCancel)
                <x-danger-button type="button" class="w-full sm:w-auto" wire:click="confirmCancelOrder">
                    Cancel order
                </x-danger-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($statusMessage)
        <x-notice type="status">{{ $statusMessage }}</x-notice>
    @endif

    @if ($errorMessage)
        <x-notice type="error">{{ $errorMessage }}</x-notice>
    @endif

    @if ($confirmingMarkOrdered)
        <x-modal
            title="Mark as Ordered?"
            variant="info"
            close-action="cancelMarkOrdered"
            close-on-escape
            close-on-backdrop
        >
            <p>
                This locks supplier, products, quantities, and unit costs. Inventory will not change until you receive goods.
            </p>

            <x-slot:footer>
                <x-secondary-button type="button" class="w-full sm:w-auto" wire:click="cancelMarkOrdered">Keep as draft</x-secondary-button>
                <x-primary-button type="button" class="w-full sm:w-auto" wire:click="markOrdered" wire:loading.attr="disabled">
                    Confirm submit
                </x-primary-button>
            </x-slot:footer>
        </x-modal>
    @endif

    @if ($confirmingCancel)
        <x-modal
            title="Cancel purchase order?"
            variant="danger"
            close-action="cancelCancelOrder"
            close-on-escape
            close-on-backdrop
        >
            <p class="text-red-700">
                Cancelled orders cannot receive inventory. Orders with received quantity cannot be cancelled.
            </p>

            <x-slot:footer>
                <x-secondary-button type="button" class="w-full sm:w-auto" wire:click="cancelCancelOrder">Keep order</x-secondary-button>
                <x-danger-button type="button" class="w-full sm:w-auto" wire:click="cancelOrder" wire:loading.attr="disabled">
                    Confirm cancel
                </x-danger-button>
            </x-slot:footer>
        </x-modal>
    @endif

    @if ($showReceiveForm)
        <x-form-panel
            :title="$confirmingReceive ? 'Confirm receipt' : 'Receive inventory'"
            :subtitle="$confirmingReceive
                ? 'Review the quantities below, then confirm. This receipt key will be reused if the submit is retried.'
                : 'Enter quantities to receive. Leave blank or zero for lines you are not receiving now.'"
            title-id="purchase-order-receive-title"
        >
            <p class="mb-5 font-mono text-xs text-slate-400">Receipt key: {{ $receiptKey }}</p>

            @if (! $confirmingReceive)
                @if ($errors->any())
                    <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                        Please correct the highlighted fields below.
                    </div>
                @endif

                <div class="space-y-3">
                    @foreach ($order->items as $item)
                        @php
                            $outstanding = $item->outstandingQuantity();
                            $receiveValue = $receiveQuantities[$item->id] ?? '';
                            $receiveInt = is_numeric($receiveValue) ? (int) $receiveValue : 0;
                        @endphp
                        <div wire:key="receive-item-{{ $item->id }}" class="grid gap-3 rounded-lg border border-slate-200 bg-slate-50/40 p-3 md:grid-cols-12 md:items-end">
                            <div class="md:col-span-5">
                                <div class="flex items-center gap-3">
                                    <x-product-image :product="$item->product" size="sm" :alt="$item->product?->name ?? 'Product'" />
                                    <div>
                                        <div class="text-sm font-medium text-slate-900">{{ $item->product?->name ?? 'Product #'.$item->product_id }}</div>
                                        <div class="text-xs text-slate-500">{{ $item->product?->sku }}</div>
                                        <div class="mt-1 text-xs text-slate-500">
                                            Ordered {{ $display->quantity($item->ordered_quantity) }}
                                            · received {{ $display->quantity($item->received_quantity) }}
                                            · outstanding {{ $display->quantity($outstanding) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="md:col-span-3">
                                <x-input-label for="receive-qty-{{ $item->id }}" value="Receive quantity" />
                                <x-text-input
                                    wire:model.live="receiveQuantities.{{ $item->id }}"
                                    id="receive-qty-{{ $item->id }}"
                                    type="number"
                                    min="0"
                                    max="{{ $outstanding }}"
                                    step="1"
                                    placeholder="0"
                                    @disabled($outstanding === 0)
                                />
                                <x-input-error :messages="$errors->get('receiveQuantities.'.$item->id)" />
                            </div>
                            <div class="md:col-span-4 text-sm text-slate-600">
                                <p>Max outstanding: <span class="font-medium tabular-nums">{{ $display->quantity($outstanding) }}</span></p>
                                @if ($receiveInt > 0)
                                    <p class="mt-1 text-xs text-slate-500">
                                        After this receipt: {{ $display->quantity($item->received_quantity + $receiveInt) }} received,
                                        {{ $display->quantity(max($outstanding - $receiveInt, 0)) }} still outstanding
                                    </p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('receiveQuantities')" />

                <div class="mt-5">
                    <x-input-label for="receiveNotes" value="Receipt notes" :optional="true" />
                    <x-textarea-input wire:model="receiveNotes" id="receiveNotes" rows="2" placeholder="Optional notes for this delivery" />
                    <x-input-error :messages="$errors->get('receiveNotes')" />
                </div>

                <x-form-actions class="mt-6 border-t-0 pt-0">
                    <x-primary-button type="button" class="w-full sm:w-auto" wire:click="reviewReceive" wire:loading.attr="disabled" wire:target="reviewReceive">
                        Review receipt
                    </x-primary-button>
                    <x-secondary-button type="button" class="w-full sm:w-auto" wire:click="cancelReceive">Cancel</x-secondary-button>
                </x-form-actions>
            @else
                <ul class="divide-y divide-slate-100 rounded-lg border border-slate-200">
                    @foreach ($receiptPreview as $preview)
                        <li class="flex flex-col gap-1 px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                            <span class="font-medium text-slate-900">{{ $preview['product'] }}</span>
                            <span class="tabular-nums text-slate-600">
                                Receive {{ $display->quantity($preview['quantity']) }}
                                (of {{ $display->quantity($preview['outstanding']) }} outstanding)
                            </span>
                        </li>
                    @endforeach
                </ul>

                <x-form-actions class="mt-6">
                    <x-primary-button type="button" class="w-full sm:w-auto" wire:click="submitReceive" wire:loading.attr="disabled" wire:target="submitReceive">
                        <span wire:loading.remove wire:target="submitReceive">Confirm receive</span>
                        <span wire:loading wire:target="submitReceive">Receiving…</span>
                    </x-primary-button>
                    <x-secondary-button type="button" class="w-full sm:w-auto" wire:click="backToReceiveForm">Back</x-secondary-button>
                </x-form-actions>
            @endif
        </x-form-panel>
    @endif

    @php
        $orderedQty = (int) $order->items->sum('ordered_quantity');
        $receivedQty = (int) $order->items->sum('received_quantity');
    @endphp

    <x-panel class="sm:p-6">
        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Status</dt>
                <dd class="mt-1">
                    <x-purchase-order-status :status="$order->status" />
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Order date</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $display->date($order->order_date) }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Expected delivery</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $order->expected_delivery_date ? $display->date($order->expected_delivery_date) : '-' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Created by</dt>
                <dd class="mt-1 text-sm text-slate-900">{{ $order->creator?->name ?? 'Unavailable' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Receiving progress</dt>
                <dd class="mt-1 text-sm tabular-nums text-slate-900">{{ $display->quantity($receivedQty) }} / {{ $display->quantity($orderedQty) }} units</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Grand total</dt>
                <dd class="mt-1 text-sm font-medium tabular-nums text-slate-900">{{ $display->money($order->totalAmount()) }}</dd>
            </div>
            <div class="sm:col-span-2 lg:col-span-3">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Notes</dt>
                <dd class="mt-1 text-sm text-slate-700">{{ $order->notes ?: 'None' }}</dd>
            </div>
        </dl>
    </x-panel>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Ordered items</h2>
        </div>
        <x-table caption="Ordered items for {{ $order->number }}">
            <x-table.header>
                <tr>
                    <th scope="col" class="px-4 py-3">Product</th>
                    <th scope="col" class="px-4 py-3 text-right">Ordered</th>
                    <th scope="col" class="px-4 py-3 text-right">Received</th>
                    <th scope="col" class="hidden px-4 py-3 text-right sm:table-cell">Outstanding</th>
                    <th scope="col" class="px-4 py-3 text-right">Unit cost</th>
                    <th scope="col" class="hidden px-4 py-3 text-right md:table-cell">Line total</th>
                </tr>
            </x-table.header>
            <x-table.body>
                @foreach ($order->items as $item)
                    <tr wire:key="detail-item-{{ $item->id }}" class="hover:bg-slate-50/80">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <x-product-image :product="$item->product" size="sm" :alt="$item->product?->name ?? 'Product'" />
                                <div>
                                    <div class="font-medium text-slate-900">{{ $item->product?->name ?? 'Product #'.$item->product_id }}</div>
                                    <div class="text-xs text-slate-500">{{ $item->product?->sku }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-slate-700">{{ $display->quantity($item->ordered_quantity) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-slate-700">{{ $display->quantity($item->received_quantity) }}</td>
                        <td class="hidden px-4 py-3 text-right tabular-nums text-slate-700 sm:table-cell">{{ $display->quantity($item->outstandingQuantity()) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-slate-700">{{ $display->money($item->unit_cost) }}</td>
                        <td class="hidden px-4 py-3 text-right tabular-nums text-slate-900 md:table-cell">{{ $display->money($item->lineTotal()) }}</td>
                    </tr>
                @endforeach
            </x-table.body>
        </x-table>
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
