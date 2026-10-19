<?php

namespace App\Livewire\Products;

use App\Models\Category;
use App\Models\Product;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Products - StockPilot')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $productId = null;

    public string $name = '';

    public string $sku = '';

    public string $description = '';

    public string $unit_price = '0.00';

    public string $cost_price = '0.00';

    public string $quantity_on_hand = '0';

    public string $reorder_level = '0';

    public bool $is_active = true;

    /** @var list<int|string> */
    public array $categoryIds = [];

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

    public function edit(int $productId): void
    {
        $this->ensureAuthenticated();
        $this->clearFeedback();

        $product = Product::query()->with('categories')->findOrFail($productId);

        $this->productId = $product->id;
        $this->name = $product->name;
        $this->sku = $product->sku;
        $this->description = (string) ($product->description ?? '');
        $this->unit_price = $product->unit_price;
        $this->cost_price = $product->cost_price;
        $this->quantity_on_hand = (string) $product->quantity_on_hand;
        $this->reorder_level = (string) $product->reorder_level;
        $this->is_active = $product->is_active;
        $this->categoryIds = $product->categories->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->showForm = true;
        $this->confirmingDeletionId = null;
    }

    public function cancelForm(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        $this->ensureAuthenticated();
        $this->clearFeedback();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'sku')->ignore($this->productId),
            ],
            'description' => ['nullable', 'string'],
            'unit_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'cost_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'quantity_on_hand' => ['required', 'integer', 'min:0'],
            'reorder_level' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'categoryIds' => ['array'],
            'categoryIds.*' => ['integer', 'exists:categories,id'],
        ]);

        $categoryIds = collect($validated['categoryIds'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $attributes = [
            'name' => $validated['name'],
            'sku' => $validated['sku'],
            'description' => $validated['description'] ?: null,
            'unit_price' => $this->normalizeMoney($validated['unit_price']),
            'cost_price' => $this->normalizeMoney($validated['cost_price']),
            'quantity_on_hand' => (int) $validated['quantity_on_hand'],
            'reorder_level' => (int) $validated['reorder_level'],
            'is_active' => $validated['is_active'],
        ];

        try {
            if ($this->productId) {
                $product = Product::query()->findOrFail($this->productId);
                $product->update($attributes);
                $product->categories()->sync($categoryIds);
                $this->statusMessage = 'Product updated successfully.';
            } else {
                $product = Product::query()->create($attributes);
                $product->categories()->sync($categoryIds);
                $this->statusMessage = 'Product created successfully.';
            }
        } catch (QueryException) {
            $this->errorMessage = 'Unable to save the product. Please check your input and try again.';

            return;
        }

        $this->resetForm();
        $this->resetPage();
    }

    public function confirmDeletion(int $productId): void
    {
        $this->ensureAuthenticated();
        $this->clearFeedback();

        Product::query()->findOrFail($productId);

        $this->confirmingDeletionId = $productId;
        $this->showForm = false;
    }

    public function cancelDeletion(): void
    {
        $this->confirmingDeletionId = null;
    }

    public function delete(): void
    {
        $this->ensureAuthenticated();
        $this->clearFeedback();

        if ($this->confirmingDeletionId === null) {
            return;
        }

        try {
            $product = Product::query()->findOrFail($this->confirmingDeletionId);
            $product->delete();
            $this->statusMessage = 'Product deleted successfully.';
        } catch (QueryException) {
            $this->errorMessage = 'Unable to delete the product right now. Please try again.';

            return;
        }

        $this->confirmingDeletionId = null;
        $this->resetPage();
    }

    public function render()
    {
        $products = Product::query()
            ->with('categories')
            ->when($this->search !== '', function ($query) {
                $term = '%'.Str::lower($this->search).'%';
                $query->where(function ($inner) use ($term) {
                    $inner->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(sku) LIKE ?', [$term]);
                });
            })
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.products.index', [
            'products' => $products,
            'availableCategories' => Category::query()->orderBy('name')->get(['id', 'name', 'is_active']),
        ])->layoutData(['header' => 'Products']);
    }

    protected function resetForm(): void
    {
        $this->reset([
            'showForm',
            'productId',
            'name',
            'sku',
            'description',
            'categoryIds',
        ]);

        $this->unit_price = '0.00';
        $this->cost_price = '0.00';
        $this->quantity_on_hand = '0';
        $this->reorder_level = '0';
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

    /**
     * Normalize monetary input to a two-decimal string using exact decimal arithmetic.
     */
    protected function normalizeMoney(mixed $value): string
    {
        return (string) BigDecimal::of((string) $value)->toScale(2, RoundingMode::HALF_UP);
    }
}
