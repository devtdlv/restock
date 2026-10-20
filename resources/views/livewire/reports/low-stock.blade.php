<div class="space-y-6">
    <x-page-header
        title="Low Stock Report"
        subtitle="Products where quantity on hand is at or below reorder level (includes out-of-stock). Suggested replenishment is max(0, reorder level − quantity on hand), a shortage gap only, not a purchasing recommendation."
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
        <div class="grid gap-4 md:grid-cols-3">
            <div class="md:col-span-2">
                <x-input-label for="low-stock-search" value="Search" />
                <x-text-input
                    wire:model.live.debounce.300ms="search"
                    id="low-stock-search"
                    type="search"
                    placeholder="Search by name or SKU…"
                />
            </div>
            <div>
                <x-input-label for="low-stock-status" value="Status" />
                <x-select-input wire:model.live="status" id="low-stock-status">
                    <option value="active">Active only</option>
                    <option value="inactive">Inactive only</option>
                    <option value="all">All statuses</option>
                </x-select-input>
            </div>
        </div>
    </x-panel>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        @if ($products->isEmpty())
            <x-empty-state
                title="No low-stock products match"
                description="Try clearing search or changing the status filter."
            />
        @else
            <x-table caption="Low stock products report">
                <x-table.header>
                    <tr>
                        <th scope="col" class="px-4 py-3">Product</th>
                        <th scope="col" class="hidden px-4 py-3 sm:table-cell">SKU</th>
                        <th scope="col" class="px-4 py-3 text-right">On hand</th>
                        <th scope="col" class="hidden px-4 py-3 text-right md:table-cell">Reorder level</th>
                        <th scope="col" class="px-4 py-3 text-right">Suggested</th>
                        <th scope="col" class="px-4 py-3">Stock</th>
                        <th scope="col" class="hidden px-4 py-3 lg:table-cell">Active</th>
                    </tr>
                </x-table.header>
                <x-table.body>
                    @foreach ($products as $product)
                        <tr wire:key="low-stock-{{ $product->id }}" class="hover:bg-slate-50/80">
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $product->name }}</div>
                                <div class="font-mono text-xs text-slate-500 sm:hidden">{{ $product->sku }}</div>
                            </td>
                            <td class="hidden px-4 py-3 font-mono text-xs text-slate-600 sm:table-cell">{{ $product->sku }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-700">{{ $display->quantity($product->quantity_on_hand) }}</td>
                            <td class="hidden px-4 py-3 text-right tabular-nums text-slate-700 md:table-cell">{{ $display->quantity($product->reorder_level) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-700">{{ $display->quantity($inventoryReports->suggestedReplenishment($product)) }}</td>
                            <td class="px-4 py-3">
                                <x-stock-level-badge :product="$product" />
                            </td>
                            <td class="hidden px-4 py-3 lg:table-cell">
                                <x-status-badge :active="$product->is_active" />
                            </td>
                        </tr>
                    @endforeach
                </x-table.body>
            </x-table>
            @if ($products->hasPages())
                <div class="border-t border-slate-200 px-4 py-3">
                    {{ $products->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
