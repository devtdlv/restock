<div class="space-y-6">
    <x-page-header title="Stock Movements">
        <x-slot:description>
            Traceable inventory history for stock in, stock out, adjustments, and legacy opening balances.
            Catalog create/update/delete history is available under
            <a href="{{ route('audit-logs.index') }}" class="font-medium text-brand-700 hover:text-brand-800" wire:navigate>Audit Logs</a>.
        </x-slot:description>
    </x-page-header>

    <x-panel>
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <x-input-label for="movement-search" value="Search" />
                <x-text-input
                    wire:model.live.debounce.300ms="search"
                    id="movement-search"
                    type="search"
                    placeholder="Search by product name or SKU…"
                />
            </div>
            <div>
                <x-input-label for="movement-type" value="Movement type" />
                <x-select-input wire:model.live="type" id="movement-type">
                    <option value="">All movement types</option>
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
                :title="($search !== '' || $type !== '') ? 'No movements match your filters' : 'No stock movements yet'"
                :description="($search !== '' || $type !== '') ? 'Try a different search or clear the type filter.' : 'Use Adjust Stock on the Products page to record inventory changes.'"
            />
        @else
            <x-table caption="Stock movement history">
                <x-table.header>
                    <tr>
                        <th scope="col" class="px-4 py-3">Date</th>
                        <th scope="col" class="px-4 py-3">Product</th>
                        <th scope="col" class="px-4 py-3">Type</th>
                        <th scope="col" class="px-4 py-3 text-right">Change</th>
                        <th scope="col" class="hidden px-4 py-3 text-right md:table-cell">Before</th>
                        <th scope="col" class="hidden px-4 py-3 text-right md:table-cell">After</th>
                        <th scope="col" class="hidden px-4 py-3 lg:table-cell">Reason</th>
                        <th scope="col" class="hidden px-4 py-3 sm:table-cell">User</th>
                    </tr>
                </x-table.header>
                <x-table.body>
                    @foreach ($movements as $movement)
                        <tr wire:key="movement-{{ $movement->id }}" class="hover:bg-slate-50/80">
                            <td class="whitespace-nowrap px-4 py-3 text-slate-600">
                                {{ $display->dateTime($movement->created_at) }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <x-product-image :product="$movement->product" size="xs" :alt="$movement->product?->name ?? 'Product'" />
                                    <div>
                                        <div class="font-medium text-slate-900">{{ $movement->product?->name ?? 'Deleted product' }}</div>
                                        <div class="font-mono text-xs text-slate-500">{{ $movement->product?->sku ?? '-' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <x-badge :variant="$movement->type->badgeVariant()">{{ $movement->type->label() }}</x-badge>
                            </td>
                            <td @class([
                                'px-4 py-3 text-right font-medium tabular-nums',
                                'text-brand-700' => $movement->quantity_change > 0,
                                'text-red-600' => $movement->quantity_change < 0,
                            ])>
                                {{ $display->quantityChange((int) $movement->quantity_change) }}
                            </td>
                            <td class="hidden px-4 py-3 text-right tabular-nums text-slate-700 md:table-cell">{{ $display->quantity($movement->quantity_before) }}</td>
                            <td class="hidden px-4 py-3 text-right tabular-nums text-slate-700 md:table-cell">{{ $display->quantity($movement->quantity_after) }}</td>
                            <td class="hidden max-w-xs px-4 py-3 text-slate-600 lg:table-cell">
                                {{ $movement->reason ?: '-' }}
                            </td>
                            <td class="hidden px-4 py-3 text-slate-600 sm:table-cell">
                                {{ $movement->user?->name ?? 'System' }}
                            </td>
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
