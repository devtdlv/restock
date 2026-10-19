<?php

namespace App\Services\Purchasing;

use App\Enums\AuditAction;
use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementType;
use App\Exceptions\InvalidPurchaseOrderException;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderReceipt;
use App\Models\PurchaseOrderReceiptItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Stock\StockMovementService;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Purchase order lifecycle and atomic partial receiving.
 */
class PurchaseOrderService
{
    public function __construct(
        protected AuditLogger $auditLogger,
        protected StockMovementService $stockMovements,
    ) {}

    /**
     * @param  list<array{product_id: int, ordered_quantity: int, unit_cost: mixed}>  $items
     */
    public function createDraft(
        User $user,
        int $supplierId,
        array $items,
        CarbonInterface|string|null $orderDate = null,
        CarbonInterface|string|null $expectedDeliveryDate = null,
        ?string $notes = null,
    ): PurchaseOrder {
        return DB::transaction(function () use ($user, $supplierId, $items, $orderDate, $expectedDeliveryDate, $notes) {
            $this->assertSupplierExists($supplierId);
            $normalizedItems = $this->normalizeItems($items);

            $order = PurchaseOrder::query()->create([
                'number' => $this->generateNumber(),
                'supplier_id' => $supplierId,
                'status' => PurchaseOrderStatus::Draft,
                'order_date' => $this->normalizeDate($orderDate) ?? Carbon::today()->toDateString(),
                'expected_delivery_date' => $this->normalizeDate($expectedDeliveryDate),
                'notes' => $this->normalizeNullableString($notes),
                'created_by' => $user->id,
            ]);

            $this->syncItems($order, $normalizedItems);
            $order->load(['items', 'supplier']);

            $this->auditLogger->record(
                AuditAction::Created,
                $order,
                null,
                $this->snapshot($order),
                $user,
            );

            return $order;
        });
    }

