<?php

namespace App\Services\Catalog;

use App\Enums\AuditAction;
use App\Models\Product;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Stock\StockMovementService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Creates, updates, and deletes products with audit logging and controlled stock.
 *
 * quantity_on_hand is never mass-assigned from edit payloads. Opening stock on
 * create is applied exclusively through StockMovementService.
 */
class ProductCatalogService
{
    public function __construct(
        protected AuditLogger $auditLogger,
        protected StockMovementService $stockMovements,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $categoryIds
     */
    public function create(array $attributes, array $categoryIds, User $user, int $openingStock = 0): Product
    {
        if ($openingStock < 0) {
            throw new InvalidArgumentException('Opening stock cannot be negative.');
        }

        return DB::transaction(function () use ($attributes, $categoryIds, $user, $openingStock) {
            $product = Product::query()->create($this->attributesForPersistence($attributes, includeQuantity: false));

            $this->stockMovements->recordOpeningStock($product, $openingStock, $user);

            $product->categories()->sync($this->normalizeCategoryIds($categoryIds));
            $product->refresh()->load('categories');

            $this->auditLogger->record(
                AuditAction::Created,
                $product,
                null,
                $this->snapshot($product),
                $user,
            );

            return $product;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $categoryIds
     */
    public function update(Product $product, array $attributes, array $categoryIds, User $user): Product
    {
        return DB::transaction(function () use ($product, $attributes, $categoryIds, $user) {
            /** @var Product $locked */
            $locked = Product::query()
                ->whereKey($product->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $locked->load('categories');
            $before = $this->snapshot($locked);

            $locked->update($this->attributesForPersistence($attributes, includeQuantity: false));
            $locked->categories()->sync($this->normalizeCategoryIds($categoryIds));
            $locked->refresh()->load('categories');

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

    public function delete(Product $product, User $user): void
    {
        DB::transaction(function () use ($product, $user) {
            /** @var Product $locked */
            $locked = Product::query()
                ->whereKey($product->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $locked->load('categories');
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
    public function snapshot(Product $product): array
    {
        $product->loadMissing('categories');

        return [
            'name' => $product->name,
            'sku' => $product->sku,
            'description' => $product->description,
            'unit_price' => $product->unit_price,
            'cost_price' => $product->cost_price,
            'quantity_on_hand' => (int) $product->quantity_on_hand,
            'reorder_level' => (int) $product->reorder_level,
            'is_active' => (bool) $product->is_active,
            'category_ids' => $product->categories
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function attributesForPersistence(array $attributes, bool $includeQuantity): array
    {
        $allowed = [
            'name',
            'sku',
            'description',
            'unit_price',
            'cost_price',
            'reorder_level',
            'is_active',
        ];

        if ($includeQuantity) {
            $allowed[] = 'quantity_on_hand';
        }

        // Explicitly drop quantity_on_hand from edit/create mass assignment path.
        unset($attributes['quantity_on_hand']);

        return collect($attributes)
            ->only($allowed)
            ->all();
    }

    /**
     * @param  list<int|string>  $categoryIds
     * @return list<int>
     */
    protected function normalizeCategoryIds(array $categoryIds): array
    {
        return collect($categoryIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
