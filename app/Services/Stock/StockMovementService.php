<?php

namespace App\Services\Stock;

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidStockMovementException;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Applies inventory quantity changes through audited stock movements.
 *
 * Public API for Livewire / application code:
 *
 *   $service->apply($product, StockMovementType::StockIn, 10, $user, 'Received shipment');
 *   $service->apply($product, StockMovementType::StockOut, 3, $user, 'Damaged units');
 *   $service->apply($product, StockMovementType::Adjustment, -2, $user, 'Cycle count correction');
 *   $service->recordOpeningStock($product, 25, $user);
 *   $service->recordLegacyOpeningBalance($product);
 *
 * For stock_in and stock_out, $quantity is an absolute positive unit count.
 * For adjustment, $quantity is a signed non-zero delta.
 */
class StockMovementService
{
    public const OPENING_STOCK_REASON = 'Opening stock';

    public const LEGACY_OPENING_REASON = 'System-generated legacy opening balance';

    /**
     * Apply a stock movement and update the product quantity atomically.
     *
     * @throws InvalidStockMovementException
     * @throws InsufficientStockException
     */
    public function apply(
        Product $product,
        StockMovementType $type,
        int $quantity,
        User $user,
        ?string $reason = null,
    ): StockMovement {
        if ($type === StockMovementType::LegacyOpening) {
            throw new InvalidStockMovementException(
                'Legacy opening balances must be recorded through reconciliation, not the adjust-stock workflow.'
            );
        }

        return DB::transaction(function () use ($product, $type, $quantity, $user, $reason) {
            /** @var Product $locked */
            $locked = Product::query()
                ->whereKey($product->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $quantityChange = $this->resolveQuantityChange($type, $quantity, $reason);
            $quantityBefore = (int) $locked->quantity_on_hand;
            $quantityAfter = $quantityBefore + $quantityChange;

            if ($quantityAfter < 0) {
                throw new InsufficientStockException(
                    "Insufficient stock for product [{$locked->id}]. Available: {$quantityBefore}, requested change: {$quantityChange}."
                );
            }

            $movement = StockMovement::query()->create([
                'product_id' => $locked->id,
                'user_id' => $user->id,
                'type' => $type,
                'quantity_change' => $quantityChange,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'reason' => $this->normalizeReason($reason),
            ]);

            $locked->forceFill([
                'quantity_on_hand' => $quantityAfter,
            ])->save();

            $product->setAttribute('quantity_on_hand', $quantityAfter);
            $product->syncOriginal();

            return $movement->fresh();
        });
    }

    /**
     * Record opening stock for a newly created product when quantity > 0.
     */
    public function recordOpeningStock(Product $product, int $quantity, User $user): ?StockMovement
    {
        if ($quantity < 0) {
            throw new InvalidStockMovementException('Opening stock quantity cannot be negative.');
        }

        if ($quantity === 0) {
            return null;
        }

        return $this->apply(
            $product,
            StockMovementType::StockIn,
            $quantity,
            $user,
            self::OPENING_STOCK_REASON,
        );
    }

    /**
     * Create an idempotent legacy opening-balance movement for existing stock
     * that predates movement logging. Does not change the product quantity and
     * does not assign a responsible user.
     */
    public function recordLegacyOpeningBalance(Product $product): ?StockMovement
    {
        return DB::transaction(function () use ($product) {
            /** @var Product $locked */
            $locked = Product::query()
                ->whereKey($product->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->stockMovements()->exists()) {
                return null;
            }

            $quantity = (int) $locked->quantity_on_hand;

            if ($quantity <= 0) {
                return null;
            }

            return StockMovement::query()->create([
                'product_id' => $locked->id,
                'user_id' => null,
                'type' => StockMovementType::LegacyOpening,
                'quantity_change' => $quantity,
                'quantity_before' => 0,
                'quantity_after' => $quantity,
                'reason' => self::LEGACY_OPENING_REASON,
            ]);
        });
    }

    /**
     * @throws InvalidStockMovementException
     */
    protected function resolveQuantityChange(StockMovementType $type, int $quantity, ?string $reason): int
    {
        return match ($type) {
            StockMovementType::StockIn => $this->resolveInboundQuantity($quantity),
            StockMovementType::StockOut => $this->resolveOutboundQuantity($quantity),
            StockMovementType::Adjustment => $this->resolveAdjustmentQuantity($quantity, $reason),
            StockMovementType::LegacyOpening => throw new InvalidStockMovementException(
                'Legacy opening balances cannot be applied through the normal stock workflow.'
            ),
        };
    }

    protected function resolveInboundQuantity(int $quantity): int
    {
        if ($quantity <= 0) {
            throw new InvalidStockMovementException('Stock in quantity must be greater than zero.');
        }

        return $quantity;
    }

    protected function resolveOutboundQuantity(int $quantity): int
    {
        if ($quantity <= 0) {
            throw new InvalidStockMovementException('Stock out quantity must be greater than zero.');
        }

        return -$quantity;
    }

    protected function resolveAdjustmentQuantity(int $quantity, ?string $reason): int
    {
        if ($quantity === 0) {
            throw new InvalidStockMovementException('Adjustment quantity cannot be zero.');
        }

        if ($this->normalizeReason($reason) === null) {
            throw new InvalidStockMovementException('A reason is required for stock adjustments.');
        }

        return $quantity;
    }

    protected function normalizeReason(?string $reason): ?string
    {
        if ($reason === null) {
            return null;
        }

        $trimmed = trim($reason);

        return $trimmed === '' ? null : $trimmed;
    }
}
