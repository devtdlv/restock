<?php

namespace App\Services\Reports;

use App\Models\Product;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only inventory overview and low-stock reporting.
 *
 * Low stock: quantity_on_hand <= reorder_level (includes out-of-stock).
 * Suggested replenishment: max(0, reorder_level - quantity_on_hand).
 * This is a shortage-to-reorder-level gap, not a purchasing recommendation.
 */
class InventoryReportService
{
    /**
     * @return array{
     *     active_products: int,
     *     inactive_products: int,
     *     total_units_on_hand: int,
     *     inventory_value_at_cost: string,
     *     low_stock_products: int,
     *     out_of_stock_products: int
     * }
     */
    public function overviewMetrics(): array
    {
        $activeProducts = (int) Product::query()->where('is_active', true)->count();
        $inactiveProducts = (int) Product::query()->where('is_active', false)->count();

        $totalUnits = (int) Product::query()
            ->where('is_active', true)
            ->sum('quantity_on_hand');

        $inventoryValue = Money::of('0');

        Product::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->select(['id', 'quantity_on_hand', 'cost_price'])
            ->chunkById(200, function ($products) use (&$inventoryValue): void {
                foreach ($products as $product) {
                    $line = Money::multiply(
                        Money::of($product->cost_price),
                        (int) $product->quantity_on_hand,
                    );
                    $inventoryValue = Money::add($inventoryValue, $line);
                }
            });

        $lowStock = (int) $this->lowStockQuery(activeOnly: true)->count();
        $outOfStock = (int) Product::query()
            ->where('is_active', true)
            ->where('quantity_on_hand', '<=', 0)
            ->count();

        return [
            'active_products' => $activeProducts,
            'inactive_products' => $inactiveProducts,
            'total_units_on_hand' => $totalUnits,
            'inventory_value_at_cost' => $inventoryValue,
            'low_stock_products' => $lowStock,
            'out_of_stock_products' => $outOfStock,
        ];
    }

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    public function lowStockPaginated(
        string $search = '',
        string $status = 'active',
        int $perPage = 15,
    ): LengthAwarePaginator {
        return $this->lowStockQuery(activeOnly: false)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $term = '%'.mb_strtolower($search).'%';
                $query->where(function (Builder $inner) use ($term): void {
                    $inner->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(sku) LIKE ?', [$term]);
                });
            })
            ->when($status === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->orderByRaw('CASE WHEN quantity_on_hand <= 0 THEN 0 ELSE 1 END')
            ->orderByRaw('(reorder_level - quantity_on_hand) DESC')
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * @return Builder<Product>
     */
    public function lowStockQuery(bool $activeOnly = true): Builder
    {
        return Product::query()
            ->whereColumn('quantity_on_hand', '<=', 'reorder_level')
            ->when($activeOnly, fn (Builder $query) => $query->where('is_active', true));
    }

    public function suggestedReplenishment(Product $product): int
    {
        return max(0, (int) $product->reorder_level - (int) $product->quantity_on_hand);
    }

    /**
     * @return \Generator<int, list<string|int>>
     */
    public function lowStockExportRows(string $search = '', string $status = 'active'): \Generator
    {
        $query = $this->lowStockQuery(activeOnly: false)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $term = '%'.mb_strtolower($search).'%';
                $query->where(function (Builder $inner) use ($term): void {
                    $inner->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(sku) LIKE ?', [$term]);
                });
            })
            ->when($status === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->orderByRaw('CASE WHEN quantity_on_hand <= 0 THEN 0 ELSE 1 END')
            ->orderByRaw('(reorder_level - quantity_on_hand) DESC')
            ->orderBy('name');

        foreach ($query->cursor() as $product) {
            yield [
                $product->name,
                $product->sku,
                (int) $product->quantity_on_hand,
                (int) $product->reorder_level,
                $this->suggestedReplenishment($product),
                $product->is_active ? 'Active' : 'Inactive',
            ];
        }
    }
}
