<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Purchase Orders</h1>
            <p class="mt-1 text-sm text-slate-500">Create draft orders, submit them, and track receiving progress.</p>
        </div>
        <x-primary-button type="button" class="w-auto" wire:click="create">
            New purchase order
        </x-primary-button>
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

    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="grid gap-4 md:grid-cols-3">
            <div class="md:col-span-2">
                <label for="po-search" class="sr-only">Search purchase orders</label>
                <x-text-input
                    wire:model.live.debounce.300ms="search"
                    id="po-search"
                    type="search"
                    placeholder="Search by PO number or supplier…"
                />
            </div>
            <div>
                <label for="po-status" class="sr-only">Filter by status</label>
                <select
                    wire:model.live="status"
                    id="po-status"
                    class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                >
                    <option value="">All statuses</option>
                    @foreach ($statuses as $statusOption)
                        <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    @if ($showForm)
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-lg font-semibold text-slate-900">
                {{ $purchaseOrderId ? 'Edit draft purchase order' : 'Create draft purchase order' }}
            </h2>
            <p class="mt-1 text-sm text-slate-500">
                Drafts do not affect inventory. Add at least one product line, then save.
            </p>

            <form wire:submit="save" class="mt-6 space-y-6">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <x-input-label for="supplier_id" value="Supplier" />
                        <select
                            wire:model="supplier_id"
                            id="supplier_id"
                            class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                            required
                        >
                            <option value="">Select supplier…</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">
                                    {{ $supplier->name }} ({{ $supplier->code }})
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('supplier_id')" />
                    </div>

                    <div>
                        <x-input-label for="order_date" value="Order date" />
                        <x-text-input wire:model="order_date" id="order_date" type="date" required />
                        <x-input-error :messages="$errors->get('order_date')" />
                    </div>

                    <div>
                        <x-input-label for="expected_delivery_date" value="Expected delivery date" />
                        <x-text-input wire:model="expected_delivery_date" id="expected_delivery_date" type="date" />
                        <x-input-error :messages="$errors->get('expected_delivery_date')" />
                    </div>

                    <div class="md:col-span-2">
                        <x-input-label for="notes" value="Notes" />
                        <x-textarea-input wire:model="notes" id="notes" rows="2" placeholder="Optional notes" />
                        <x-input-error :messages="$errors->get('notes')" />
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold text-slate-900">Line items</h3>
                        <x-secondary-button type="button" wire:click="addLine">Add line</x-secondary-button>
                    </div>
                    <x-input-error :messages="$errors->get('lines')" />

                    <div class="mt-3 space-y-3">
                        @foreach ($lines as $index => $line)
                            <div wire:key="po-line-{{ $index }}" class="grid gap-3 rounded-lg border border-slate-200 p-3 md:grid-cols-12">
                                <div class="md:col-span-5">
                                    <x-input-label for="line-product-{{ $index }}" value="Product" />
                                    <select
                                        wire:model.live="lines.{{ $index }}.product_id"
                                        id="line-product-{{ $index }}"
                                        class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                        required
                                    >
                                        <option value="">Select product…</option>
                                        @foreach ($products as $product)
                                            <option value="{{ $product->id }}">
                                                {{ $product->name }} ({{ $product->sku }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('lines.'.$index.'.product_id')" />
                                </div>
                                <div class="md:col-span-2">
                                    <x-input-label for="line-qty-{{ $index }}" value="Quantity" />
                                    <x-text-input wire:model.live="lines.{{ $index }}.ordered_quantity" id="line-qty-{{ $index }}" type="number" min="1" step="1" required />
                                    <x-input-error :messages="$errors->get('lines.'.$index.'.ordered_quantity')" />
                                </div>
                                <div class="md:col-span-3">
                                    <x-input-label for="line-cost-{{ $index }}" value="Unit cost" />
                                    <x-text-input wire:model.live="lines.{{ $index }}.unit_cost" id="line-cost-{{ $index }}" type="text" inputmode="decimal" placeholder="0.00" required />
                                    <x-input-error :messages="$errors->get('lines.'.$index.'.unit_cost')" />
                                </div>
                                <div class="flex items-end md:col-span-2">
                                    <x-secondary-button type="button" class="w-full" wire:click="removeLine({{ $index }})">
                                        Remove
                                    </x-secondary-button>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 flex justify-end text-sm">
                        <div class="rounded-lg bg-slate-50 px-4 py-2 font-medium text-slate-800">
                            Grand total: {{ $formGrandTotal }}
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <x-primary-button class="w-auto" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="save">{{ $purchaseOrderId ? 'Save draft' : 'Create draft' }}</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </x-primary-button>
                    <x-secondary-button type="button" wire:click="cancelForm">Cancel</x-secondary-button>
                </div>
            </form>
        </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        @if ($orders->isEmpty())
            <div class="px-6 py-16 text-center">
                <p class="text-base font-semibold text-slate-900">
                    {{ ($search !== '' || $status !== '') ? 'No purchase orders match your filters' : 'No purchase orders yet' }}
                </p>
                <p class="mt-2 text-sm text-slate-500">
                    {{ ($search !== '' || $status !== '') ? 'Try a different search or clear the status filter.' : 'Create a draft purchase order to start purchasing.' }}
                </p>
                @if ($search === '' && $status === '')
                    <div class="mt-6">
                        <x-primary-button type="button" class="w-auto" wire:click="create">
                            Create purchase order
                        </x-primary-button>
                    </div>
                @endif
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">PO</th>
                            <th class="px-4 py-3 font-medium">Supplier</th>
                            <th class="px-4 py-3 font-medium">Order date</th>
                            <th class="px-4 py-3 font-medium">Expected</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Total</th>
                            <th class="px-4 py-3 font-medium">Progress</th>
                            <th class="px-4 py-3 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($orders as $order)
                            @php
                                $orderedQty = (int) $order->items->sum('ordered_quantity');
                                $receivedQty = (int) $order->items->sum('received_quantity');
                                $statusClasses = match ($order->status) {
                                    \App\Enums\PurchaseOrderStatus::Draft => 'bg-slate-100 text-slate-700',
                                    \App\Enums\PurchaseOrderStatus::Ordered => 'bg-sky-50 text-sky-800',
                                    \App\Enums\PurchaseOrderStatus::PartiallyReceived => 'bg-amber-50 text-amber-800',
                                    \App\Enums\PurchaseOrderStatus::Received => 'bg-brand-50 text-brand-800',
                                    \App\Enums\PurchaseOrderStatus::Cancelled => 'bg-red-50 text-red-700',
                                };
                            @endphp
                            <tr wire:key="po-{{ $order->id }}" class="hover:bg-slate-50/80">
                                <td class="px-4 py-3 font-medium text-slate-900">{{ $order->number }}</td>
                                <td class="px-4 py-3 text-slate-700">
                                    <div>{{ $order->supplier?->name ?? 'Unavailable' }}</div>
                                    @if ($order->supplier)
                                        <div class="text-xs text-slate-500">{{ $order->supplier->code }}</div>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-600">
                                    {{ $order->order_date?->format('Y-m-d') }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-600">
                                    {{ $order->expected_delivery_date?->format('Y-m-d') ?: '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-md px-2 py-1 text-[11px] font-medium {{ $statusClasses }}">
                                        {{ $order->status->label() }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-700">
                                    {{ $order->totalAmount() }}
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    {{ $receivedQty }} / {{ $orderedQty }}
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
</div>
