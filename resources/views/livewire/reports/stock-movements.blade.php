<div class="space-y-6">
    <x-page-header
        title="Stock Movement Report"
        subtitle="Exact recorded movement history. Includes stock in, stock out, adjustments, and legacy opening balances."
        :back-href="route('reports.index')"
        back-label="Back to reports"
    >
        <x-slot:actions>
            <x-secondary-button type="button" class="w-full sm:w-auto" wire:click="export" wire:loading.attr="disabled" wire:target="export">
                <span wire:loading.remove wire:target="export">Export CSV</span>
                <span wire:loading wire:target="export">Exporting…</span>
            </x-secondary-button>
        </x-slot:actions>
    </x-page-header>

    <x-panel>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div>
                <x-input-label for="movement-date-from" value="From date" />
                <x-text-input wire:model.live="dateFrom" id="movement-date-from" type="date" />
            </div>
            <div>
                <x-input-label for="movement-date-to" value="To date" />
                <x-text-input wire:model.live="dateTo" id="movement-date-to" type="date" />
            </div>
            <div>
                <x-input-label for="movement-product" value="Product" />
                <x-select-input wire:model.live="productId" id="movement-product">
                    <option value="">All products</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>
                    @endforeach
                </x-select-input>
            </div>
            <div>
                <x-input-label for="movement-type" value="Movement type" />
                <x-select-input wire:model.live="type" id="movement-type">
                    <option value="">All types</option>
                    @foreach ($movementTypes as $movementType)
                        <option value="{{ $movementType->value }}">{{ $movementType->label() }}</option>
                    @endforeach
                </x-select-input>
            </div>
        </div>
    </x-panel>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        @if ($movements->isEmpty())
            <x-empty-state
                title="No stock movements match"
                description="Adjust the date range, product, or type filters."
            />
        @else
            <x-table caption="Stock movement report">
                <x-table.header>
                    <tr>
                        <th scope="col" class="px-4 py-3">Timestamp</th>
                        <th scope="col" class="px-4 py-3">Product</th>
                        <th scope="col" class="px-4 py-3">Type</th>
                        <th scope="col" class="px-4 py-3 text-right">Qty</th>
                        <th scope="col" class="hidden px-4 py-3 text-right md:table-cell">Before</th>
                        <th scope="col" class="hidden px-4 py-3 text-right md:table-cell">After</th>
                        <th scope="col" class="hidden px-4 py-3 lg:table-cell">Reason</th>
                        <th scope="col" class="hidden px-4 py-3 sm:table-cell">User</th>
                    </tr>
                </x-table.header>
                <x-table.body>
                    @foreach ($movements as $movement)
                        <tr wire:key="report-movement-{{ $movement->id }}" class="hover:bg-slate-50/80">
                            <td class="whitespace-nowrap px-4 py-3 tabular-nums text-slate-600">
                                {{ $display->dateTime($movement->created_at) }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $movement->product?->name ?? 'Unavailable' }}</div>
                                <div class="font-mono text-xs text-slate-500">{{ $movement->product?->sku }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <x-badge :variant="$movement->type?->badgeVariant() ?? 'neutral'">{{ $movement->type?->label() }}</x-badge>
                            </td>
                            <td @class([
                                'px-4 py-3 text-right font-medium tabular-nums',
                                'text-brand-700' => $movement->quantity_change > 0,
                                'text-red-600' => $movement->quantity_change < 0,
                            ])>
                                {{ $display->quantityChange((int) $movement->quantity_change) }}
                            </td>
                            <td class="hidden px-4 py-3 text-right tabular-nums text-slate-600 md:table-cell">{{ $display->quantity($movement->quantity_before) }}</td>
                            <td class="hidden px-4 py-3 text-right tabular-nums text-slate-600 md:table-cell">{{ $display->quantity($movement->quantity_after) }}</td>
                            <td class="hidden max-w-xs px-4 py-3 text-slate-600 lg:table-cell">{{ $movement->reason ?: '-' }}</td>
                            <td class="hidden px-4 py-3 text-slate-600 sm:table-cell">{{ $movement->user?->name ?? 'System / unavailable' }}</td>
                        </tr>
                    @endforeach
                </x-table.body>
            </x-table>
            @if ($movements->hasPages())
                <div class="border-t border-slate-200 px-4 py-3">
                    {{ $movements->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
