<?php

namespace Tests\Feature\Reports;

use App\Enums\PurchaseOrderStatus;
use App\Livewire\Reports\Purchasing;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\PurchaseOrderService;
use App\Services\Reports\PurchasingReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PurchasingReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_purchasing_report(): void
    {
        $this->get(route('reports.purchasing'))
            ->assertRedirect(route('login'));
    }

    public function test_purchasing_summary_statuses_partial_receiving_and_historical_costs(): void
    {
        $user = User::factory()->create();
        $supplierA = Supplier::factory()->create(['name' => 'Acme Supplies', 'code' => 'ACME']);
        $supplierB = Supplier::factory()->create(['name' => 'Beta Goods', 'code' => 'BETA']);
        $product = Product::factory()->create([
            'name' => 'Report Mouse',
            'cost_price' => '99.00',
        ]);
        $service = app(PurchaseOrderService::class);

        $draft = $service->createDraft($user, $supplierA->id, [
            ['product_id' => $product->id, 'ordered_quantity' => 10, 'unit_cost' => '5.00'],
        ], '2026-10-01');

        $ordered = $service->createDraft($user, $supplierA->id, [
            ['product_id' => $product->id, 'ordered_quantity' => 20, 'unit_cost' => '12.50'],
        ], '2026-10-05');
        $ordered = $service->markOrdered($ordered, $user);

        $partial = $service->createDraft($user, $supplierB->id, [
            ['product_id' => $product->id, 'ordered_quantity' => 100, 'unit_cost' => '8.00'],
        ], '2026-10-10');
        $partial = $service->markOrdered($partial, $user);
        $partialItemId = $partial->items->first()->id;
        $service->receive($partial, [$partialItemId => 40], $user, 'receipt-report-1');

        $cancelled = $service->createDraft($user, $supplierB->id, [
            ['product_id' => $product->id, 'ordered_quantity' => 50, 'unit_cost' => '7.00'],
        ], '2026-10-12');
        $service->cancel($cancelled, $user);

        // Current product cost must not affect historical PO totals.
        $product->update(['cost_price' => '1.00']);

        $summary = app(PurchasingReportService::class)->summary();

        $this->assertSame(4, $summary['total_orders']);
        $this->assertSame(1, $summary['draft_orders']);
        $this->assertSame(1, $summary['ordered_orders']);
        $this->assertSame(1, $summary['partially_received_orders']);
        $this->assertSame(0, $summary['received_orders']);
        $this->assertSame(1, $summary['cancelled_orders']);
        // Committed: ordered 20*12.50=250 + partial 100*8.00=800 = 1050.00
        $this->assertSame('1050.00', $summary['committed_ordered_value']);
        $this->assertSame(40, $summary['committed_received_quantity']);
        // Outstanding: ordered 20 + partial 60 = 80
        $this->assertSame(80, $summary['committed_outstanding_quantity']);

        Livewire::actingAs($user)
            ->test(Purchasing::class)
            ->assertSee('Acme Supplies', false)
            ->assertSee('Beta Goods', false)
            ->assertSee('$1050.00', false)
            ->assertSee('committed orders only', false)
            ->assertSee($draft->number, false)
            ->assertSee($ordered->number, false);

        $supplierSummary = app(PurchasingReportService::class)->supplierSummary();
        $acme = $supplierSummary->firstWhere('supplier_id', $supplierA->id);
        $beta = $supplierSummary->firstWhere('supplier_id', $supplierB->id);

        $this->assertSame(2, $acme['order_count']);
        $this->assertSame('250.00', $acme['committed_ordered_value']);
        $this->assertSame(2, $beta['order_count']);
        $this->assertSame('800.00', $beta['committed_ordered_value']);
    }

    public function test_purchasing_filters_search_date_range_supplier_and_status(): void
    {
        $user = User::factory()->create();
        $supplierA = Supplier::factory()->create(['name' => 'North Vendor']);
        $supplierB = Supplier::factory()->create(['name' => 'South Vendor']);
        $product = Product::factory()->create();
        $service = app(PurchaseOrderService::class);

        $early = $service->createDraft($user, $supplierA->id, [
            ['product_id' => $product->id, 'ordered_quantity' => 1, 'unit_cost' => '1.00'],
        ], '2026-09-01');
        $early = $service->markOrdered($early, $user);

        $late = $service->createDraft($user, $supplierB->id, [
            ['product_id' => $product->id, 'ordered_quantity' => 2, 'unit_cost' => '2.00'],
        ], '2026-10-19');

        Livewire::actingAs($user)
            ->test(Purchasing::class)
            ->set('dateFrom', '2026-10-01')
            ->set('dateTo', '2026-10-19')
            ->assertSee($late->number, false)
            ->assertDontSee($early->number, false);

        Livewire::actingAs($user)
            ->test(Purchasing::class)
            ->set('supplierId', (string) $supplierA->id)
            ->assertSee($early->number, false)
            ->assertDontSee($late->number, false);

        Livewire::actingAs($user)
            ->test(Purchasing::class)
            ->set('status', PurchaseOrderStatus::Draft->value)
            ->assertSee($late->number, false)
            ->assertDontSee($early->number, false);

        Livewire::actingAs($user)
            ->test(Purchasing::class)
            ->set('search', $early->number)
            ->assertSee($early->number, false)
            ->assertDontSee($late->number, false);
    }

    public function test_purchasing_report_paginates_and_empty_state(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();
        $service = app(PurchaseOrderService::class);

        for ($i = 1; $i <= 16; $i++) {
            $service->createDraft($user, $supplier->id, [
                ['product_id' => $product->id, 'ordered_quantity' => 1, 'unit_cost' => '1.00'],
            ], '2026-10-'.sprintf('%02d', min($i, 28)));
        }

        Livewire::actingAs($user)
            ->test(Purchasing::class)
            ->assertSee('aria-label="Pagination Navigation"', false)
            ->assertSee('PO-000016', false)
            ->assertDontSee('PO-000001</', false)
            ->call('nextPage')
            ->assertSee('PO-000001', false);

        Livewire::actingAs($user)
            ->test(Purchasing::class)
            ->set('search', 'NO-SUCH-PO')
            ->assertSee('No purchase orders match', false);
    }

    public function test_purchasing_report_is_read_only(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();
        $service = app(PurchaseOrderService::class);

        $order = $service->createDraft($user, $supplier->id, [
            ['product_id' => $product->id, 'ordered_quantity' => 5, 'unit_cost' => '3.00'],
        ]);
        $order = $service->markOrdered($order, $user);

        Livewire::actingAs($user)->test(Purchasing::class)->assertOk();

        $this->assertSame(PurchaseOrderStatus::Ordered, $order->fresh()->status);
        $this->assertSame(0, $product->fresh()->quantity_on_hand);
        $this->assertSame(0, (int) $order->fresh()->items()->sum('received_quantity'));
        $this->assertSame(1, PurchaseOrder::query()->count());
    }
}
