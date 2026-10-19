<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Categories</h1>
            <p class="mt-1 text-sm text-slate-500">Organize products into reusable inventory categories.</p>
        </div>
        <x-primary-button type="button" class="w-auto" wire:click="create">
            New category
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
        <label for="category-search" class="sr-only">Search categories</label>
        <x-text-input
            wire:model.live.debounce.300ms="search"
            id="category-search"
            type="search"
            placeholder="Search by name…"
            class="max-w-md"
        />
    </div>

    @if ($showForm)
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-lg font-semibold text-slate-900">
                {{ $categoryId ? 'Edit category' : 'Create category' }}
            </h2>
            <p class="mt-1 text-sm text-slate-500">
                Slugs are generated from the name when creating. You can edit them anytime.
            </p>

            <form wire:submit="save" class="mt-6 grid gap-4 md:grid-cols-2">
                <div class="md:col-span-1">
                    <x-input-label for="name" value="Name" />
                    <x-text-input wire:model.live="name" id="name" type="text" required />
                    <x-input-error :messages="$errors->get('name')" />
                </div>

                <div class="md:col-span-1">
                    <x-input-label for="slug" value="Slug" />
                    <x-text-input wire:model.live="slug" id="slug" type="text" required />
                    <x-input-error :messages="$errors->get('slug')" />
                </div>

                <div class="md:col-span-2">
                    <x-input-label for="description" value="Description" />
                    <x-textarea-input wire:model="description" id="description" rows="3" placeholder="Optional details" />
                    <x-input-error :messages="$errors->get('description')" />
                </div>

                <div class="md:col-span-2 flex items-center gap-2">
                    <input
                        wire:model="is_active"
                        id="is_active"
                        type="checkbox"
                        class="h-4 w-4 rounded border-slate-300 text-brand-700 focus:ring-brand-500"
                    >
                    <label for="is_active" class="text-sm text-slate-700">Active</label>
                </div>

                <div class="md:col-span-2 flex flex-wrap gap-3">
                    <x-primary-button class="w-auto" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="save">{{ $categoryId ? 'Save changes' : 'Create category' }}</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </x-primary-button>
                    <x-secondary-button wire:click="cancelForm">Cancel</x-secondary-button>
                </div>
            </form>
        </div>
    @endif

    @if ($confirmingDeletionId)
        <div class="rounded-xl border border-red-200 bg-red-50 p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-red-800">Delete category?</h2>
            <p class="mt-1 text-sm text-red-700">
                This removes the category and its product associations. Products themselves are not deleted.
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
        @if ($categories->isEmpty())
            <div class="px-6 py-16 text-center">
                <p class="text-base font-semibold text-slate-900">
                    {{ $search !== '' ? 'No categories match your search' : 'No categories yet' }}
                </p>
                <p class="mt-2 text-sm text-slate-500">
                    {{ $search !== '' ? 'Try a different name or clear the search.' : 'Create your first category to start organizing products.' }}
                </p>
                @if ($search === '')
                    <div class="mt-6">
                        <x-primary-button type="button" class="w-auto" wire:click="create">
                            Create category
                        </x-primary-button>
                    </div>
                @endif
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">Name</th>
                            <th class="px-4 py-3 font-medium">Slug</th>
                            <th class="px-4 py-3 font-medium">Products</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($categories as $category)
                            <tr wire:key="category-{{ $category->id }}" class="hover:bg-slate-50/80">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-900">{{ $category->name }}</div>
                                    @if ($category->description)
                                        <div class="mt-0.5 max-w-xs truncate text-xs text-slate-500">{{ $category->description }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $category->slug }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $category->products_count }}</td>
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
                    </tbody>
                </table>
            </div>

            @if ($categories->hasPages())
                <div class="border-t border-slate-200 px-4 py-3">
                    {{ $categories->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
