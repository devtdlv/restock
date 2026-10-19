<?php

namespace App\Services\Catalog;

use App\Enums\AuditAction;
use App\Exceptions\InvalidPurchaseOrderException;
use App\Models\Product;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Stock\StockMovementService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * Creates, updates, and deletes products with audit logging and controlled stock.
 *
 * quantity_on_hand is never mass-assigned from edit payloads. Opening stock on
 * create is applied exclusively through StockMovementService.
 * Product images are stored on the public disk; only relative paths are persisted.
 */
class ProductCatalogService
{
    public function __construct(
        protected AuditLogger $auditLogger,
        protected StockMovementService $stockMovements,
        protected ProductImageService $images,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $categoryIds
     */
    public function create(
        array $attributes,
        array $categoryIds,
        User $user,
        int $openingStock = 0,
        UploadedFile|TemporaryUploadedFile|null $image = null,
    ): Product {
        if ($openingStock < 0) {
            throw new InvalidArgumentException('Opening stock cannot be negative.');
        }

        $storedPath = null;

        try {
            if ($image !== null) {
                $storedPath = $this->images->store($image);
            }

            return DB::transaction(function () use ($attributes, $categoryIds, $user, $openingStock, $storedPath) {
                $payload = $this->attributesForPersistence($attributes, includeQuantity: false);
                if ($storedPath !== null) {
                    $payload['image_path'] = $storedPath;
                }

                $product = Product::query()->create($payload);

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
        } catch (Throwable $exception) {
            if ($storedPath !== null) {
                $this->images->delete($storedPath);
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $categoryIds
     */
    public function update(
        Product $product,
        array $attributes,
        array $categoryIds,
        User $user,
        UploadedFile|TemporaryUploadedFile|null $image = null,
        bool $removeImage = false,
    ): Product {
        $newPath = null;
        $oldPath = null;

        try {
            if ($image !== null) {
                $newPath = $this->images->store($image);
            }

            $updated = DB::transaction(function () use (
                $product,
                $attributes,
                $categoryIds,
                $user,
                $newPath,
                $removeImage,
                &$oldPath,
            ) {
                /** @var Product $locked */
                $locked = Product::query()
                    ->whereKey($product->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $locked->load('categories');
                $before = $this->snapshot($locked);
                $oldPath = $locked->image_path;

                $payload = $this->attributesForPersistence($attributes, includeQuantity: false);

                if ($newPath !== null) {
                    $payload['image_path'] = $newPath;
                } elseif ($removeImage) {
                    $payload['image_path'] = null;
                }

                $locked->update($payload);
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

            if ($newPath !== null && $oldPath !== null && $oldPath !== $newPath) {
                $this->images->delete($oldPath);
            }

            if ($newPath === null && $removeImage && $oldPath !== null) {
                $this->images->delete($oldPath);
            }

            return $updated;
        } catch (Throwable $exception) {
            if ($newPath !== null) {
                $this->images->delete($newPath);
            }

            throw $exception;
        }
    }

    public function delete(Product $product, User $user): void
    {
        $imagePath = null;

        DB::transaction(function () use ($product, $user, &$imagePath) {
            /** @var Product $locked */
            $locked = Product::query()
                ->whereKey($product->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $locked->load('categories');
            $before = $this->snapshot($locked);
            $imagePath = $locked->image_path;

            if ($locked->purchaseOrderItems()->exists()) {
                throw new InvalidPurchaseOrderException(
                    'Products referenced by purchase orders cannot be deleted.'
                );
            }

            $locked->delete();

            $this->auditLogger->record(
                AuditAction::Deleted,
                $locked,
                $before,
                null,
                $user,
            );
        });

        $this->images->delete($imagePath);
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
            'image_path' => $product->image_path,
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
            'image_path',
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