    /**
     * @param  list<array{product_id: int, ordered_quantity: int, unit_cost: mixed}>  $items
     */
    public function updateDraft(
        PurchaseOrder $purchaseOrder,
        User $user,
        int $supplierId,
        array $items,
        CarbonInterface|string|null $orderDate = null,
        CarbonInterface|string|null $expectedDeliveryDate = null,
        ?string $notes = null,
    ): PurchaseOrder {
        return DB::transaction(function () use ($purchaseOrder, $user, $supplierId, $items, $orderDate, $expectedDeliveryDate, $notes) {
            $locked = $this->lockPurchaseOrder($purchaseOrder);

            if ($locked->status !== PurchaseOrderStatus::Draft) {
                throw new InvalidPurchaseOrderException('Only draft purchase orders can be edited.');
            }

            $this->assertSupplierExists($supplierId);
            $normalizedItems = $this->normalizeItems($items);
            $before = $this->snapshot($locked->load('items'));

            $locked->update([
                'supplier_id' => $supplierId,
                'order_date' => $this->normalizeDate($orderDate) ?? $locked->order_date?->toDateString(),
                'expected_delivery_date' => $this->normalizeDate($expectedDeliveryDate),
                'notes' => $this->normalizeNullableString($notes),
            ]);

            $this->syncItems($locked, $normalizedItems);
            $locked->refresh()->load(['items', 'supplier']);

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

    public function markOrdered(PurchaseOrder $purchaseOrder, User $user): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $user) {
            $locked = $this->lockPurchaseOrder($purchaseOrder)->load('items');

            if ($locked->status !== PurchaseOrderStatus::Draft) {
                throw new InvalidPurchaseOrderException('Only draft purchase orders can be marked as ordered.');
            }

            if ($locked->items->isEmpty()) {
                throw new InvalidPurchaseOrderException('A purchase order must include at least one line item.');
            }

            $before = $this->snapshot($locked);
            $locked->update(['status' => PurchaseOrderStatus::Ordered]);
            $locked->refresh()->load(['items', 'supplier']);

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

    public function cancel(PurchaseOrder $purchaseOrder, User $user): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $user) {
            $locked = $this->lockPurchaseOrder($purchaseOrder)->load('items');

            if (! $locked->status->canBeCancelled()) {
                throw new InvalidPurchaseOrderException(
                    'Only draft or ordered purchase orders without receipts can be cancelled.'
                );
            }

            if ($locked->hasAnyReceivedQuantity()) {
                throw new InvalidPurchaseOrderException(
                    'Purchase orders with received inventory cannot be cancelled.'
                );
            }

            $before = $this->snapshot($locked);
            $locked->update(['status' => PurchaseOrderStatus::Cancelled]);
            $locked->refresh()->load(['items', 'supplier']);

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

    /**
     * Receive inventory against an ordered or partially received purchase order.
     *
     * @param  array<int, int>  $quantitiesByItemId  purchase_order_item_id => quantity
     */
    public function receive(
        PurchaseOrder $purchaseOrder,
        array $quantitiesByItemId,
        User $user,
        string $idempotencyKey,
        ?string $notes = null,
    ): PurchaseOrderReceipt {
        $idempotencyKey = trim($idempotencyKey);

        if ($idempotencyKey === '' || strlen($idempotencyKey) > 64) {
            throw new InvalidPurchaseOrderException('A valid receipt idempotency key is required.');
        }

        return DB::transaction(function () use ($purchaseOrder, $quantitiesByItemId, $user, $idempotencyKey, $notes) {
            $existing = PurchaseOrderReceipt::query()
                ->where('receipt_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ($existing->purchase_order_id !== $purchaseOrder->id) {
                    throw new InvalidPurchaseOrderException(
                        'Receipt key is already used by a different purchase order.'
                    );
                }

                return $existing->load(['items.stockMovement', 'items.purchaseOrderItem']);
            }

            $lockedOrder = $this->lockPurchaseOrder($purchaseOrder);

            if (! $lockedOrder->status->allowsReceiving()) {
                throw new InvalidPurchaseOrderException(
                    "Cannot receive inventory for a purchase order in status [{$lockedOrder->status->value}]."
                );
            }

            $quantities = $this->normalizeReceiptQuantities($quantitiesByItemId);
            $itemIds = array_keys($quantities);
            sort($itemIds);

            /** @var \Illuminate\Support\Collection<int, PurchaseOrderItem> $items */
            $items = PurchaseOrderItem::query()
                ->where('purchase_order_id', $lockedOrder->id)
                ->whereIn('id', $itemIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($items->count() !== count($itemIds)) {
                throw new InvalidPurchaseOrderException('One or more receipt lines are invalid for this purchase order.');
            }

            $productIds = $items->pluck('product_id')->unique()->sort()->values()->all();
            Product::query()
                ->whereIn('id', $productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $before = $this->snapshot($lockedOrder->load('items'));

            $receipt = PurchaseOrderReceipt::query()->create([
                'receipt_key' => $idempotencyKey,
                'purchase_order_id' => $lockedOrder->id,
                'user_id' => $user->id,
                'received_at' => now(),
                'notes' => $this->normalizeNullableString($notes),
            ]);

            foreach ($itemIds as $itemId) {
                /** @var PurchaseOrderItem $item */
                $item = $items[$itemId];
                $quantity = $quantities[$itemId];
                $outstanding = $item->outstandingQuantity();

                if ($quantity > $outstanding) {
                    throw new InvalidPurchaseOrderException(
                        "Cannot receive {$quantity} for item [{$item->id}]; outstanding quantity is {$outstanding}."
                    );
                }

                $movement = $this->stockMovements->apply(
                    $item->product()->firstOrFail(),
                    StockMovementType::StockIn,
                    $quantity,
                    $user,
                    "Received {$lockedOrder->number} ({$idempotencyKey})",
                );

                PurchaseOrderReceiptItem::query()->create([
                    'purchase_order_receipt_id' => $receipt->id,
                    'purchase_order_item_id' => $item->id,
                    'quantity' => $quantity,
                    'stock_movement_id' => $movement->id,
                ]);

                $item->update([
                    'received_quantity' => (int) $item->received_quantity + $quantity,
                ]);
            }

            $lockedOrder->refresh()->load('items');
            $newStatus = $lockedOrder->isFullyReceived()
                ? PurchaseOrderStatus::Received
                : PurchaseOrderStatus::PartiallyReceived;

            $lockedOrder->update(['status' => $newStatus]);
            $lockedOrder->refresh()->load(['items', 'supplier']);

            $this->auditLogger->record(
                AuditAction::Created,
                $receipt,
                null,
                $this->receiptSnapshot($receipt->load('items')),
                $user,
            );

            $this->auditLogger->record(
                AuditAction::Updated,
                $lockedOrder,
                $before,
                $this->snapshot($lockedOrder),
                $user,
            );

            return $receipt->load(['items.stockMovement', 'items.purchaseOrderItem']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(PurchaseOrder $order): array
    {
        $order->loadMissing('items');

        return [
            'number' => $order->number,
            'supplier_id' => (int) $order->supplier_id,
            'status' => $order->status instanceof PurchaseOrderStatus
                ? $order->status->value
                : (string) $order->status,
            'order_date' => $order->order_date?->toDateString(),
            'expected_delivery_date' => $order->expected_delivery_date?->toDateString(),
            'notes' => $order->notes,
            'created_by' => $order->created_by,
            'total_amount' => $order->totalAmount(),
            'items' => $order->items
                ->sortBy('product_id')
                ->values()
                ->map(fn (PurchaseOrderItem $item) => [
                    'product_id' => (int) $item->product_id,
                    'ordered_quantity' => (int) $item->ordered_quantity,
                    'received_quantity' => (int) $item->received_quantity,
                    'unit_cost' => Money::of($item->unit_cost),
                    'line_total' => $item->lineTotal(),
                ])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function receiptSnapshot(PurchaseOrderReceipt $receipt): array
    {
        $receipt->loadMissing('items');

        return [
            'receipt_key' => $receipt->receipt_key,
            'purchase_order_id' => (int) $receipt->purchase_order_id,
            'user_id' => $receipt->user_id,
            'received_at' => $receipt->received_at?->toIso8601String(),
            'notes' => $receipt->notes,
            'items' => $receipt->items
                ->sortBy('purchase_order_item_id')
                ->values()
                ->map(fn (PurchaseOrderReceiptItem $item) => [
                    'purchase_order_item_id' => (int) $item->purchase_order_item_id,
                    'quantity' => (int) $item->quantity,
                    'stock_movement_id' => $item->stock_movement_id,
                ])
                ->all(),
        ];
    }

    protected function lockPurchaseOrder(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        /** @var PurchaseOrder $locked */
        $locked = PurchaseOrder::query()
            ->whereKey($purchaseOrder->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        return $locked;
    }

    protected function assertSupplierExists(int $supplierId): void
    {
        if (! Supplier::query()->whereKey($supplierId)->exists()) {
            throw new InvalidPurchaseOrderException('The selected supplier does not exist.');
        }
    }

    /**
     * @param  list<array{product_id: int|string, ordered_quantity: int|string, unit_cost: mixed}>  $items
     * @return list<array{product_id: int, ordered_quantity: int, unit_cost: string}>
     */
    protected function normalizeItems(array $items): array
    {
        if ($items === []) {
            throw new InvalidPurchaseOrderException('A purchase order must include at least one line item.');
        }

        $normalized = [];
        $seenProducts = [];

        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $orderedQuantity = (int) ($item['ordered_quantity'] ?? 0);
            $unitCost = Money::of($item['unit_cost'] ?? '0');

            if ($productId <= 0) {
                throw new InvalidPurchaseOrderException('Each line item requires a valid product.');
            }

            if (isset($seenProducts[$productId])) {
                throw new InvalidPurchaseOrderException('Duplicate products are not allowed on a purchase order.');
            }

            if ($orderedQuantity <= 0) {
                throw new InvalidPurchaseOrderException('Ordered quantity must be greater than zero.');
            }

            if (Money::isNegative($unitCost)) {
                throw new InvalidPurchaseOrderException('Unit cost cannot be negative.');
            }

            if (! Product::query()->whereKey($productId)->exists()) {
                throw new InvalidPurchaseOrderException("Product [{$productId}] does not exist.");
            }

            $seenProducts[$productId] = true;
            $normalized[] = [
                'product_id' => $productId,
                'ordered_quantity' => $orderedQuantity,
                'unit_cost' => $unitCost,
            ];
        }

        usort($normalized, fn (array $a, array $b) => $a['product_id'] <=> $b['product_id']);

        return $normalized;
    }

    /**
     * @param  list<array{product_id: int, ordered_quantity: int, unit_cost: string}>  $items
     */
    protected function syncItems(PurchaseOrder $order, array $items): void
    {
        $order->items()->delete();

        foreach ($items as $item) {
            PurchaseOrderItem::query()->create([
                'purchase_order_id' => $order->id,
                'product_id' => $item['product_id'],
                'ordered_quantity' => $item['ordered_quantity'],
                'received_quantity' => 0,
                'unit_cost' => $item['unit_cost'],
            ]);
        }
    }

    /**
     * @param  array<int|string, int|string>  $quantitiesByItemId
     * @return array<int, int>
     */
    protected function normalizeReceiptQuantities(array $quantitiesByItemId): array
    {
        if ($quantitiesByItemId === []) {
            throw new InvalidPurchaseOrderException('At least one receipt quantity is required.');
        }

        $normalized = [];

        foreach ($quantitiesByItemId as $itemId => $quantity) {
            $id = (int) $itemId;
            $qty = (int) $quantity;

            if ($id <= 0) {
                throw new InvalidPurchaseOrderException('Receipt lines must reference valid purchase order items.');
            }

            if ($qty <= 0) {
                throw new InvalidPurchaseOrderException('Receipt quantities must be greater than zero.');
            }

            if (isset($normalized[$id])) {
                throw new InvalidPurchaseOrderException('Duplicate receipt lines for the same item are not allowed.');
            }

            $normalized[$id] = $qty;
        }

        return $normalized;
    }

    protected function generateNumber(): string
    {
        $latest = PurchaseOrder::query()
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('number');

        $sequence = 1;

        if (is_string($latest) && preg_match('/^PO-(\d+)$/', $latest, $matches) === 1) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return sprintf('PO-%06d', $sequence);
    }

    protected function normalizeDate(CarbonInterface|string|null $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        return Carbon::parse($date)->toDateString();
    }

    protected function normalizeNullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
