<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <a href="{{ route('reports.index') }}" class="text-sm font-medium text-brand-700 hover:text-brand-800" wire:navigate>
                ← Back to reports
            </a>
            <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900">Stock Movement Report</h1>
            <p class="mt-1 text-sm text-slate-500">
                Exact recorded movement history. Includes stock in, stock out, adjustments, and legacy opening balances.
            </p>
        </div>
        <x-secondary-button type="button" class="w-auto" wire:click="export" wire:loading.attr="disabled">
            Export CSV
        </x-secondary-button>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
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
                <select
                    wire:model.live="productId"
                    id="movement-product"
                    class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                >
                    <option value="">All products</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="movement-type" value="Movement type" />
                <select
                    wire:model.live="type"
                    id="movement-type"
                    class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                >
                    <option value="">All types</option>
                    @foreach ($movementTypes as $movementType)
                        <option value="{{ $movementType->value }}">{{ $movementType->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        @if ($movements->isEmpty())
            <div class="px-6 py-16 text-center">
                <p class="text-base font-semibold text-slate-900">No stock movements match</p>
                <p class="mt-2 text-sm text-slate-500">Adjust the date range, product, or type filters.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">Timestamp</th>
                            <th class="px-4 py-3 font-medium">Product</th>
                            <th class="px-4 py-3 font-medium">Type</th>
                            <th class="px-4 py-3 font-medium">Qty</th>
                            <th class="px-4 py-3 font-medium">Before</th>
                            <th class="px-4 py-3 font-medium">After</th>
                            <th class="px-4 py-3 font-medium">Reason</th>
                            <th class="px-4 py-3 font-medium">User</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($movements as $movement)
                            <tr wire:key="report-movement-{{ $movement->id }}" class="hover:bg-slate-50/80">
                                <td class="whitespace-nowrap px-4 py-3 text-slate-600">
                                    {{ $movement->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-900">{{ $movement->product?->name ?? 'Unavailable' }}</div>
                                    <div class="font-mono text-xs text-slate-500">{{ $movement->product?->sku }}</div>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ $movement->type?->label() }}</td>
                                <td class="px-4 py-3 font-medium text-slate-900">
                                    {{ $movement->quantity_change > 0 ? '+'.$movement->quantity_change : $movement->quantity_change }}
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $movement->quantity_before }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $movement->quantity_after }}</td>
                                <td class="max-w-xs px-4 py-3 text-slate-600">{{ $movement->reason ?: '—' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $movement->user?->name ?? 'System / unavailable' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($movements->hasPages())
                <div class="border-t border-slate-200 px-4 py-3">
                    {{ $movements->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
