<?php

namespace App\Livewire\Categories;

use App\Models\Category;
use App\Services\Catalog\CategoryCatalogService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Categories - StockPilot')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $categoryId = null;

    public string $name = '';

    public string $slug = '';

    public string $description = '';

    public bool $is_active = true;

    public bool $slugManuallyEdited = false;

    public ?int $confirmingDeletionId = null;

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedName(string $value): void
    {
        if ($this->categoryId === null && ! $this->slugManuallyEdited) {
            $this->slug = Str::slug($value);
        }
    }

    public function updatedSlug(): void
    {
        $this->slugManuallyEdited = true;
    }

    public function create(): void
    {
        $this->ensureAuthenticated();
        $this->resetForm();
        $this->showForm = true;
        $this->clearFeedback();
    }

    public function edit(int $categoryId): void
    {
        $this->ensureAuthenticated();
        $this->clearFeedback();

        $category = Category::query()->findOrFail($categoryId);

        $this->categoryId = $category->id;
        $this->name = $category->name;
        $this->slug = $category->slug;
        $this->description = (string) ($category->description ?? '');
        $this->is_active = $category->is_active;
        $this->slugManuallyEdited = true;
        $this->showForm = true;
        $this->confirmingDeletionId = null;
    }

    public function cancelForm(): void
    {
        $this->resetForm();
    }

    public function save(CategoryCatalogService $categories): void
    {
        $this->ensureAuthenticated();
        $this->clearFeedback();

        $validated = $this->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')->ignore($this->categoryId),
            ],
            'slug' => [
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('categories', 'slug')->ignore($this->categoryId),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $attributes = [
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?: null,
            'is_active' => $validated['is_active'],
        ];

        try {
            if ($this->categoryId) {
                $category = Category::query()->findOrFail($this->categoryId);
                $categories->update($category, $attributes, auth()->user());
                $this->statusMessage = 'Category updated successfully.';
            } else {
                $categories->create($attributes, auth()->user());
                $this->statusMessage = 'Category created successfully.';
            }
        } catch (QueryException) {
            $this->errorMessage = 'Unable to save the category. Please check your input and try again.';

            return;
        }

        $this->resetForm();
        $this->resetPage();
    }

    public function confirmDeletion(int $categoryId): void
    {
        $this->ensureAuthenticated();
        $this->clearFeedback();

        Category::query()->findOrFail($categoryId);

        $this->confirmingDeletionId = $categoryId;
        $this->showForm = false;
    }

    public function cancelDeletion(): void
    {
        $this->confirmingDeletionId = null;
    }

    public function delete(CategoryCatalogService $categories): void
    {
        $this->ensureAuthenticated();
        $this->clearFeedback();

        if ($this->confirmingDeletionId === null) {
            return;
        }

        try {
            $category = Category::query()->findOrFail($this->confirmingDeletionId);
            $categories->delete($category, auth()->user());
            $this->statusMessage = 'Category deleted successfully.';
        } catch (QueryException) {
            $this->errorMessage = 'Unable to delete the category right now. Please try again.';

            return;
        }

        $this->confirmingDeletionId = null;
        $this->resetPage();
    }

    public function render()
    {
        $categories = Category::query()
            ->withCount('products')
            ->when($this->search !== '', function ($query) {
                $term = '%'.Str::lower($this->search).'%';
                $query->whereRaw('LOWER(name) LIKE ?', [$term]);
            })
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.categories.index', [
            'categories' => $categories,
        ])->layoutData(['header' => 'Categories']);
    }

    protected function resetForm(): void
    {
        $this->reset([
            'showForm',
            'categoryId',
            'name',
            'slug',
            'description',
            'slugManuallyEdited',
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
