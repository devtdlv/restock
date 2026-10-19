<?php

namespace App\Livewire\Suppliers;

use App\Models\Supplier;
use App\Services\Catalog\SupplierCatalogService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Suppliers - StockPilot')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $supplierId = null;

    public string $name = '';

    public string $code = '';

    public string $contact_person = '';

    public string $email = '';

    public string $phone = '';

    public string $address = '';

    public string $notes = '';

    public bool $is_active = true;

    public ?int $confirmingDeletionId = null;

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->ensureAuthenticated();
        $this->resetForm();
        $this->showForm = true;
        $this->clearFeedback();
    }

    public function edit(int $supplierId): void
    {
        $this->ensureAuthenticated();
        $this->clearFeedback();

        $supplier = Supplier::query()->findOrFail($supplierId);

        $this->supplierId = $supplier->id;
        $this->name = $supplier->name;
        $this->code = $supplier->code;
        $this->contact_person = (string) ($supplier->contact_person ?? '');
        $this->email = (string) ($supplier->email ?? '');
        $this->phone = (string) ($supplier->phone ?? '');
        $this->address = (string) ($supplier->address ?? '');
        $this->notes = (string) ($supplier->notes ?? '');
        $this->is_active = $supplier->is_active;
        $this->showForm = true;
        $this->confirmingDeletionId = null;
    }

    public function cancelForm(): void
    {
        $this->resetForm();
    }

    public function save(SupplierCatalogService $suppliers): void
    {
        $this->ensureAuthenticated();
        $this->clearFeedback();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:64',
                'alpha_dash',
                Rule::unique('suppliers', 'code')->ignore($this->supplierId),
            ],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $attributes = [
            'name' => $validated['name'],
            'code' => $validated['code'],
            'contact_person' => ($validated['contact_person'] ?? null) ?: null,
            'email' => ($validated['email'] ?? null) ?: null,
            'phone' => ($validated['phone'] ?? null) ?: null,
            'address' => ($validated['address'] ?? null) ?: null,
            'notes' => ($validated['notes'] ?? null) ?: null,
            'is_active' => $validated['is_active'],
        ];

        try {
            if ($this->supplierId) {
                $supplier = Supplier::query()->findOrFail($this->supplierId);
                $suppliers->update($supplier, $attributes, auth()->user());
                $this->statusMessage = 'Supplier updated successfully.';
            } else {
                $suppliers->create($attributes, auth()->user());
                $this->statusMessage = 'Supplier created successfully.';
            }
        } catch (QueryException) {
            $this->errorMessage = 'Unable to save the supplier. Please check your input and try again.';

            return;
        }

        $this->resetForm();
        $this->resetPage();
    }

    public function confirmDeletion(int $supplierId): void
    {
        $this->ensureAuthenticated();
        $this->clearFeedback();

        Supplier::query()->findOrFail($supplierId);

        $this->confirmingDeletionId = $supplierId;
        $this->showForm = false;
    }

    public function cancelDeletion(): void
    {
        $this->confirmingDeletionId = null;
    }

    public function delete(SupplierCatalogService $suppliers): void
    {
        $this->ensureAuthenticated();
        $this->clearFeedback();

        if ($this->confirmingDeletionId === null) {
            return;
        }

        try {
            $supplier = Supplier::query()->findOrFail($this->confirmingDeletionId);
            $suppliers->delete($supplier, auth()->user());
            $this->statusMessage = 'Supplier deleted successfully.';
        } catch (QueryException) {
            $this->errorMessage = 'Unable to delete the supplier right now. Please try again.';

            return;
        }

        $this->confirmingDeletionId = null;
        $this->resetPage();
    }

    public function render()
    {
        $suppliers = Supplier::query()
            ->when($this->search !== '', function ($query) {
                $term = '%'.Str::lower($this->search).'%';

                $query->where(function ($inner) use ($term) {
                    $inner->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(code) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(contact_person) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$term]);
                });
            })
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.suppliers.index', [
            'suppliers' => $suppliers,
        ])->layoutData(['header' => 'Suppliers']);
    }

    protected function resetForm(): void
    {
        $this->reset([
            'showForm',
            'supplierId',
            'name',
            'code',
            'contact_person',
            'email',
            'phone',
            'address',
            'notes',
        ]);

        $this->is_active = true;
        $this->resetValidation();
    }

    protected function clearFeedback(): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;
    }

    protected function ensureAuthenticated(): void
    {
        abort_unless(auth()->check(), 403);
    }
}
