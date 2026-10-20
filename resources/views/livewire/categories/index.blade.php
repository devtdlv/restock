<div class="space-y-6">
    <x-page-header
        title="Categories"
        subtitle="Organize products into reusable inventory categories."
    >
        <x-slot:actions>
            <x-primary-button type="button" class="w-auto" wire:click="create">
                New category
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
        <x-input-label for="category-search" value="Search categories" />
        <x-text-input
            wire:model.live.debounce.300ms="search"
            id="category-search"
            type="search"
            placeholder="Search by name…"
            class="max-w-md"
        />
    </x-panel>

    @if ($showForm)
        <x-form-panel
            :title="$categoryId ? 'Edit category' : 'Create category'"
            subtitle="Slugs are generated from the name when creating. You can edit them anytime."
            title-id="category-form-title"
        >
            @if ($errors->any())
                <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                    Please correct the highlighted fields below.
                </div>
            @endif

            <form wire:submit="save" class="grid gap-5 md:grid-cols-2">
                <div>
                    <x-input-label for="name" value="Name" :required="true" />
                    <x-text-input wire:model.live="name" id="name" type="text" required aria-required="true" />
                    <x-input-error :messages="$errors->get('name')" />
                </div>

                <div>
                    <x-input-label for="slug" value="Slug" :required="true" />
                    <x-text-input wire:model.live="slug" id="slug" type="text" required aria-required="true" />
                    <x-form-help>Lowercase letters, numbers, and hyphens.</x-form-help>
                    <x-input-error :messages="$errors->get('slug')" />
                </div>

                <div class="md:col-span-2">
                    <x-input-label for="description" value="Description" :optional="true" />
                    <x-textarea-input wire:model="description" id="description" rows="3" placeholder="Optional details" />
                    <x-input-error :messages="$errors->get('description')" />
                </div>

                <div class="md:col-span-2 flex min-h-11 items-center gap-2">
                    <input
                        wire:model="is_active"
                        id="is_active"
                        type="checkbox"
                        class="h-4 w-4 rounded border-slate-300 text-brand-700 focus:ring-brand-500"
                    >
                    <label for="is_active" class="text-sm text-slate-700">Active</label>
                </div>

                <x-form-actions class="md:col-span-2">
                    <x-primary-button class="w-full sm:w-auto" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">{{ $categoryId ? 'Save changes' : 'Create category' }}</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </x-primary-button>
                    <x-secondary-button type="button" class="w-full sm:w-auto" wire:click="cancelForm">Cancel</x-secondary-button>
                </x-form-actions>
            </form>
        </x-form-panel>
    @endif

    @if ($confirmingDeletionId)
        <x-modal
            title="Delete category?"
            variant="danger"
            close-action="cancelDeletion"
            close-on-escape
            close-on-backdrop
        >
            <p class="text-red-700">
                This removes the category and its product associations. Products themselves are not deleted.
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
        @if ($categories->isEmpty())
            <x-empty-state
                :title="$search !== '' ? 'No categories match your search' : 'No categories yet'"
                :description="$search !== '' ? 'Try a different name or clear the search.' : 'Create your first category to start organizing products.'"
            >
                @if ($search === '')
                    <x-slot:actions>
                        <x-primary-button type="button" class="w-auto" wire:click="create">
                            Create category
                        </x-primary-button>
                    </x-slot:actions>
                @endif
            </x-empty-state>
        @else
            <x-table caption="Product categories listing">
                <x-table.header>
                    <tr>
                        <th scope="col" class="px-4 py-3">Name</th>
                        <th scope="col" class="hidden px-4 py-3 md:table-cell">Slug</th>
                        <th scope="col" class="px-4 py-3 text-right">Products</th>
                        <th scope="col" class="px-4 py-3">Status</th>
                        <th scope="col" class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </x-table.header>
                <x-table.body>
                    @foreach ($categories as $category)
                        <tr wire:key="category-{{ $category->id }}" class="hover:bg-slate-50/80">
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $category->name }}</div>
                                @if ($category->description)
                                    <div class="mt-0.5 max-w-xs truncate text-xs text-slate-500">{{ $category->description }}</div>
                                @endif
                                <div class="mt-0.5 font-mono text-xs text-slate-500 md:hidden">{{ $category->slug }}</div>
                            </td>
                            <td class="hidden px-4 py-3 font-mono text-xs text-slate-600 md:table-cell">{{ $category->slug }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-600">{{ $category->products_count }}</td>
                            <td class="px-4 py-3">
                                <x-status-badge :active="$category->is_active" />
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        wire:click="edit({{ $category->id }})"
                                        class="rounded-lg px-2.5 py-1.5 text-sm font-medium text-brand-700 hover:bg-brand-50"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="confirmDeletion({{ $category->id }})"
                                        class="rounded-lg px-2.5 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-table.body>
            </x-table>

            @if ($categories->hasPages())
                <div class="border-t border-slate-200 px-4 py-3">
                    {{ $categories->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
