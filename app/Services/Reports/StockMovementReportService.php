<?php

namespace App\Services\Reports;

use App\Models\StockMovement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Read-only stock movement history reporting.
 */
class StockMovementReportService
{
    /**
     * @return LengthAwarePaginator<int, StockMovement>
     */
    public function paginated(
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?int $productId = null,
        ?string $type = null,
        int $perPage = 20,
    ): LengthAwarePaginator {
        return $this->filteredQuery($dateFrom, $dateTo, $productId, $type)
            ->with(['product:id,name,sku', 'user:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * @return \Generator<int, list<string|int|null>>
     */
    public function exportRows(
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?int $productId = null,
        ?string $type = null,
    ): \Generator {
        $query = $this->filteredQuery($dateFrom, $dateTo, $productId, $type)
            ->with(['product:id,name,sku', 'user:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        foreach ($query->cursor() as $movement) {
            yield [
                $movement->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
                $movement->product?->name,
                $movement->product?->sku,
                $movement->type?->label(),
                (int) $movement->quantity_change,
                (int) $movement->quantity_before,
                (int) $movement->quantity_after,
                $movement->reason,
                $movement->user?->name,
            ];
        }
    }

    /**
     * @return Builder<StockMovement>
     */
    protected function filteredQuery(
        ?string $dateFrom,
        ?string $dateTo,
        ?int $productId,
        ?string $type,
    ): Builder {
        return StockMovement::query()
            ->when($dateFrom, function (Builder $query) use ($dateFrom): void {
                $query->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
            })
            ->when($dateTo, function (Builder $query) use ($dateTo): void {
                $query->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
            })
            ->when($productId, fn (Builder $query) => $query->where('product_id', $productId))
            ->when($type, fn (Builder $query) => $query->where('type', $type));
    }
}
