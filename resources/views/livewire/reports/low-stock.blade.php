<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <a href="{{ route('reports.index') }}" class="text-sm font-medium text-brand-700 hover:text-brand-800" wire:navigate>
                ← Back to reports
            </a>
            <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900">Low Stock Report</h1>
            <p class="mt-1 text-sm text-slate-500">
                Products where quantity on hand is at or below reorder level (includes out-of-stock).
                Suggested replenishment is <span class="font-medium text-slate-700">max(0, reorder level − quantity on hand)</span>,
                a shortage gap only, not a purchasing recommendation.
            </p>
        </div>
        <x-secondary-button type="button" class="w-auto" wire:click="export" wire:loading.attr="disabled">
            Export CSV
        </x-secondary-button>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="grid gap-4 md:grid-cols-3">
            <div class="md:col-span-2">
                <label for="low-stock-search" class="sr-only">Search products</label>
                <x-text-input
                    wire:model.live.debounce.300ms="search"
                    id="low-stock-search"
                    type="search"
                    placeholder="Search by name or SKU…"
                />
            </div>
            <div>
                <label for="low-stock-status" class="sr-only">Status filter</label>
                <select
                    wire:model.live="status"
                    id="low-stock-status"
                    class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                >
                    <option value="active">Active only</option>
                    <option value="inactive">Inactive only</option>
                    <option value="all">All statuses</option>
                </select>
            </div>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        @if ($products->isEmpty())
            <div class="px-6 py-16 text-center">
                <p class="text-base font-semibold text-slate-900">No low-stock products match</p>
                <p class="mt-2 text-sm text-slate-500">Try clearing search or changing the status filter.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">Product</th>
                            <th class="px-4 py-3 font-medium">SKU</th>
                            <th class="px-4 py-3 font-medium">On hand</th>
                            <th class="px-4 py-3 font-medium">Reorder level</th>
                            <th class="px-4 py-3 font-medium">Suggested replenishment</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($products as $product)
                            <tr wire:key="low-stock-{{ $product->id }}" class="hover:bg-slate-50/80">
                                <td class="px-4 py-3 font-medium text-slate-900">{{ $product->name }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $product->sku }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $product->quantity_on_hand }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $product->reorder_level }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $inventoryReports->suggestedReplenishment($product) }}</td>
                                <td class="px-4 py-3">
                                    <x-status-badge :active="$product->is_active" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($products->hasPages())
                <div class="border-t border-slate-200 px-4 py-3">
                    {{ $products->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
