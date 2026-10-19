<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Products</h1>
            <p class="mt-1 text-sm text-slate-500">Manage inventory items, pricing, stock levels, and categories.</p>
        </div>
        <x-primary-button type="button" class="w-auto" wire:click="create">
            New product
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
        <label for="product-search" class="sr-only">Search products</label>
        <x-text-input
            wire:model.live.debounce.300ms="search"
            id="product-search"
            type="search"
            placeholder="Search by name or SKU…"
            class="max-w-md"
        />
    </div>

    @if ($showForm)
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-lg font-semibold text-slate-900">
                {{ $productId ? 'Edit product' : 'Create product' }}
            </h2>
            <p class="mt-1 text-sm text-slate-500">
                Assign zero or more categories. Prices use two decimal places.
            </p>

            <form wire:submit="save" class="mt-6 grid gap-4 md:grid-cols-2">
                <div>
                    <x-input-label for="name" value="Name" />
                    <x-text-input wire:model="name" id="name" type="text" required />
                    <x-input-error :messages="$errors->get('name')" />
                </div>

                <div>
                    <x-input-label for="sku" value="SKU" />
                    <x-text-input wire:model="sku" id="sku" type="text" required />
                    <x-input-error :messages="$errors->get('sku')" />
                </div>

                <div class="md:col-span-2">
                    <x-input-label for="description" value="Description" />
                    <x-textarea-input wire:model="description" id="description" rows="3" placeholder="Optional details" />
                    <x-input-error :messages="$errors->get('description')" />
                </div>

                <div>
                    <x-input-label for="unit_price" value="Unit price" />
                    <x-text-input wire:model="unit_price" id="unit_price" type="text" inputmode="decimal" required />
                    <x-input-error :messages="$errors->get('unit_price')" />
                </div>

                <div>
                    <x-input-label for="cost_price" value="Cost price" />
                    <x-text-input wire:model="cost_price" id="cost_price" type="text" inputmode="decimal" required />
                    <x-input-error :messages="$errors->get('cost_price')" />
                </div>

                <div>
                    @if ($productId)
                        <x-input-label for="quantity_on_hand" value="Quantity on hand" />
                        <x-text-input
                            id="quantity_on_hand"
                            type="number"
                            value="{{ $quantity_on_hand }}"
                            disabled
                        />
                        <p class="mt-1.5 text-xs text-slate-500">Stock quantity is changed through stock movements only.</p>
                    @else
                        <x-input-label for="quantity_on_hand" value="Opening stock" />
                        <x-text-input wire:model="quantity_on_hand" id="quantity_on_hand" type="number" min="0" step="1" required />
                        <p class="mt-1.5 text-xs text-slate-500">Values greater than zero create a traceable opening stock movement.</p>
                        <x-input-error :messages="$errors->get('quantity_on_hand')" />
                    @endif
                </div>

                <div>
                    <x-input-label for="reorder_level" value="Reorder level" />
                    <x-text-input wire:model="reorder_level" id="reorder_level" type="number" min="0" step="1" required />
                    <x-input-error :messages="$errors->get('reorder_level')" />
                </div>

                <div class="md:col-span-2">
                    <x-input-label value="Categories" />
                    @if ($availableCategories->isEmpty())
                        <p class="mt-1 text-sm text-slate-500">No categories available yet. You can create the product without categories.</p>
                    @else
                        <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($availableCategories as $category)
                                <label
                                    wire:key="product-category-option-{{ $category->id }}"
                                    class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
                                >
                                    <input
                                        wire:model="categoryIds"
                                        type="checkbox"
                                        value="{{ $category->id }}"
                                        class="h-4 w-4 rounded border-slate-300 text-brand-700 focus:ring-brand-500"
                                    >
                                    <span>{{ $category->name }}</span>
                                    @unless ($category->is_active)
                                        <span class="text-xs text-slate-400">(inactive)</span>
                                    @endunless
                                </label>
                            @endforeach
                        </div>
                    @endif
                    <x-input-error :messages="collect($errors->getMessages())->filter(fn ($messages, $key) => $key === 'categoryIds' || str_starts_with($key, 'categoryIds.'))->flatten()->all()" />
                </div>

                <div class="md:col-span-2 flex items-center gap-2">
                    <input
                        wire:model="is_active"
                        id="product_is_active"
                        type="checkbox"
                        class="h-4 w-4 rounded border-slate-300 text-brand-700 focus:ring-brand-500"
                    >
                    <label for="product_is_active" class="text-sm text-slate-700">Active</label>
                </div>

                <div class="md:col-span-2 flex flex-wrap gap-3">
                    <x-primary-button class="w-auto" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="save">{{ $productId ? 'Save changes' : 'Create product' }}</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </x-primary-button>
                    <x-secondary-button wire:click="cancelForm">Cancel</x-secondary-button>
                </div>
            </form>
        </div>
    @endif

    @if ($showAdjustForm)
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-lg font-semibold text-slate-900">Adjust stock</h2>
            <p class="mt-1 text-sm text-slate-500">
                {{ $adjustingProductName }}
                <span class="font-mono text-xs text-slate-400">({{ $adjustingProductSku }})</span>
            </p>
            <p class="mt-3 text-sm text-slate-700">
                Current quantity:
                <span class="font-semibold">{{ $adjustingCurrentQuantity }}</span>
            </p>

            <form wire:submit="applyStockAdjustment" class="mt-6 grid gap-4 md:grid-cols-2">
                <div>
                    <x-input-label for="adjustmentType" value="Movement type" />
                    <select
                        wire:model.live="adjustmentType"
                        id="adjustmentType"
                        class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                    >
                        @foreach ($adjustmentTypes as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('adjustmentType')" />
                </div>

                <div>
                    <x-input-label for="adjustmentQuantity" :value="$adjustmentType === 'adjustment' ? 'Signed quantity change' : 'Quantity'" />
                    <x-text-input
                        wire:model="adjustmentQuantity"
                        id="adjustmentQuantity"
                        type="number"
                        step="1"
                        required
                    />
                    <p class="mt-1.5 text-xs text-slate-500">
                        @if ($adjustmentType === 'adjustment')
                            Use a positive number to increase stock or a negative number to decrease stock.
                        @else
                            Enter a positive quantity. StockPilot applies the correct sign for the selected type.
                        @endif
                    </p>
                    <x-input-error :messages="$errors->get('adjustmentQuantity')" />
                </div>

                <div class="md:col-span-2">
                    <x-input-label for="adjustmentReason" value="Reason" />
                    <x-textarea-input wire:model="adjustmentReason" id="adjustmentReason" rows="3" required placeholder="Why is this stock changing?" />
                    <x-input-error :messages="$errors->get('adjustmentReason')" />
                </div>

                <div class="md:col-span-2 flex flex-wrap gap-3">
                    <x-primary-button class="w-auto" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="applyStockAdjustment">Apply stock change</span>
                        <span wire:loading wire:target="applyStockAdjustment">Applying…</span>
                    </x-primary-button>
                    <x-secondary-button wire:click="cancelAdjustStock">Cancel</x-secondary-button>
                </div>
            </form>
        </div>
    @endif

    @if ($confirmingDeletionId)
        <div class="rounded-xl border border-red-200 bg-red-50 p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-red-800">Delete product?</h2>
            <p class="mt-1 text-sm text-red-700">
                This removes the product and its category associations. Categories themselves are not deleted.
            </p>
            <div class="mt-4 flex flex-wrap gap-3">
                <x-danger-button wire:click="delete" wire:loading.attr="disabled">
                    Confirm delete
                </x-danger-button>
                <x-secondary-button wire:click="cancelDeletion">Cancel</x-secondary-button>
            </div>
        </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        @if ($products->isEmpty())
            <div class="px-6 py-16 text-center">
                <p class="text-base font-semibold text-slate-900">
                    {{ $search !== '' ? 'No products match your search' : 'No products yet' }}
                </p>
                <p class="mt-2 text-sm text-slate-500">
                    {{ $search !== '' ? 'Try a different name or SKU.' : 'Create your first product to start tracking inventory.' }}
                </p>
                @if ($search === '')
                    <div class="mt-6">
                        <x-primary-button type="button" class="w-auto" wire:click="create">
                            Create product
                        </x-primary-button>
                    </div>
                @endif
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">Product</th>
                            <th class="px-4 py-3 font-medium">SKU</th>
                            <th class="px-4 py-3 font-medium">Price</th>
                            <th class="px-4 py-3 font-medium">Qty</th>
                            <th class="px-4 py-3 font-medium">Categories</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($products as $product)
                            <tr wire:key="product-{{ $product->id }}" class="hover:bg-slate-50/80">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-900">{{ $product->name }}</div>
                                    @if ($product->description)
                                        <div class="mt-0.5 max-w-xs truncate text-xs text-slate-500">{{ $product->description }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $product->sku }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $product->unit_price }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $product->quantity_on_hand }}</td>
                                <td class="px-4 py-3">
                                    @if ($product->categories->isEmpty())
                                        <span class="text-xs text-slate-400">None</span>
                                    @else
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($product->categories as $category)
                                                <span class="rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600">
                                                    {{ $category->name }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <x-status-badge :active="$product->is_active" />
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <button
                                            type="button"
                                            wire:click="openAdjustStock({{ $product->id }})"
                                            class="rounded-lg px-2.5 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100"
                                        >
                                            Adjust Stock
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="edit({{ $product->id }})"
                                            class="rounded-lg px-2.5 py-1.5 text-sm font-medium text-brand-700 hover:bg-brand-50"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="confirmDeletion({{ $product->id }})"
                                            class="rounded-lg px-2.5 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50"
                                        >
                                            Delete
                                        </button>
                                    </div>
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
