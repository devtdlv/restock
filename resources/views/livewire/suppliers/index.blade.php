<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Suppliers</h1>
            <p class="mt-1 text-sm text-slate-500">Manage vendor records and contacts for purchasing.</p>
        </div>
        <x-primary-button type="button" class="w-auto" wire:click="create">
            New supplier
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
        <label for="supplier-search" class="sr-only">Search suppliers</label>
        <x-text-input
            wire:model.live.debounce.300ms="search"
            id="supplier-search"
            type="search"
            placeholder="Search by name, code, contact, or email…"
            class="max-w-xl"
        />
    </div>

    @if ($showForm)
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-lg font-semibold text-slate-900">
                {{ $supplierId ? 'Edit supplier' : 'Create supplier' }}
            </h2>
            <p class="mt-1 text-sm text-slate-500">
                Supplier codes must be unique. Contact details are optional.
            </p>

            <form wire:submit="save" class="mt-6 grid gap-4 md:grid-cols-2">
                <div class="md:col-span-1">
                    <x-input-label for="name" value="Supplier name" />
                    <x-text-input wire:model="name" id="name" type="text" required />
                    <x-input-error :messages="$errors->get('name')" />
                </div>

                <div class="md:col-span-1">
                    <x-input-label for="code" value="Supplier code" />
                    <x-text-input wire:model="code" id="code" type="text" required placeholder="e.g. ACME-001" />
                    <x-input-error :messages="$errors->get('code')" />
                </div>

                <div class="md:col-span-1">
                    <x-input-label for="contact_person" value="Contact person" />
                    <x-text-input wire:model="contact_person" id="contact_person" type="text" />
                    <x-input-error :messages="$errors->get('contact_person')" />
                </div>

                <div class="md:col-span-1">
                    <x-input-label for="email" value="Email" />
                    <x-text-input wire:model="email" id="email" type="email" />
                    <x-input-error :messages="$errors->get('email')" />
                </div>

                <div class="md:col-span-1">
                    <x-input-label for="phone" value="Phone" />
                    <x-text-input wire:model="phone" id="phone" type="text" />
                    <x-input-error :messages="$errors->get('phone')" />
                </div>

                <div class="md:col-span-1 flex items-center gap-2 self-end pb-2">
                    <input
                        wire:model="is_active"
                        id="is_active"
                        type="checkbox"
                        class="h-4 w-4 rounded border-slate-300 text-brand-700 focus:ring-brand-500"
                    >
                    <label for="is_active" class="text-sm text-slate-700">Active</label>
                </div>

                <div class="md:col-span-2">
                    <x-input-label for="address" value="Address" />
                    <x-textarea-input wire:model="address" id="address" rows="2" placeholder="Optional mailing or warehouse address" />
                    <x-input-error :messages="$errors->get('address')" />
                </div>

                <div class="md:col-span-2">
                    <x-input-label for="notes" value="Notes" />
                    <x-textarea-input wire:model="notes" id="notes" rows="3" placeholder="Optional internal notes" />
                    <x-input-error :messages="$errors->get('notes')" />
                </div>

                <div class="md:col-span-2 flex flex-wrap gap-3">
                    <x-primary-button class="w-auto" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="save">{{ $supplierId ? 'Save changes' : 'Create supplier' }}</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </x-primary-button>
                    <x-secondary-button wire:click="cancelForm">Cancel</x-secondary-button>
                </div>
            </form>
        </div>
    @endif

    @if ($confirmingDeletionId)
        <div class="rounded-xl border border-red-200 bg-red-50 p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-red-800">Delete supplier?</h2>
            <p class="mt-1 text-sm text-red-700">
                This permanently removes the supplier record. Audit history is retained. Once purchase orders exist, suppliers with related orders should not be deleted.
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
        @if ($suppliers->isEmpty())
            <div class="px-6 py-16 text-center">
                <p class="text-base font-semibold text-slate-900">
                    {{ $search !== '' ? 'No suppliers match your search' : 'No suppliers yet' }}
                </p>
                <p class="mt-2 text-sm text-slate-500">
                    {{ $search !== '' ? 'Try a different name, code, contact, or email.' : 'Create your first supplier to prepare for purchasing.' }}
                </p>
                @if ($search === '')
                    <div class="mt-6">
                        <x-primary-button type="button" class="w-auto" wire:click="create">
                            Create supplier
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
                            <th class="px-4 py-3 font-medium">Code</th>
                            <th class="px-4 py-3 font-medium">Contact</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($suppliers as $supplier)
                            <tr wire:key="supplier-{{ $supplier->id }}" class="hover:bg-slate-50/80">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-900">{{ $supplier->name }}</div>
                                    @if ($supplier->email)
                                        <div class="mt-0.5 text-xs text-slate-500">{{ $supplier->email }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $supplier->code }}</td>
                                <td class="px-4 py-3 text-slate-600">
                                    <div>{{ $supplier->contact_person ?: '—' }}</div>
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
                    </tbody>
                </table>
            </div>

            @if ($suppliers->hasPages())
                <div class="border-t border-slate-200 px-4 py-3">
                    {{ $suppliers->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
