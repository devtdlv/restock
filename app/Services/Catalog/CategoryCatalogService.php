<?php

namespace App\Services\Catalog;

use App\Enums\AuditAction;
use App\Models\Category;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Creates, updates, and deletes categories with audit logging.
 */
class CategoryCatalogService
{
    public function __construct(
        protected AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, User $user): Category
    {
        return DB::transaction(function () use ($attributes, $user) {
            $category = Category::query()->create($this->attributesForPersistence($attributes));

            $this->auditLogger->record(
                AuditAction::Created,
                $category,
                null,
                $this->snapshot($category),
                $user,
            );

            return $category;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Category $category, array $attributes, User $user): Category
    {
        return DB::transaction(function () use ($category, $attributes, $user) {
            /** @var Category $locked */
            $locked = Category::query()
                ->whereKey($category->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $before = $this->snapshot($locked);

            $locked->update($this->attributesForPersistence($attributes));
            $locked->refresh();

            $this->auditLogger->record(
                AuditAction::Updated,
                $locked,
                $before,
                $this->snapshot($locked),
                $user,
            );

            return $locked;
        });
    }

    public function delete(Category $category, User $user): void
    {
        DB::transaction(function () use ($category, $user) {
            /** @var Category $locked */
            $locked = Category::query()
                ->whereKey($category->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $before = $this->snapshot($locked);

            $locked->delete();

            $this->auditLogger->record(
                AuditAction::Deleted,
                $locked,
                $before,
                null,
                $user,
            );
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(Category $category): array
    {
        return [
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'is_active' => (bool) $category->is_active,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function attributesForPersistence(array $attributes): array
    {
        return collect($attributes)
            ->only(['name', 'slug', 'description', 'is_active'])
            ->all();
    }
}
