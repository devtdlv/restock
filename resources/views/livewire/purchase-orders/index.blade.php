<div class="space-y-6">
    <x-page-header
        title="Purchase Orders"
        subtitle="Create draft orders, submit them, and track receiving progress."
    >
        <x-slot:actions>
            <x-primary-button type="button" class="w-auto" wire:click="create">
                New purchase order
            </x-primary-button>
        </x-slot:actions>
    </x-page-header>

    @if ($statusMessage)
        <x-notice type="status">{{ $statusMessage }}</x-notice>
    @endif

    @if ($errorMessage)
        <x-notice type="error">{{ $errorMessage }}</x-notice>
    @endif

    <x-panel>
        <div class="grid gap-4 md:grid-cols-3">
            <div class="md:col-span-2">
                <x-input-label for="po-search" value="Search" />
                <x-text-input
                    wire:model.live.debounce.300ms="search"
                    id="po-search"
                    type="search"
                    placeholder="Search by PO number or supplier…"
                />
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

    @if ($showForm)
        <x-form-panel
            :title="$purchaseOrderId ? 'Edit draft purchase order' : 'Create draft purchase order'"
            subtitle="Drafts do not affect inventory. Add at least one product line, then save."
            title-id="purchase-order-form-title"
        >
            @if ($errors->any())
                <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                    Please correct the highlighted fields below.
                </div>
            @endif

            <form wire:submit="save" class="space-y-6">
                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <x-input-label for="supplier_id" value="Supplier" :required="true" />
                        <x-select-input wire:model="supplier_id" id="supplier_id" required aria-required="true">
                            <option value="">Select supplier…</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">
                                    {{ $supplier->name }} ({{ $supplier->code }})
                                </option>
                            @endforeach
                        </x-select-input>
                        <x-input-error :messages="$errors->get('supplier_id')" />
                    </div>

                    <div>
                        <x-input-label for="order_date" value="Order date" :required="true" />
                        <x-text-input wire:model="order_date" id="order_date" type="date" required aria-required="true" />
                        <x-input-error :messages="$errors->get('order_date')" />
                    </div>

                    <div>
                        <x-input-label for="expected_delivery_date" value="Expected delivery date" :optional="true" />
                        <x-text-input wire:model="expected_delivery_date" id="expected_delivery_date" type="date" />
                        <x-input-error :messages="$errors->get('expected_delivery_date')" />
                    </div>

                    <div class="md:col-span-2">
                        <x-input-label for="notes" value="Notes" :optional="true" />
                        <x-textarea-input wire:model="notes" id="notes" rows="2" placeholder="Optional notes" />
                        <x-input-error :messages="$errors->get('notes')" />
                    </div>
                </div>

                <fieldset class="min-w-0">
                    <legend class="text-sm font-semibold text-slate-900">Line items</legend>
                    <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <x-form-help class="mt-0">Each line needs a product, quantity, and unit cost.</x-form-help>
                        <x-secondary-button type="button" class="w-full sm:w-auto" wire:click="addLine">Add line</x-secondary-button>
                    </div>

                    <x-input-error :messages="$errors->get('lines')" />

                    <div class="mt-3 space-y-3">
                        @foreach ($lines as $index => $line)
                            @php
                                $lineQty = is_numeric($line['ordered_quantity'] ?? null) ? (int) $line['ordered_quantity'] : null;
                                $lineCost = is_numeric($line['unit_cost'] ?? null) ? (float) $line['unit_cost'] : null;
                                $lineTotal = ($lineQty !== null && $lineCost !== null) ? $lineQty * $lineCost : null;
                            @endphp
                            <div wire:key="po-line-{{ $index }}" class="grid gap-3 rounded-lg border border-slate-200 bg-slate-50/40 p-3 md:grid-cols-12">
                                <div class="md:col-span-5">
                                    <x-input-label for="line-product-{{ $index }}" value="Product" :required="true" />
                                    <x-select-input wire:model.live="lines.{{ $index }}.product_id" id="line-product-{{ $index }}" required aria-required="true">
                                        <option value="">Select product…</option>
                                        @foreach ($products as $product)
                                            <option value="{{ $product->id }}">
                                                {{ $product->name }} ({{ $product->sku }})
                                            </option>
                                        @endforeach
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('lines.'.$index.'.product_id')" />
                                </div>
                                <div class="md:col-span-2">
                                    <x-input-label for="line-qty-{{ $index }}" value="Quantity" :required="true" />
                                    <x-text-input wire:model.live="lines.{{ $index }}.ordered_quantity" id="line-qty-{{ $index }}" type="number" min="1" step="1" required aria-required="true" />
                                    <x-input-error :messages="$errors->get('lines.'.$index.'.ordered_quantity')" />
                                </div>
                                <div class="md:col-span-3">
                                    <x-input-label for="line-cost-{{ $index }}" value="Unit cost" :required="true" />
                                    <x-text-input wire:model.live="lines.{{ $index }}.unit_cost" id="line-cost-{{ $index }}" type="text" inputmode="decimal" placeholder="0.00" required aria-required="true" />
                                    @if ($lineTotal !== null)
                                        <x-form-help>Line total: {{ $display->money($lineTotal) }}</x-form-help>
                                    @endif
                                    <x-input-error :messages="$errors->get('lines.'.$index.'.unit_cost')" />
                                </div>
                                <div class="flex items-end md:col-span-2">
                                    <x-secondary-button type="button" class="w-full min-h-11" wire:click="removeLine({{ $index }})">
                                        Remove
                                    </x-secondary-button>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 flex justify-end text-sm">
                        <div class="rounded-lg border border-slate-200 bg-white px-4 py-2.5 font-semibold tabular-nums text-slate-900">
                            Grand total: {{ $display->money($formGrandTotal) }}
                        </div>
                    </div>
                </fieldset>

                <x-form-actions>
                    <x-primary-button class="w-full sm:w-auto" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">{{ $purchaseOrderId ? 'Save draft' : 'Create draft' }}</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </x-primary-button>
                    <x-secondary-button type="button" class="w-full sm:w-auto" wire:click="cancelForm">Cancel</x-secondary-button>
                </x-form-actions>
            </form>
        </x-form-panel>
    @endif

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        @if ($orders->isEmpty())
            <x-empty-state
                :title="($search !== '' || $status !== '') ? 'No purchase orders match your filters' : 'No purchase orders yet'"
                :description="($search !== '' || $status !== '') ? 'Try a different search or clear the status filter.' : 'Create a draft purchase order to start purchasing.'"
            >
                @if ($search === '' && $status === '')
                    <x-slot:actions>
                        <x-primary-button type="button" class="w-auto" wire:click="create">
                            Create purchase order
                        </x-primary-button>
                    </x-slot:actions>
                @endif
            </x-empty-state>
        @else
            <x-table caption="Purchase orders listing">
                <x-table.header>
                    <tr>
                        <th scope="col" class="px-4 py-3">PO</th>
                        <th scope="col" class="px-4 py-3">Supplier</th>
                        <th scope="col" class="hidden px-4 py-3 md:table-cell">Order date</th>
                        <th scope="col" class="hidden px-4 py-3 lg:table-cell">Expected</th>
                        <th scope="col" class="px-4 py-3">Status</th>
                        <th scope="col" class="px-4 py-3 text-right">Total</th>
                        <th scope="col" class="hidden px-4 py-3 sm:table-cell">Progress</th>
                        <th scope="col" class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </x-table.header>
                <x-table.body>
                    @foreach ($orders as $order)
                        @php
                            $orderedQty = (int) $order->items->sum('ordered_quantity');
                            $receivedQty = (int) $order->items->sum('received_quantity');
                        @endphp
                        <tr wire:key="po-{{ $order->id }}" class="hover:bg-slate-50/80">
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $order->number }}</td>
                            <td class="px-4 py-3 text-slate-700">
                                <div>{{ $order->supplier?->name ?? 'Unavailable' }}</div>
                                @if ($order->supplier)
                                    <div class="text-xs text-slate-500">{{ $order->supplier->code }}</div>
                                @endif
                                <div class="mt-1 text-xs text-slate-500 md:hidden">{{ $display->date($order->order_date) }}</div>
                            </td>
                            <td class="hidden whitespace-nowrap px-4 py-3 text-slate-600 md:table-cell">
                                {{ $display->date($order->order_date) }}
                            </td>
                            <td class="hidden whitespace-nowrap px-4 py-3 text-slate-600 lg:table-cell">
                                {{ $order->expected_delivery_date ? $display->date($order->expected_delivery_date) : '-' }}
                            </td>
                            <td class="px-4 py-3">
                                <x-purchase-order-status :status="$order->status" />
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-slate-700">
                                {{ $display->money($order->totalAmount()) }}
                            </td>
                            <td class="hidden px-4 py-3 tabular-nums text-slate-600 sm:table-cell">
                                {{ $display->quantity($receivedQty) }} / {{ $display->quantity($orderedQty) }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <a
                                        href="{{ route('purchase-orders.show', $order) }}"
                                        class="rounded-lg px-2.5 py-1.5 text-sm font-medium text-brand-700 hover:bg-brand-50"
                                        wire:navigate
                                    >
                                        View
                                    </a>
                                    @if ($order->status->isEditable())
                                        <button
                                            type="button"
                                            wire:click="edit({{ $order->id }})"
                                            class="rounded-lg px-2.5 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100"
                                        >
                                            Edit
                                        </button>
                                    @endif
                                </div>
                            </td>
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
</div>
