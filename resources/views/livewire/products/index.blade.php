<div class="space-y-6">
    <x-page-header
        title="Products"
        subtitle="Manage inventory items, pricing, stock levels, and categories."
    >
        <x-slot:actions>
            <x-primary-button type="button" class="w-auto" wire:click="create">
                New product
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
        <x-input-label for="product-search" value="Search products" />
        <x-text-input
            wire:model.live.debounce.300ms="search"
            id="product-search"
            type="search"
            placeholder="Search by name or SKU…"
            class="max-w-md"
        />
    </x-panel>

    @if ($showForm)
        <x-form-panel
            :title="$productId ? 'Edit product' : 'Create product'"
            subtitle="Assign zero or more categories. Prices use two decimal places."
            title-id="product-form-title"
        >
            @if ($errors->any())
                <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                    Please correct the highlighted fields below.
                </div>
            @endif

            <form wire:submit="save" class="grid gap-5 md:grid-cols-2">
                <div>
                    <x-input-label for="name" value="Name" :required="true" />
                    <x-text-input wire:model="name" id="name" type="text" required aria-required="true" />
                    <x-input-error :messages="$errors->get('name')" />
                </div>

                <div>
                    <x-input-label for="sku" value="SKU" :required="true" />
                    <x-text-input wire:model="sku" id="sku" type="text" required aria-required="true" />
                    <x-input-error :messages="$errors->get('sku')" />
                </div>

                <div class="md:col-span-2">
                    <x-input-label for="description" value="Description" :optional="true" />
                    <x-textarea-input wire:model="description" id="description" rows="3" placeholder="Optional details" />
                    <x-input-error :messages="$errors->get('description')" />
                </div>

                <div>
                    <x-input-label for="unit_price" value="Unit price" :required="true" />
                    <x-text-input wire:model="unit_price" id="unit_price" type="text" inputmode="decimal" required aria-required="true" />
                    <x-input-error :messages="$errors->get('unit_price')" />
                </div>

                <div>
                    <x-input-label for="cost_price" value="Cost price" :required="true" />
                    <x-text-input wire:model="cost_price" id="cost_price" type="text" inputmode="decimal" required aria-required="true" />
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
                        <x-form-help>Stock quantity is changed through stock movements only.</x-form-help>
                    @else
                        <x-input-label for="quantity_on_hand" value="Opening stock" :required="true" />
                        <x-text-input wire:model="quantity_on_hand" id="quantity_on_hand" type="number" min="0" step="1" required aria-required="true" />
                        <x-form-help>Values greater than zero create a traceable opening stock movement.</x-form-help>
                        <x-input-error :messages="$errors->get('quantity_on_hand')" />
                    @endif
                </div>

                <div>
                    <x-input-label for="reorder_level" value="Reorder level" :required="true" />
                    <x-text-input wire:model="reorder_level" id="reorder_level" type="number" min="0" step="1" required aria-required="true" />
                    <x-input-error :messages="$errors->get('reorder_level')" />
                </div>

                <fieldset class="md:col-span-2 min-w-0">
                    <legend class="mb-1.5 text-sm font-medium text-slate-700">Categories <span class="font-normal text-slate-400">(optional)</span></legend>
                    @if ($availableCategories->isEmpty())
                        <x-form-help class="mt-0">No categories available yet. You can create the product without categories.</x-form-help>
                    @else
                        <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($availableCategories as $category)
                                <label
                                    wire:key="product-category-option-{{ $category->id }}"
                                    class="flex min-h-11 items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
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
                </fieldset>

                <div class="md:col-span-2 flex min-h-11 items-center gap-2">
                    <input
                        wire:model="is_active"
                        id="product_is_active"
                        type="checkbox"
                        class="h-4 w-4 rounded border-slate-300 text-brand-700 focus:ring-brand-500"
                    >
                    <label for="product_is_active" class="text-sm text-slate-700">Active</label>
                </div>

                <div class="md:col-span-2 rounded-lg border border-dashed border-slate-300 bg-slate-50/80 p-4">
                    <x-input-label for="product_image" value="Product image" :optional="true" />
                    <x-form-help class="mt-0">JPEG, PNG, or WebP. Maximum 2 MB. SVG files are not allowed.</x-form-help>

                    <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-start">
                        <div class="shrink-0">
                            @php
                                $selectedPreviewUrl = null;
                                if ($image && method_exists($image, 'isPreviewable') && $image->isPreviewable()) {
                                    try {
                                        $selectedPreviewUrl = $image->temporaryUrl();
                                    } catch (\Throwable) {
                                        $selectedPreviewUrl = null;
                                    }
                                }
                            @endphp
                            @if ($selectedPreviewUrl)
                                <img
                                    src="{{ $selectedPreviewUrl }}"
                                    alt="Selected product image preview"
                                    class="h-28 w-28 rounded-lg border border-slate-200 object-cover shadow-sm"
                                >
                            @elseif ($existingImageUrl && ! $removeImage)
                                <x-product-image :url="$existingImageUrl" :alt="$name !== '' ? $name : 'Existing product image'" size="lg" />
                            @else
                                <x-product-image size="lg" alt="Product image placeholder" />
                            @endif
                        </div>

                        <div class="min-w-0 flex-1 space-y-3">
                            <input
                                wire:model="image"
                                id="product_image"
                                type="file"
                                accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                                class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2.5 file:text-sm file:font-medium file:text-brand-800 hover:file:bg-brand-100"
                            >
                            <div wire:loading wire:target="image" class="text-sm text-slate-500" role="status" aria-live="polite">
                                Uploading image preview…
                            </div>
                            <x-input-error :messages="$errors->get('image')" />

                            <div class="flex flex-wrap gap-2">
                                @if ($image)
                                    <x-secondary-button type="button" class="w-auto" wire:click="clearSelectedImage">
                                        Clear selection
                                    </x-secondary-button>
                                @endif
                                @if ($existingImageUrl && ! $removeImage && ! $image)
                                    <x-secondary-button type="button" class="w-auto" wire:click="removeExistingImage">
                                        Remove image
                                    </x-secondary-button>
                                @endif
                                @if ($removeImage && ! $image)
                                    <p class="text-sm text-amber-700" role="status">Image will be removed when you save.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <x-form-actions class="md:col-span-2">
                    <x-primary-button class="w-full sm:w-auto" wire:loading.attr="disabled" wire:target="save,image">
                        <span wire:loading.remove wire:target="save">{{ $productId ? 'Save changes' : 'Create product' }}</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </x-primary-button>
                    <x-secondary-button type="button" class="w-full sm:w-auto" wire:click="cancelForm">Cancel</x-secondary-button>
                </x-form-actions>
            </form>
        </x-form-panel>
    @endif

    @if ($showAdjustForm)
        @php
            $adjustQtyInt = is_numeric($adjustmentQuantity) ? (int) $adjustmentQuantity : null;
            $currentQtyInt = (int) $adjustingCurrentQuantity;
            $expectedQty = null;

            if ($adjustQtyInt !== null) {
                $expectedQty = match ($adjustmentType) {
                    'stock_in' => $currentQtyInt + max($adjustQtyInt, 0),
                    'stock_out' => $currentQtyInt - max($adjustQtyInt, 0),
                    'adjustment' => $currentQtyInt + $adjustQtyInt,
                    default => null,
                };
            }
        @endphp

        <x-form-panel
            title="Adjust stock"
            :subtitle="$adjustingProductName.' ('.$adjustingProductSku.')'"
            title-id="stock-adjust-form-title"
        >
            <div class="flex flex-col gap-3 rounded-lg border border-slate-200 bg-slate-50/80 p-4 sm:flex-row sm:items-center">
                <x-product-image :url="$adjustingProductImageUrl" size="sm" :alt="$adjustingProductName" />
                <div class="min-w-0 space-y-1 text-sm text-slate-700">
                    <p>
                        <span class="text-slate-500">Product</span>
                        <span class="ml-2 font-medium text-slate-900">{{ $adjustingProductName }}</span>
                    </p>
                    <p>
                        <span class="text-slate-500">Current quantity</span>
                        <span class="ml-2 font-semibold tabular-nums text-slate-900">{{ $adjustingCurrentQuantity }}</span>
                    </p>
                    @if ($expectedQty !== null)
                        <p>
                            <span class="text-slate-500">Expected quantity after change</span>
                            <span class="ml-2 font-semibold tabular-nums text-slate-900">{{ $expectedQty }}</span>
                        </p>
                    @endif
                </div>
            </div>

            <form wire:submit="applyStockAdjustment" class="mt-5 grid gap-5 md:grid-cols-2">
                <div>
                    <x-input-label for="adjustmentType" value="Movement type" :required="true" />
                    <x-select-input wire:model.live="adjustmentType" id="adjustmentType" required aria-required="true">
                        @foreach ($adjustmentTypes as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </x-select-input>
                    <x-input-error :messages="$errors->get('adjustmentType')" />
                </div>

                <div>
                    <x-input-label
                        for="adjustmentQuantity"
                        :value="$adjustmentType === 'adjustment' ? 'Signed quantity change' : 'Quantity'"
                        :required="true"
                    />
                    <x-text-input
                        wire:model.live="adjustmentQuantity"
                        id="adjustmentQuantity"
                        type="number"
                        step="1"
                        required
                        aria-required="true"
                    />
                    <x-form-help>
                        @if ($adjustmentType === 'adjustment')
                            Use a positive number to increase stock or a negative number to decrease stock.
                        @elseif ($adjustmentType === 'stock_out')
                            Enter a positive quantity to remove from stock.
                        @else
                            Enter a positive quantity to add to stock.
                        @endif
                    </x-form-help>
                    <x-input-error :messages="$errors->get('adjustmentQuantity')" />
                </div>

                <div class="md:col-span-2">
                    <x-input-label for="adjustmentReason" value="Reason" :required="true" />
                    <x-textarea-input wire:model="adjustmentReason" id="adjustmentReason" rows="3" required aria-required="true" placeholder="Why is this stock changing?" />
                    <x-input-error :messages="$errors->get('adjustmentReason')" />
                </div>

                <x-form-actions class="md:col-span-2">
                    <x-primary-button class="w-full sm:w-auto" wire:loading.attr="disabled" wire:target="applyStockAdjustment">
                        <span wire:loading.remove wire:target="applyStockAdjustment">Apply stock change</span>
                        <span wire:loading wire:target="applyStockAdjustment">Applying…</span>
                    </x-primary-button>
                    <x-secondary-button type="button" class="w-full sm:w-auto" wire:click="cancelAdjustStock">Cancel</x-secondary-button>
                </x-form-actions>
            </form>
        </x-form-panel>
    @endif

    @if ($confirmingDeletionId)
        <x-modal
            title="Delete product?"
            variant="danger"
            close-action="cancelDeletion"
            close-on-escape
            close-on-backdrop
        >
            <p class="text-red-700">
                This removes the product and its category associations. Categories themselves are not deleted.
            </p>

            <x-slot:footer>
                <x-secondary-button type="button" class="w-full sm:w-auto" wire:click="cancelDeletion">Cancel</x-secondary-button>
                <x-danger-button type="button" class="w-full sm:w-auto" wire:click="delete" wire:loading.attr="disabled">
                    Confirm delete
                </x-danger-button>
            </x-slot:footer>
        </x-modal>
    @endif

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        @if ($products->isEmpty())
            <x-empty-state
                :title="$search !== '' ? 'No products match your search' : 'No products yet'"
                :description="$search !== '' ? 'Try a different name or SKU.' : 'Create your first product to start tracking inventory.'"
            >
                @if ($search === '')
                    <x-slot:actions>
                        <x-primary-button type="button" class="w-auto" wire:click="create">
                            Create product
                        </x-primary-button>
                    </x-slot:actions>
                @endif
            </x-empty-state>
        @else
            <x-table caption="Products inventory listing">
                <x-table.header>
                    <tr>
                        <th scope="col" class="px-4 py-3">Product</th>
                        <th scope="col" class="hidden px-4 py-3 md:table-cell">SKU</th>
                        <th scope="col" class="px-4 py-3 text-right">Price</th>
                        <th scope="col" class="px-4 py-3 text-right">Qty</th>
                        <th scope="col" class="px-4 py-3">Stock</th>
                        <th scope="col" class="hidden px-4 py-3 lg:table-cell">Categories</th>
                        <th scope="col" class="hidden px-4 py-3 sm:table-cell">Status</th>
                        <th scope="col" class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </x-table.header>
                <x-table.body>
                    @foreach ($products as $product)
                        <tr wire:key="product-{{ $product->id }}" class="hover:bg-slate-50/80">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <x-product-image :product="$product" size="sm" />
                                    <div class="min-w-0">
                                        <div class="font-medium text-slate-900">{{ $product->name }}</div>
                                        <div class="font-mono text-xs text-slate-500 md:hidden">{{ $product->sku }}</div>
                                        @if ($product->description)
                                            <div class="mt-0.5 max-w-xs truncate text-xs text-slate-500">{{ $product->description }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="hidden px-4 py-3 font-mono text-xs text-slate-600 md:table-cell">{{ $product->sku }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-700">{{ $display->money($product->unit_price) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-700">{{ $display->quantity($product->quantity_on_hand) }}</td>
                            <td class="px-4 py-3">
                                <x-stock-level-badge :product="$product" />
                            </td>
                            <td class="hidden px-4 py-3 lg:table-cell">
                                @if ($product->categories->isEmpty())
                                    <span class="text-xs text-slate-400">None</span>
                                @else
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($product->categories as $category)
                                            <x-badge variant="neutral">{{ $category->name }}</x-badge>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="hidden px-4 py-3 sm:table-cell">
                                <x-status-badge :active="$product->is_active" />
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-col items-stretch justify-end gap-1 sm:flex-row sm:flex-wrap sm:items-center sm:gap-2">
                                    <button
                                        type="button"
                                        wire:click="openAdjustStock({{ $product->id }})"
                                        class="min-h-11 rounded-lg px-2.5 py-1.5 text-left text-sm font-medium text-slate-700 hover:bg-slate-100 sm:text-center"
                                    >
                                        Adjust
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="edit({{ $product->id }})"
                                        class="min-h-11 rounded-lg px-2.5 py-1.5 text-left text-sm font-medium text-brand-700 hover:bg-brand-50 sm:text-center"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="confirmDeletion({{ $product->id }})"
                                        class="min-h-11 rounded-lg px-2.5 py-1.5 text-left text-sm font-medium text-red-600 hover:bg-red-50 sm:text-center"
                                    >
                                        Delete
                                    </button>
                                </div>
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
