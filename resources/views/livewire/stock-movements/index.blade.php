<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Stock Movements</h1>
        <p class="mt-1 text-sm text-slate-500">
            Traceable inventory history for stock in, stock out, adjustments, and legacy opening balances.
            Catalog create/update/delete history is available under
            <a href="{{ route('audit-logs.index') }}" class="font-medium text-brand-700 hover:text-brand-800" wire:navigate>Audit Logs</a>.
        </p>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label for="movement-search" class="sr-only">Search movements</label>
                <x-text-input
                    wire:model.live.debounce.300ms="search"
                    id="movement-search"
                    type="search"
                    placeholder="Search by product name or SKU…"
                />
            </div>
            <div>
                <label for="movement-type" class="sr-only">Filter by type</label>
                <select
                    wire:model.live="type"
                    id="movement-type"
                    class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                >
                    <option value="">All movement types</option>
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
                <p class="text-base font-semibold text-slate-900">
                    {{ ($search !== '' || $type !== '') ? 'No movements match your filters' : 'No stock movements yet' }}
                </p>
                <p class="mt-2 text-sm text-slate-500">
                    {{ ($search !== '' || $type !== '') ? 'Try a different search or clear the type filter.' : 'Use Adjust Stock on the Products page to record inventory changes.' }}
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">Date</th>
                            <th class="px-4 py-3 font-medium">Product</th>
                            <th class="px-4 py-3 font-medium">Type</th>
                            <th class="px-4 py-3 font-medium">Change</th>
                            <th class="px-4 py-3 font-medium">Before</th>
                            <th class="px-4 py-3 font-medium">After</th>
                            <th class="px-4 py-3 font-medium">Reason</th>
                            <th class="px-4 py-3 font-medium">User</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($movements as $movement)
                            <tr wire:key="movement-{{ $movement->id }}" class="hover:bg-slate-50/80">
                                <td class="whitespace-nowrap px-4 py-3 text-slate-600">
                                    {{ $display->dateTime($movement->created_at) }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-900">{{ $movement->product?->name ?? 'Deleted product' }}</div>
                                    <div class="font-mono text-xs text-slate-500">{{ $movement->product?->sku ?? '-' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-md bg-slate-100 px-2 py-1 text-[11px] font-medium text-slate-700">
                                        {{ $movement->type->label() }}
                                    </span>
                                </td>
                                <td @class([
                                    'px-4 py-3 font-medium',
                                    'text-brand-700' => $movement->quantity_change > 0,
                                    'text-red-600' => $movement->quantity_change < 0,
                                ])>
                                    {{ $movement->quantity_change > 0 ? '+'.$movement->quantity_change : $movement->quantity_change }}
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ $movement->quantity_before }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $movement->quantity_after }}</td>
                                <td class="max-w-xs px-4 py-3 text-slate-600">
                                    {{ $movement->reason ?: '-' }}
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    {{ $movement->user?->name ?? 'System' }}
                                </td>
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
