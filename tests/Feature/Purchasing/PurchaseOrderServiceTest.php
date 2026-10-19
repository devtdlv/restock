<?php

namespace Tests\Feature\Purchasing;

use App\Enums\AuditAction;
use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementType;
use App\Exceptions\InvalidPurchaseOrderException;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderReceipt;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Catalog\ProductCatalogService;
use App\Services\Catalog\SupplierCatalogService;
use App\Services\Purchasing\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurchaseOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PurchaseOrderService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PurchaseOrderService::class);
    }

    public function test_draft_purchase_order_can_be_created_with_multiple_line_items(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $mouse = Product::factory()->create(['name' => 'Wireless Mouse', 'cost_price' => '10.00']);
        $keyboard = Product::factory()->create(['name' => 'Keyboard', 'cost_price' => '20.00']);

        $order = $this->service->createDraft(
            $user,
            $supplier->id,
            [
                ['product_id' => $mouse->id, 'ordered_quantity' => 100, 'unit_cost' => '12.50'],
                ['product_id' => $keyboard->id, 'ordered_quantity' => 25, 'unit_cost' => '18.00'],
            ],
            '2026-10-19',
            '2026-10-30',
            'Initial PO',
        );

        $this->assertSame('PO-000001', $order->number);
        $this->assertSame(PurchaseOrderStatus::Draft, $order->status);
        $this->assertSame($supplier->id, $order->supplier_id);
        $this->assertSame($user->id, $order->created_by);
        $this->assertCount(2, $order->items);
        $this->assertSame(0, $mouse->fresh()->quantity_on_hand);
        $this->assertSame('1700.00', $order->totalAmount());
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_unique_purchase_order_numbers_are_generated(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        $first = $this->service->createDraft($user, $supplier->id, [
            ['product_id' => $product->id, 'ordered_quantity' => 1, 'unit_cost' => '1.00'],
        ]);
        $second = $this->service->createDraft($user, $supplier->id, [
            ['product_id' => $product->id, 'ordered_quantity' => 2, 'unit_cost' => '1.00'],
        ]);

        $this->assertSame('PO-000001', $first->number);
        $this->assertSame('PO-000002', $second->number);
        $this->assertNotSame($first->number, $second->number);
    }

    public function test_duplicate_products_and_invalid_inputs_are_rejected(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        try {
            $this->service->createDraft($user, $supplier->id, [
                ['product_id' => $product->id, 'ordered_quantity' => 5, 'unit_cost' => '1.00'],
                ['product_id' => $product->id, 'ordered_quantity' => 3, 'unit_cost' => '1.00'],
            ]);
            $this->fail('Duplicate products should be rejected.');
        } catch (InvalidPurchaseOrderException) {
            $this->assertDatabaseCount('purchase_orders', 0);
        }

        $this->expectException(InvalidPurchaseOrderException::class);
        $this->service->createDraft($user, $supplier->id, [
            ['product_id' => $product->id, 'ordered_quantity' => 0, 'unit_cost' => '1.00'],
        ]);
    }

    public function test_supplier_and_product_validation(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->expectException(InvalidPurchaseOrderException::class);
        $this->service->createDraft($user, 999999, [
            ['product_id' => $product->id, 'ordered_quantity' => 1, 'unit_cost' => '1.00'],
        ]);
    }

    public function test_draft_purchase_order_can_be_edited(): void
    {
        $user = User::factory()->create();
        $supplierA = Supplier::factory()->create();
        $supplierB = Supplier::factory()->create();
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();

        $order = $this->service->createDraft($user, $supplierA->id, [
            ['product_id' => $productA->id, 'ordered_quantity' => 10, 'unit_cost' => '5.00'],
        ]);

        $updated = $this->service->updateDraft(
            $order,
            $user,
            $supplierB->id,
            [
                ['product_id' => $productB->id, 'ordered_quantity' => 7, 'unit_cost' => '9.25'],
            ],
            '2026-11-01',
            null,
            'Updated notes',
        );

        $this->assertSame($supplierB->id, $updated->supplier_id);
        $this->assertSame('Updated notes', $updated->notes);
        $this->assertCount(1, $updated->items);
        $this->assertSame($productB->id, $updated->items->first()->product_id);
        $this->assertSame(7, $updated->items->first()->ordered_quantity);
        $this->assertSame('9.25', $updated->items->first()->unit_cost);
        $this->assertSame('64.75', $updated->totalAmount());
    }

    public function test_non_draft_orders_cannot_be_edited(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        $order = $this->service->createDraft($user, $supplier->id, [
            ['product_id' => $product->id, 'ordered_quantity' => 5, 'unit_cost' => '2.00'],
        ]);
        $this->service->markOrdered($order, $user);

        $this->expectException(InvalidPurchaseOrderException::class);
        $this->service->updateDraft($order->fresh(), $user, $supplier->id, [
            ['product_id' => $product->id, 'ordered_quantity' => 8, 'unit_cost' => '2.00'],
        ]);
    }

    public function test_draft_can_be_marked_ordered_without_affecting_inventory(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        $order = $this->service->createDraft($user, $supplier->id, [
            ['product_id' => $product->id, 'ordered_quantity' => 40, 'unit_cost' => '3.00'],
        ]);

        $ordered = $this->service->markOrdered($order, $user);

        $this->assertSame(PurchaseOrderStatus::Ordered, $ordered->status);
        $this->assertSame(0, $product->fresh()->quantity_on_hand);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_partial_and_full_receiving_updates_status_inventory_and_history(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create(['name' => 'Wireless Mouse']);

        $order = $this->service->createDraft($user, $supplier->id, [
            ['product_id' => $product->id, 'ordered_quantity' => 100, 'unit_cost' => '12.50'],
        ]);
        $order = $this->service->markOrdered($order, $user);
        $itemId = $order->items->first()->id;

        $firstReceipt = $this->service->receive(
            $order,
            [$itemId => 40],
            $user,
            'receipt-partial-1',
        );

        $order->refresh()->load('items');
        $item = $order->items->first();

        $this->assertSame(PurchaseOrderStatus::PartiallyReceived, $order->status);
        $this->assertSame(40, $item->received_quantity);
        $this->assertSame(60, $item->outstandingQuantity());
        $this->assertSame(40, $product->fresh()->quantity_on_hand);
        $this->assertSame(1, $firstReceipt->items()->count());
        $this->assertSame(1, StockMovement::query()->where('type', StockMovementType::StockIn)->count());

        $secondReceipt = $this->service->receive(
            $order,
            [$itemId => 60],
            $user,
            'receipt-partial-2',
        );

        $order->refresh()->load('items');
        $item = $order->items->first();

        $this->assertSame(PurchaseOrderStatus::Received, $order->status);
        $this->assertSame(100, $item->received_quantity);
        $this->assertSame(0, $item->outstandingQuantity());
        $this->assertSame(100, $product->fresh()->quantity_on_hand);
        $this->assertSame(2, PurchaseOrderReceipt::query()->count());
        $this->assertSame(2, StockMovement::query()->count());
        $this->assertNotNull($secondReceipt->items->first()->stock_movement_id);
    }

    public function test_over_receiving_zero_and_invalid_receipts_are_rejected(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        $order = $this->service->createDraft($user, $supplier->id, [
            ['product_id' => $product->id, 'ordered_quantity' => 10, 'unit_cost' => '1.00'],
        ]);
        $order = $this->service->markOrdered($order, $user);
        $itemId = $order->items->first()->id;

        try {
            $this->service->receive($order, [$itemId => 0], $user, 'receipt-zero');
            $this->fail('Zero receipt quantity should fail.');
        } catch (InvalidPurchaseOrderException) {
            $this->assertSame(0, $product->fresh()->quantity_on_hand);
        }

        try {
            $this->service->receive($order, [$itemId => 11], $user, 'receipt-over');
            $this->fail('Over-receiving should fail.');
        } catch (InvalidPurchaseOrderException) {
            $this->assertSame(0, $product->fresh()->quantity_on_hand);
            $this->assertDatabaseCount('purchase_order_receipts', 0);
        }

        $this->service->receive($order, [$itemId => 10], $user, 'receipt-complete');

        $this->expectException(InvalidPurchaseOrderException::class);
        $this->service->receive($order->fresh(), [$itemId => 1], $user, 'receipt-after-complete');
    }

    public function test_cancelled_orders_cannot_receive_inventory(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        $order = $this->service->createDraft($user, $supplier->id, [
            ['product_id' => $product->id, 'ordered_quantity' => 5, 'unit_cost' => '1.00'],
        ]);
        $order = $this->service->markOrdered($order, $user);
        $this->service->cancel($order, $user);

        $this->expectException(InvalidPurchaseOrderException::class);
        $this->service->receive($order->fresh(), [$order->items->first()->id => 1], $user, 'receipt-cancelled');
    }

    public function test_orders_with_received_inventory_cannot_be_cancelled(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        $order = $this->service->createDraft($user, $supplier->id, [
            ['product_id' => $product->id, 'ordered_quantity' => 10, 'unit_cost' => '1.00'],
        ]);
        $order = $this->service->markOrdered($order, $user);
        $this->service->receive($order, [$order->items->first()->id => 4], $user, 'receipt-cancel-block');

        $this->expectException(InvalidPurchaseOrderException::class);
        $this->service->cancel($order->fresh(), $user);
    }

    public function test_receipt_idempotency_does_not_double_apply_inventory(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        $order = $this->service->createDraft($user, $supplier->id, [
            ['product_id' => $product->id, 'ordered_quantity' => 20, 'unit_cost' => '1.00'],
        ]);
        $order = $this->service->markOrdered($order, $user);
        $itemId = $order->items->first()->id;

        $first = $this->service->receive($order, [$itemId => 8], $user, 'idempotent-key-1');
        $second = $this->service->receive($order->fresh(), [$itemId => 8], $user, 'idempotent-key-1');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(8, $product->fresh()->quantity_on_hand);
        $this->assertSame(1, StockMovement::query()->count());
        $this->assertSame(1, PurchaseOrderReceipt::query()->count());
        $this->assertSame(
            1,
            AuditLog::query()
                ->where('entity_type', PurchaseOrderReceipt::class)
                ->where('action', AuditAction::Created)
                ->count()
        );
    }

    public function test_failed_receive_rolls_back_inventory_and_receipts(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();

        $order = $this->service->createDraft($user, $supplier->id, [
            ['product_id' => $productA->id, 'ordered_quantity' => 5, 'unit_cost' => '1.00'],
            ['product_id' => $productB->id, 'ordered_quantity' => 5, 'unit_cost' => '1.00'],
        ]);
        $order = $this->service->markOrdered($order, $user);
        $items = $order->items->keyBy('product_id');

        try {
            $this->service->receive(
                $order,
                [
                    $items[$productA->id]->id => 5,
                    $items[$productB->id]->id => 9,
                ],
                $user,
                'receipt-rollback',
            );
            $this->fail('Over-receive on second line should fail.');
        } catch (InvalidPurchaseOrderException) {
            $this->assertSame(0, $productA->fresh()->quantity_on_hand);
            $this->assertSame(0, $productB->fresh()->quantity_on_hand);
            $this->assertDatabaseCount('purchase_order_receipts', 0);
            $this->assertDatabaseCount('stock_movements', 0);
            $this->assertSame(0, $order->fresh()->items()->sum('received_quantity'));
            $this->assertSame(PurchaseOrderStatus::Ordered, $order->fresh()->status);
        }
    }

    public function test_historical_unit_costs_are_preserved_when_product_cost_changes(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create(['cost_price' => '10.00']);

        $order = $this->service->createDraft($user, $supplier->id, [
            ['product_id' => $product->id, 'ordered_quantity' => 4, 'unit_cost' => '11.25'],
        ]);

        $product->update(['cost_price' => '99.99']);

        $this->assertSame('11.25', $order->fresh()->items->first()->unit_cost);
        $this->assertSame('45.00', $order->fresh()->load('items')->totalAmount());
        $this->assertSame('99.99', $product->fresh()->cost_price);
    }

    public function test_purchase_order_audit_logging_covers_lifecycle_events(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        $order = $this->service->createDraft($user, $supplier->id, [
            ['product_id' => $product->id, 'ordered_quantity' => 10, 'unit_cost' => '2.50'],
        ], notes: 'Draft notes');

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => PurchaseOrder::class,
            'entity_id' => $order->id,
            'action' => AuditAction::Created->value,
            'user_id' => $user->id,
        ]);

        $this->service->updateDraft($order, $user, $supplier->id, [
            ['product_id' => $product->id, 'ordered_quantity' => 12, 'unit_cost' => '2.50'],
        ], notes: 'Edited draft');

        $order = $this->service->markOrdered($order->fresh(), $user);
        $this->service->receive($order, [$order->items->first()->id => 12], $user, 'audit-receipt-1');

        $this->assertTrue(
            AuditLog::query()
                ->where('entity_type', PurchaseOrder::class)
                ->where('entity_id', $order->id)
                ->where('action', AuditAction::Updated)
                ->where('after_values->status', PurchaseOrderStatus::Ordered->value)
                ->exists()
        );

        $this->assertTrue(
            AuditLog::query()
                ->where('entity_type', PurchaseOrder::class)
                ->where('entity_id', $order->id)
                ->where('action', AuditAction::Updated)
                ->where('after_values->status', PurchaseOrderStatus::Received->value)
                ->exists()
        );

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => PurchaseOrderReceipt::class,
            'action' => AuditAction::Created->value,
        ]);
    }

    public function test_referenced_suppliers_and_products_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        $this->service->createDraft($user, $supplier->id, [
            ['product_id' => $product->id, 'ordered_quantity' => 3, 'unit_cost' => '1.00'],
        ]);

        try {
            app(SupplierCatalogService::class)->delete($supplier, $user);
            $this->fail('Supplier delete should be blocked.');
        } catch (InvalidPurchaseOrderException) {
            $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
        }

        try {
            app(ProductCatalogService::class)->delete($product, $user);
            $this->fail('Product delete should be blocked.');
        } catch (InvalidPurchaseOrderException) {
            $this->assertDatabaseHas('products', ['id' => $product->id]);
        }

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('suppliers')->where('id', $supplier->id)->delete();
    }

    public function test_draft_and_ordered_orders_without_receipts_can_be_cancelled(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        $draft = $this->service->createDraft($user, $supplier->id, [
            ['product_id' => $product->id, 'ordered_quantity' => 2, 'unit_cost' => '1.00'],
        ]);
        $this->service->cancel($draft, $user);
        $this->assertSame(PurchaseOrderStatus::Cancelled, $draft->fresh()->status);

        $ordered = $this->service->createDraft($user, $supplier->id, [
            ['product_id' => $product->id, 'ordered_quantity' => 2, 'unit_cost' => '1.00'],
        ]);
        $this->service->markOrdered($ordered, $user);
        $this->service->cancel($ordered->fresh(), $user);
        $this->assertSame(PurchaseOrderStatus::Cancelled, $ordered->fresh()->status);
        $this->assertSame(0, $product->fresh()->quantity_on_hand);
    }

    public function test_noop_draft_update_does_not_write_duplicate_audit(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        $order = $this->service->createDraft(
            $user,
            $supplier->id,
            [['product_id' => $product->id, 'ordered_quantity' => 5, 'unit_cost' => '2.00']],
            '2026-10-19',
            null,
            null,
        );

        $beforeCount = AuditLog::query()->count();

        $this->service->updateDraft(
            $order,
            $user,
            $supplier->id,
            [['product_id' => $product->id, 'ordered_quantity' => 5, 'unit_cost' => '2.00']],
            '2026-10-19',
            null,
            null,
        );

        $this->assertSame($beforeCount, AuditLog::query()->count());
    }
}
