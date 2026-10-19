<?php

namespace Tests\Feature\Reports;

use App\Enums\StockMovementType;
use App\Livewire\Reports\LowStock;
use App\Livewire\Reports\Purchasing;
use App\Livewire\Reports\StockMovements;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\PurchaseOrderService;
use App\Services\Stock\StockMovementService;
use App\Support\CsvExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CsvExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_exporter_escapes_formula_injection(): void
    {
        $this->assertSame("'=CMD()", CsvExporter::escapeCell('=CMD()'));
        $this->assertSame("'+1+1", CsvExporter::escapeCell('+1+1'));
        $this->assertSame("'-2", CsvExporter::escapeCell('-2'));
        $this->assertSame("'@sum", CsvExporter::escapeCell('@sum'));
        $this->assertSame('Safe Name', CsvExporter::escapeCell('Safe Name'));
        $this->assertSame('', CsvExporter::escapeCell(null));
        $this->assertSame('12', CsvExporter::escapeCell(12));
    }

    public function test_guests_cannot_export_reports(): void
    {
        $this->get(route('reports.low-stock'))->assertRedirect(route('login'));
        $this->get(route('reports.stock-movements'))->assertRedirect(route('login'));
        $this->get(route('reports.purchasing'))->assertRedirect(route('login'));
    }

    public function test_low_stock_csv_export_respects_filters_and_escapes_formulas(): void
    {
        $user = User::factory()->create();

        $formula = Product::factory()->create([
            'name' => '=HYPERLINK("http://evil")',
            'sku' => '+SKU',
            'reorder_level' => 10,
            'is_active' => true,
        ]);
        $formula->forceFill(['quantity_on_hand' => 0])->save();

        $other = Product::factory()->create([
            'name' => 'Other Low',
            'sku' => 'SKU-OTHER',
            'reorder_level' => 5,
            'is_active' => true,
        ]);
        $other->forceFill(['quantity_on_hand' => 1])->save();

        $inactive = Product::factory()->inactive()->create([
            'name' => 'Inactive Low Export',
            'sku' => 'SKU-INACT-EXP',
            'reorder_level' => 4,
        ]);
        $inactive->forceFill(['quantity_on_hand' => 0])->save();

        $component = Livewire::actingAs($user)
            ->test(LowStock::class)
            ->set('search', '+SKU')
            ->call('export')
            ->assertFileDownloaded();

        $content = base64_decode((string) data_get($component->effects, 'download.content'));
        $filename = (string) data_get($component->effects, 'download.name');

        $this->assertStringEndsWith('.csv', $filename);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('Product,SKU,"Quantity on hand","Reorder level","Suggested replenishment",Status', $content);
        $this->assertStringContainsString("'=HYPERLINK", $content);
        $this->assertStringContainsString("'+SKU", $content);
        $this->assertStringContainsString(',10,10,Active', $content);
        $this->assertStringNotContainsString('Other Low', $content);
        $this->assertStringNotContainsString('Inactive Low Export', $content);
    }

    public function test_stock_movement_csv_export_respects_filters(): void
    {
        $user = User::factory()->create(['name' => 'Export User']);
        $alpha = Product::factory()->create(['name' => 'Export Alpha', 'sku' => 'SKU-EA']);
        $beta = Product::factory()->create(['name' => 'Export Beta', 'sku' => 'SKU-EB']);
        $service = app(StockMovementService::class);

        $service->apply($alpha, StockMovementType::StockIn, 4, $user, '=INJECT()');
        $service->apply($beta, StockMovementType::StockIn, 2, $user, 'Beta seed');
        $service->apply($beta->fresh(), StockMovementType::StockOut, 1, $user, 'Beta export reason');

        $component = Livewire::actingAs($user)
            ->test(StockMovements::class)
            ->set('type', StockMovementType::StockIn->value)
            ->call('export')
            ->assertFileDownloaded();

        $content = base64_decode((string) data_get($component->effects, 'download.content'));

        $this->assertStringContainsString('Timestamp,Product,SKU,Type,"Quantity change",Before,After,Reason,User', $content);
        $this->assertStringContainsString('Export Alpha', $content);
        $this->assertStringContainsString("'=INJECT()", $content);
        $this->assertStringNotContainsString('Beta export reason', $content);
    }

    public function test_purchasing_csv_export_respects_filters_and_uses_historical_totals(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create(['name' => 'CSV Supplier']);
        $product = Product::factory()->create(['cost_price' => '50.00']);
        $service = app(PurchaseOrderService::class);

        $keep = $service->createDraft($user, $supplier->id, [
            ['product_id' => $product->id, 'ordered_quantity' => 4, 'unit_cost' => '11.25'],
        ], '2026-10-08');
        $keep = $service->markOrdered($keep, $user);

        $skip = $service->createDraft($user, $supplier->id, [
            ['product_id' => $product->id, 'ordered_quantity' => 9, 'unit_cost' => '3.00'],
        ], '2026-09-01');
        $service->markOrdered($skip, $user);

        $component = Livewire::actingAs($user)
            ->test(Purchasing::class)
            ->set('dateFrom', '2026-10-01')
            ->set('dateTo', '2026-10-31')
            ->call('export')
            ->assertFileDownloaded();

        $content = base64_decode((string) data_get($component->effects, 'download.content'));

        $this->assertStringContainsString(
            '"PO number",Supplier,Status,"Order date","Expected delivery","Ordered total","Received qty","Outstanding qty"',
            $content,
        );
        $this->assertStringContainsString($keep->number, $content);
        $this->assertStringContainsString('45.00', $content);
        $this->assertStringContainsString('CSV Supplier', $content);
        $this->assertStringNotContainsString($skip->number, $content);
    }

    public function test_csv_exports_do_not_mutate_inventory_or_purchase_orders(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['reorder_level' => 5]);
        $product->forceFill(['quantity_on_hand' => 1])->save();
        $supplier = Supplier::factory()->create();
        $order = app(PurchaseOrderService::class)->createDraft($user, $supplier->id, [
            ['product_id' => $product->id, 'ordered_quantity' => 3, 'unit_cost' => '2.00'],
        ]);

        Livewire::actingAs($user)->test(LowStock::class)->call('export');
        Livewire::actingAs($user)->test(StockMovements::class)->call('export');
        Livewire::actingAs($user)->test(Purchasing::class)->call('export');

        $this->assertSame(1, $product->fresh()->quantity_on_hand);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame($order->status->value, $order->fresh()->status->value);
        $this->assertSame(0, (int) $order->fresh()->items()->sum('received_quantity'));
    }
}
