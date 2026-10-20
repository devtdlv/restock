<?php

namespace App\Enums;

use App\Models\Product;

/**
 * Presentation stock level derived from on-hand quantity and reorder level.
 *
 * Rules match inventory reporting:
 * - Out of stock: quantity_on_hand <= 0
 * - Low stock: quantity_on_hand > 0 and quantity_on_hand <= reorder_level
 * - In stock: quantity_on_hand > reorder_level
 */
enum StockLevel: string
{
    case InStock = 'in_stock';
    case LowStock = 'low_stock';
    case OutOfStock = 'out_of_stock';

    public static function forProduct(Product $product): self
    {
        $quantity = (int) $product->quantity_on_hand;
        $reorderLevel = (int) $product->reorder_level;

        if ($quantity <= 0) {
            return self::OutOfStock;
        }

        if ($quantity <= $reorderLevel) {
            return self::LowStock;
        }

        return self::InStock;
    }

    public function label(): string
    {
        return match ($this) {
            self::InStock => 'In stock',
            self::LowStock => 'Low stock',
            self::OutOfStock => 'Out of stock',
        };
    }

    /**
     * Badge variant for x-badge.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::InStock => 'success',
            self::LowStock => 'warning',
            self::OutOfStock => 'danger',
        };
    }

    public function isAttention(): bool
    {
        return $this !== self::InStock;
    }
}
