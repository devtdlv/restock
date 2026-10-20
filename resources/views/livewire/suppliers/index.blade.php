<div class="space-y-6">
    <x-page-header
        title="Suppliers"
        subtitle="Manage vendor records and contacts for purchasing."
    >
        <x-slot:actions>
            <x-primary-button type="button" class="w-auto" wire:click="create">
                New supplier
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
        <x-input-label for="supplier-search" value="Search suppliers" />
        <x-text-input
            wire:model.live.debounce.300ms="search"
            id="supplier-search"
            type="search"
            placeholder="Search by name, code, contact, or email…"
            class="max-w-xl"
        />
    </x-panel>

    @if ($showForm)
        <x-form-panel
            :title="$supplierId ? 'Edit supplier' : 'Create supplier'"
            subtitle="Supplier codes must be unique. Contact details are optional."
            title-id="supplier-form-title"
        >
            @if ($errors->any())
                <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                    Please correct the highlighted fields below.
                </div>
            @endif

            <form wire:submit="save" class="grid gap-5 md:grid-cols-2">
                <div>
                    <x-input-label for="name" value="Supplier name" :required="true" />
                    <x-text-input wire:model="name" id="name" type="text" required aria-required="true" />
                    <x-input-error :messages="$errors->get('name')" />
                </div>

                <div>
                    <x-input-label for="code" value="Supplier code" :required="true" />
                    <x-text-input wire:model="code" id="code" type="text" required aria-required="true" placeholder="e.g. ACME-001" />
                    <x-input-error :messages="$errors->get('code')" />
                </div>

                <div>
                    <x-input-label for="contact_person" value="Contact person" :optional="true" />
                    <x-text-input wire:model="contact_person" id="contact_person" type="text" />
                    <x-input-error :messages="$errors->get('contact_person')" />
                </div>

                <div>
                    <x-input-label for="email" value="Email" :optional="true" />
                    <x-text-input wire:model="email" id="email" type="email" />
                    <x-input-error :messages="$errors->get('email')" />
                </div>

                <div>
                    <x-input-label for="phone" value="Phone" :optional="true" />
                    <x-text-input wire:model="phone" id="phone" type="text" />
                    <x-input-error :messages="$errors->get('phone')" />
                </div>

                <div class="flex min-h-11 items-center gap-2 self-end pb-1">
                    <input
                        wire:model="is_active"
                        id="is_active"
                        type="checkbox"
                        class="h-4 w-4 rounded border-slate-300 text-brand-700 focus:ring-brand-500"
                    >
                    <label for="is_active" class="text-sm text-slate-700">Active</label>
                </div>

                <div class="md:col-span-2">
                    <x-input-label for="address" value="Address" :optional="true" />
                    <x-textarea-input wire:model="address" id="address" rows="2" placeholder="Optional mailing or warehouse address" />
                    <x-input-error :messages="$errors->get('address')" />
                </div>

                <div class="md:col-span-2">
                    <x-input-label for="notes" value="Notes" :optional="true" />
                    <x-textarea-input wire:model="notes" id="notes" rows="3" placeholder="Optional internal notes" />
                    <x-input-error :messages="$errors->get('notes')" />
                </div>

                <x-form-actions class="md:col-span-2">
                    <x-primary-button class="w-full sm:w-auto" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">{{ $supplierId ? 'Save changes' : 'Create supplier' }}</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </x-primary-button>
                    <x-secondary-button type="button" class="w-full sm:w-auto" wire:click="cancelForm">Cancel</x-secondary-button>
                </x-form-actions>
            </form>
        </x-form-panel>
    @endif

    @if ($confirmingDeletionId)
        <x-modal
            title="Delete supplier?"
            variant="danger"
            close-action="cancelDeletion"
            close-on-escape
            close-on-backdrop
        >
            <p class="text-red-700">
                This permanently removes the supplier record. Audit history is retained. Once purchase orders exist, suppliers with related orders should not be deleted.
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
        @if ($suppliers->isEmpty())
            <x-empty-state
                :title="$search !== '' ? 'No suppliers match your search' : 'No suppliers yet'"
                :description="$search !== '' ? 'Try a different name, code, contact, or email.' : 'Create your first supplier to prepare for purchasing.'"
            >
                @if ($search === '')
                    <x-slot:actions>
                        <x-primary-button type="button" class="w-auto" wire:click="create">
                            Create supplier
                        </x-primary-button>
                    </x-slot:actions>
                @endif
            </x-empty-state>
        @else
            <x-table caption="Suppliers listing">
                <x-table.header>
                    <tr>
                        <th scope="col" class="px-4 py-3">Name</th>
                        <th scope="col" class="hidden px-4 py-3 sm:table-cell">Code</th>
                        <th scope="col" class="hidden px-4 py-3 md:table-cell">Contact</th>
                        <th scope="col" class="px-4 py-3">Status</th>
                        <th scope="col" class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </x-table.header>
                <x-table.body>
                    @foreach ($suppliers as $supplier)
                        <tr wire:key="supplier-{{ $supplier->id }}" class="hover:bg-slate-50/80">
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $supplier->name }}</div>
                                @if ($supplier->email)
                                    <div class="mt-0.5 text-xs text-slate-500">{{ $supplier->email }}</div>
                                @endif
                                <div class="mt-0.5 font-mono text-xs text-slate-500 sm:hidden">{{ $supplier->code }}</div>
                            </td>
                            <td class="hidden px-4 py-3 font-mono text-xs text-slate-600 sm:table-cell">{{ $supplier->code }}</td>
                            <td class="hidden px-4 py-3 text-slate-600 md:table-cell">
                                <div>{{ $supplier->contact_person ?: '-' }}</div>
                                @if ($supplier->phone)
                                    <div class="mt-0.5 text-xs text-slate-500">{{ $supplier->phone }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <x-status-badge :active="$supplier->is_active" />
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        wire:click="edit({{ $supplier->id }})"
                                        class="rounded-lg px-2.5 py-1.5 text-sm font-medium text-brand-700 hover:bg-brand-50"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="confirmDeletion({{ $supplier->id }})"
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

            @if ($suppliers->hasPages())
                <div class="border-t border-slate-200 px-4 py-3">
                    {{ $suppliers->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
