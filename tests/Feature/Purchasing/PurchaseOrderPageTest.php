<?php

namespace Tests\Feature\Purchasing;

use App\Enums\AuditAction;
use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementType;
use App\Livewire\PurchaseOrders\Index;
use App\Livewire\PurchaseOrders\Show;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderReceipt;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PurchaseOrderPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_purchase_orders_pages(): void
    {
        $this->get(route('purchase-orders.index'))
            ->assertRedirect(route('login'));

        $order = $this->makeOrderedPurchaseOrder();

        $this->get(route('purchase-orders.show', $order))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_listing_and_details(): void
    {
        $user = User::factory()->create();
        $order = $this->makeDraftPurchaseOrder($user);

        $this->actingAs($user)
            ->get(route('purchase-orders.index'))
            ->assertOk()
            ->assertSee('Purchase Orders', false)
            ->assertSee($order->number, false);

        $this->actingAs($user)
            ->get(route('purchase-orders.show', $order))
            ->assertOk()
            ->assertSee($order->number, false)
            ->assertSee($order->supplier->name);
    }

    public function test_purchase_orders_can_be_searched_and_filtered_by_status(): void
    {
        $user = User::factory()->create();
        $alphaSupplier = Supplier::factory()->create(['name' => 'Alpha Vendor', 'code' => 'ALPHA']);
        $betaSupplier = Supplier::factory()->create(['name' => 'Beta Vendor', 'code' => 'BETA']);
        $product = Product::factory()->create();

        $alpha = app(PurchaseOrderService::class)->createDraft($user, $alphaSupplier->id, [
            ['product_id' => $product->id, 'ordered_quantity' => 5, 'unit_cost' => '1.00'],
        ]);
        $beta = app(PurchaseOrderService::class)->createDraft($user, $betaSupplier->id, [
            ['product_id' => $product->id, 'ordered_quantity' => 5, 'unit_cost' => '1.00'],
        ]);
        app(PurchaseOrderService::class)->markOrdered($beta, $user);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->set('search', 'Alpha')
            ->assertSee($alpha->number, false)
            ->assertDontSee($beta->number, false);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->set('status', PurchaseOrderStatus::Ordered->value)
            ->assertSee($beta->number, false)
            ->assertDontSee($alpha->number, false);
    }

    public function test_purchase_orders_are_paginated(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();
        $service = app(PurchaseOrderService::class);

        for ($i = 0; $i < 11; $i++) {
            $service->createDraft($user, $supplier->id, [
                ['product_id' => $product->id, 'ordered_quantity' => 1, 'unit_cost' => '1.00'],
            ]);
        }

        Livewire::actingAs($user)
            ->test(Index::class)
            ->assertSee('aria-label="Pagination Navigation"', false)
            ->call('nextPage')
            ->assertHasNoErrors();
    }

    public function test_draft_purchase_order_can_be_created_with_multiple_lines(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $mouse = Product::factory()->create(['name' => 'Wireless Mouse']);
        $keyboard = Product::factory()->create(['name' => 'Keyboard']);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('create')
            ->set('supplier_id', (string) $supplier->id)
            ->set('order_date', '2026-10-19')
            ->set('lines', [
                ['product_id' => (string) $mouse->id, 'ordered_quantity' => '10', 'unit_cost' => '12.50'],
                ['product_id' => (string) $keyboard->id, 'ordered_quantity' => '4', 'unit_cost' => '20.00'],
            ])
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('created as draft', false);

        $order = PurchaseOrder::query()->firstOrFail();
        $this->assertSame(PurchaseOrderStatus::Draft, $order->status);
        $this->assertCount(2, $order->items);
        $this->assertSame('205.00', $order->totalAmount());
        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => PurchaseOrder::class,
            'entity_id' => $order->id,
            'action' => AuditAction::Created->value,
        ]);
    }

    public function test_duplicate_products_are_rejected_on_create(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('create')
            ->set('supplier_id', (string) $supplier->id)
            ->set('order_date', '2026-10-19')
            ->set('lines', [
                ['product_id' => (string) $product->id, 'ordered_quantity' => '2', 'unit_cost' => '1.00'],
                ['product_id' => (string) $product->id, 'ordered_quantity' => '3', 'unit_cost' => '1.00'],
            ])
            ->call('save')
            ->assertHasErrors(['lines']);

        $this->assertDatabaseCount('purchase_orders', 0);
    }

    public function test_draft_purchase_order_can_be_edited(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();

        $order = app(PurchaseOrderService::class)->createDraft($user, $supplier->id, [
            ['product_id' => $productA->id, 'ordered_quantity' => 5, 'unit_cost' => '2.00'],
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('edit', $order->id)
            ->set('lines', [
                ['product_id' => (string) $productB->id, 'ordered_quantity' => '8', 'unit_cost' => '3.25'],
            ])
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('statusMessage', 'Purchase order updated successfully.');

        $order->refresh()->load('items');
        $this->assertSame($productB->id, $order->items->first()->product_id);
        $this->assertSame(8, $order->items->first()->ordered_quantity);
        $this->assertSame('3.25', $order->items->first()->unit_cost);
    }

    public function test_non_draft_orders_cannot_be_edited_from_ui(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrderedPurchaseOrder($user);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('edit', $order->id)
            ->assertSet('showForm', false)
            ->assertSet('errorMessage', 'Only draft purchase orders can be edited.');
    }

    public function test_draft_can_be_marked_ordered_and_cancelled_from_details(): void
    {
        $user = User::factory()->create();
        $order = $this->makeDraftPurchaseOrder($user);

        Livewire::actingAs($user)
            ->test(Show::class, ['purchaseOrder' => $order])
            ->call('confirmMarkOrdered')
            ->call('markOrdered')
            ->assertHasNoErrors()
            ->assertSee('marked as ordered', false);

        $this->assertSame(PurchaseOrderStatus::Ordered, $order->fresh()->status);

        Livewire::actingAs($user)
            ->test(Show::class, ['purchaseOrder' => $order->fresh()])
            ->call('confirmCancelOrder')
            ->call('cancelOrder')
            ->assertHasNoErrors()
            ->assertSee('cancelled', false);

        $this->assertSame(PurchaseOrderStatus::Cancelled, $order->fresh()->status);
    }

    public function test_partial_and_full_receiving_through_ui(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrderedPurchaseOrder($user, orderedQuantity: 100);
        $itemId = $order->items->first()->id;
        $product = $order->items->first()->product;

        $component = Livewire::actingAs($user)
            ->test(Show::class, ['purchaseOrder' => $order])
            ->call('openReceiveForm')
            ->assertSet('showReceiveForm', true);

        $receiptKey = $component->get('receiptKey');
        $this->assertNotSame('', $receiptKey);

        $component
            ->set('receiveQuantities.'.$itemId, '40')
            ->call('reviewReceive')
            ->assertSet('confirmingReceive', true)
            ->assertSet('receiptKey', $receiptKey)
            ->call('submitReceive')
            ->assertHasNoErrors()
            ->assertSee('Partially Received', false);

        $order->refresh()->load('items');
        $this->assertSame(PurchaseOrderStatus::PartiallyReceived, $order->status);
        $this->assertSame(40, $order->items->first()->received_quantity);
        $this->assertSame(40, $product->fresh()->quantity_on_hand);
        $this->assertSame(1, PurchaseOrderReceipt::query()->count());
        $this->assertSame(1, StockMovement::query()->where('type', StockMovementType::StockIn)->count());

        Livewire::actingAs($user)
            ->test(Show::class, ['purchaseOrder' => $order->fresh()])
            ->call('openReceiveForm')
            ->set('receiveQuantities.'.$itemId, '60')
            ->call('reviewReceive')
            ->call('submitReceive')
            ->assertHasNoErrors()
            ->assertSee('Received', false);

        $this->assertSame(PurchaseOrderStatus::Received, $order->fresh()->status);
        $this->assertSame(100, $product->fresh()->quantity_on_hand);
        $this->assertSame(2, PurchaseOrderReceipt::query()->count());
        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => PurchaseOrderReceipt::class,
            'action' => AuditAction::Created->value,
        ]);
    }

    public function test_over_receiving_and_invalid_quantities_are_rejected(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrderedPurchaseOrder($user, orderedQuantity: 10);
        $itemId = $order->items->first()->id;

        Livewire::actingAs($user)
            ->test(Show::class, ['purchaseOrder' => $order])
            ->call('openReceiveForm')
            ->set('receiveQuantities.'.$itemId, '-1')
            ->call('reviewReceive')
            ->assertHasErrors(['receiveQuantities.'.$itemId]);

        Livewire::actingAs($user)
            ->test(Show::class, ['purchaseOrder' => $order])
            ->call('openReceiveForm')
            ->set('receiveQuantities.'.$itemId, '11')
            ->call('reviewReceive')
            ->assertHasErrors(['receiveQuantities.'.$itemId]);

        Livewire::actingAs($user)
            ->test(Show::class, ['purchaseOrder' => $order])
            ->call('openReceiveForm')
            ->call('reviewReceive')
            ->assertHasErrors(['receiveQuantities']);

        $this->assertDatabaseCount('purchase_order_receipts', 0);
        $this->assertSame(0, $order->items->first()->product->fresh()->quantity_on_hand);
    }

    public function test_duplicate_submission_keeps_the_same_receipt_key(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrderedPurchaseOrder($user, orderedQuantity: 20);
        $itemId = $order->items->first()->id;
        $product = $order->items->first()->product;

        $component = Livewire::actingAs($user)
            ->test(Show::class, ['purchaseOrder' => $order])
            ->call('openReceiveForm');

        $receiptKey = $component->get('receiptKey');

        $component
            ->set('receiveQuantities.'.$itemId, '5')
            ->call('reviewReceive')
            ->call('submitReceive')
            ->assertHasNoErrors();

        $this->assertSame(5, $product->fresh()->quantity_on_hand);

        // Simulate a retry of the same intended receipt using the retained service key.
        app(PurchaseOrderService::class)->receive(
            $order->fresh(),
            [$itemId => 5],
            $user,
            $receiptKey,
        );

        $this->assertSame(5, $product->fresh()->quantity_on_hand);
        $this->assertSame(1, PurchaseOrderReceipt::query()->count());
        $this->assertSame(1, StockMovement::query()->count());
    }

    public function test_receipt_history_and_stock_movement_links_are_visible(): void
    {
        $user = User::factory()->create(['name' => 'Receiver User']);
        $order = $this->makeOrderedPurchaseOrder($user, orderedQuantity: 8);
        $itemId = $order->items->first()->id;

        Livewire::actingAs($user)
            ->test(Show::class, ['purchaseOrder' => $order])
            ->call('openReceiveForm')
            ->set('receiveQuantities.'.$itemId, '3')
            ->call('reviewReceive')
            ->call('submitReceive');

        $movementId = StockMovement::query()->value('id');
        $receiptKey = PurchaseOrderReceipt::query()->value('receipt_key');

        Livewire::actingAs($user)
            ->test(Show::class, ['purchaseOrder' => $order->fresh()])
            ->assertSee('Receipt history', false)
            ->assertSee($receiptKey, false)
            ->assertSee('Receiver User', false)
            ->assertSee('Stock movement #'.$movementId, false)
            ->assertSee('3 received', false);
    }

    public function test_guests_cannot_mutate_purchase_orders_through_livewire(): void
    {
        $order = $this->makeDraftPurchaseOrder();

        Livewire::test(Index::class)
            ->call('create')
            ->assertForbidden();

        Livewire::test(Show::class, ['purchaseOrder' => $order])
            ->call('confirmMarkOrdered')
            ->assertForbidden();
    }

    public function test_validation_errors_are_shown_for_incomplete_drafts(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('create')
            ->set('supplier_id', '')
            ->set('order_date', '')
            ->set('lines', [
                ['product_id' => '', 'ordered_quantity' => '', 'unit_cost' => ''],
            ])
            ->call('save')
            ->assertHasErrors(['supplier_id', 'order_date', 'lines.0.product_id', 'lines.0.ordered_quantity', 'lines.0.unit_cost']);
    }

    public function test_orders_with_received_inventory_cannot_be_cancelled_from_ui(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrderedPurchaseOrder($user, orderedQuantity: 10);
        $itemId = $order->items->first()->id;

        app(PurchaseOrderService::class)->receive($order, [$itemId => 2], $user, 'ui-cancel-block');

        Livewire::actingAs($user)
            ->test(Show::class, ['purchaseOrder' => $order->fresh()])
            ->call('confirmCancelOrder')
            ->call('cancelOrder')
            ->assertSet('errorMessage', 'Only draft or ordered purchase orders without receipts can be cancelled.');

        $this->assertSame(PurchaseOrderStatus::PartiallyReceived, $order->fresh()->status);
    }

    protected function makeDraftPurchaseOrder(?User $user = null): PurchaseOrder
    {
        $user ??= User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        return app(PurchaseOrderService::class)->createDraft($user, $supplier->id, [
            ['product_id' => $product->id, 'ordered_quantity' => 5, 'unit_cost' => '2.00'],
        ]);
    }

    protected function makeOrderedPurchaseOrder(?User $user = null, int $orderedQuantity = 10): PurchaseOrder
    {
        $user ??= User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        $order = app(PurchaseOrderService::class)->createDraft($user, $supplier->id, [
            ['product_id' => $product->id, 'ordered_quantity' => $orderedQuantity, 'unit_cost' => '5.00'],
        ]);

        return app(PurchaseOrderService::class)->markOrdered($order, $user)->load(['items.product', 'supplier']);
    }
}
