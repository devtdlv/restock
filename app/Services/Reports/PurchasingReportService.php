<?php

namespace App\Services\Reports;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Read-only purchasing analytics using historical PO unit costs.
 *
 * Aggregate value and quantity metrics exclude Draft and Cancelled orders
 * (committed purchasing only: ordered, partially received, received).
 * Status counts include every matching purchase order.
 */
class PurchasingReportService
{
    /**
     * @return array{
     *     total_orders: int,
     *     draft_orders: int,
     *     ordered_orders: int,
     *     partially_received_orders: int,
     *     received_orders: int,
     *     cancelled_orders: int,
     *     committed_ordered_value: string,
     *     committed_outstanding_quantity: int,
     *     committed_received_quantity: int
     * }
     */
    public function summary(
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?int $supplierId = null,
        ?string $status = null,
        string $search = '',
    ): array {
        $base = $this->ordersQuery($dateFrom, $dateTo, $supplierId, $status, $search);

        $counts = (clone $base)
            ->select('status', DB::raw('COUNT(*) as aggregate_count'))
            ->groupBy('status')
            ->pluck('aggregate_count', 'status');

        $committedStatuses = [
            PurchaseOrderStatus::Ordered->value,
            PurchaseOrderStatus::PartiallyReceived->value,
            PurchaseOrderStatus::Received->value,
        ];

        $committedOrderIds = (clone $base)
            ->whereIn('status', $committedStatuses)
            ->pluck('id');

        $committedOrderedValue = Money::of('0');
        $outstanding = 0;
        $received = 0;

        if ($committedOrderIds->isNotEmpty()) {
            PurchaseOrderItem::query()
                ->whereIn('purchase_order_id', $committedOrderIds)
                ->orderBy('id')
                ->select(['id', 'ordered_quantity', 'received_quantity', 'unit_cost'])
                ->chunkById(200, function ($items) use (&$committedOrderedValue, &$outstanding, &$received): void {
                    foreach ($items as $item) {
                        $line = Money::multiply(
                            Money::of($item->unit_cost),
                            (int) $item->ordered_quantity,
                        );
                        $committedOrderedValue = Money::add($committedOrderedValue, $line);
                        $outstanding += max(0, (int) $item->ordered_quantity - (int) $item->received_quantity);
                        $received += (int) $item->received_quantity;
                    }
                });
        }

        return [
            'total_orders' => (int) (clone $base)->count(),
            'draft_orders' => (int) ($counts[PurchaseOrderStatus::Draft->value] ?? 0),
            'ordered_orders' => (int) ($counts[PurchaseOrderStatus::Ordered->value] ?? 0),
            'partially_received_orders' => (int) ($counts[PurchaseOrderStatus::PartiallyReceived->value] ?? 0),
            'received_orders' => (int) ($counts[PurchaseOrderStatus::Received->value] ?? 0),
            'cancelled_orders' => (int) ($counts[PurchaseOrderStatus::Cancelled->value] ?? 0),
            'committed_ordered_value' => $committedOrderedValue,
            'committed_outstanding_quantity' => $outstanding,
            'committed_received_quantity' => $received,
        ];
    }

    /**
     * @return LengthAwarePaginator<int, PurchaseOrder>
     */
    public function ordersPaginated(
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?int $supplierId = null,
        ?string $status = null,
        string $search = '',
        int $perPage = 15,
    ): LengthAwarePaginator {
        return $this->ordersQuery($dateFrom, $dateTo, $supplierId, $status, $search)
            ->with(['supplier:id,name,code', 'items'])
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * @return Collection<int, array{supplier_id: int, supplier_name: string, supplier_code: string, order_count: int, committed_ordered_value: string}>
     */
    public function supplierSummary(
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?int $supplierId = null,
        ?string $status = null,
        string $search = '',
    ): Collection {
        $orders = $this->ordersQuery($dateFrom, $dateTo, $supplierId, $status, $search)
            ->with(['supplier:id,name,code', 'items'])
            ->orderBy('id')
            ->get();

        $grouped = [];

        foreach ($orders as $order) {
            $key = (int) $order->supplier_id;
            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'supplier_id' => $key,
                    'supplier_name' => $order->supplier?->name ?? 'Unavailable',
                    'supplier_code' => $order->supplier?->code ?? '',
                    'order_count' => 0,
                    'committed_ordered_value' => Money::of('0'),
                ];
            }

            $grouped[$key]['order_count']++;

            if ($this->isCommitted($order->status)) {
                $grouped[$key]['committed_ordered_value'] = Money::add(
                    $grouped[$key]['committed_ordered_value'],
                    $order->totalAmount(),
                );
            }
        }

        return collect($grouped)
            ->sortByDesc('order_count')
            ->values();
    }

    /**
     * @return \Generator<int, list<string|int|null>>
     */
    public function exportRows(
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?int $supplierId = null,
        ?string $status = null,
        string $search = '',
    ): \Generator {
        $query = $this->ordersQuery($dateFrom, $dateTo, $supplierId, $status, $search)
            ->with(['supplier:id,name,code', 'items'])
            ->orderByDesc('order_date')
            ->orderByDesc('id');

        foreach ($query->cursor() as $order) {
            $orderedQty = (int) $order->items->sum('ordered_quantity');
            $receivedQty = (int) $order->items->sum('received_quantity');

            yield [
                $order->number,
                $order->supplier?->name,
                $order->status->label(),
                $order->order_date?->toDateString(),
                $order->expected_delivery_date?->toDateString(),
                $order->totalAmount(),
                $receivedQty,
                max(0, $orderedQty - $receivedQty),
            ];
        }
    }

    /**
     * @return Builder<PurchaseOrder>
     */
    protected function ordersQuery(
        ?string $dateFrom,
        ?string $dateTo,
        ?int $supplierId,
        ?string $status,
        string $search,
    ): Builder {
        return PurchaseOrder::query()
            ->when($dateFrom, function (Builder $query) use ($dateFrom): void {
                $query->whereDate('order_date', '>=', Carbon::parse($dateFrom)->toDateString());
            })
            ->when($dateTo, function (Builder $query) use ($dateTo): void {
                $query->whereDate('order_date', '<=', Carbon::parse($dateTo)->toDateString());
            })
            ->when($supplierId, fn (Builder $query) => $query->where('supplier_id', $supplierId))
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $term = '%'.Str::lower($search).'%';
                $query->where(function (Builder $inner) use ($term): void {
                    $inner->whereRaw('LOWER(number) LIKE ?', [$term])
                        ->orWhereHas('supplier', function (Builder $supplierQuery) use ($term): void {
                            $supplierQuery->whereRaw('LOWER(name) LIKE ?', [$term])
                                ->orWhereRaw('LOWER(code) LIKE ?', [$term]);
                        });
                });
            });
    }

    protected function isCommitted(PurchaseOrderStatus|string $status): bool
    {
        $value = $status instanceof PurchaseOrderStatus ? $status : PurchaseOrderStatus::tryFrom((string) $status);

        return in_array($value, [
            PurchaseOrderStatus::Ordered,
            PurchaseOrderStatus::PartiallyReceived,
            PurchaseOrderStatus::Received,
        ], true);
    }
}
