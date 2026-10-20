<?php

namespace Tests\Feature\Ui;

use App\Enums\PurchaseOrderStatus;
use App\Livewire\Products\Index as ProductsIndex;
use App\Livewire\PurchaseOrders\Index as PurchaseOrdersIndex;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\PurchaseOrderService;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DataPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_table_shows_formatted_money_stock_level_and_thumbnail_markup(): void
    {
        $user = User::factory()->create();

        app(SettingsService::class)->update([
            'business_name' => 'StockPilot',
            'currency_symbol' => '$',
            'default_reorder_level' => 0,
            'default_per_page' => 10,
            'date_format' => 'Y-m-d',
        ], $user);

        $product = Product::factory()->create([
            'name' => 'Display Widget',
            'sku' => 'SKU-DISP-1',
            'unit_price' => '19.99',
            'reorder_level' => 10,
        ]);
        $product->forceFill(['quantity_on_hand' => 3])->save();

        Livewire::actingAs($user)
            ->test(ProductsIndex::class)
            ->assertSee('Display Widget', false)
            ->assertSee('$19.99', false)
            ->assertSee('Low stock', false)
            ->assertSeeHtml('caption class="sr-only">Products inventory listing')
            ->assertSeeHtml('overflow-x-auto')
            ->assertSeeHtml('tabular-nums');
    }

    public function test_purchase_order_status_badges_render_consistent_labels(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        $draft = app(PurchaseOrderService::class)->createDraft($user, $supplier->id, [
            ['product_id' => $product->id, 'ordered_quantity' => 2, 'unit_cost' => '1.25'],
        ]);

        $ordered = app(PurchaseOrderService::class)->createDraft($user, $supplier->id, [
            ['product_id' => $product->id, 'ordered_quantity' => 2, 'unit_cost' => '1.25'],
        ]);
        $ordered = app(PurchaseOrderService::class)->markOrdered($ordered, $user);

        $this->assertSame(PurchaseOrderStatus::Draft, $draft->fresh()->status);
        $this->assertSame(PurchaseOrderStatus::Ordered, $ordered->status);

        Livewire::actingAs($user)
            ->test(PurchaseOrdersIndex::class)
            ->assertSee('Draft', false)
            ->assertSee('Ordered', false)
            ->assertSee('$2.50', false)
            ->assertSeeHtml('caption class="sr-only">Purchase orders listing');
    }

    public function test_out_of_stock_products_use_critical_label(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Empty Bin',
            'reorder_level' => 2,
        ]);
        $product->forceFill(['quantity_on_hand' => 0])->save();

        Livewire::actingAs($user)
            ->test(ProductsIndex::class)
            ->assertSee('Empty Bin', false)
            ->assertSee('Out of stock', false)
            ->assertSeeHtml('aria-label="Critical: Out of stock"');
    }
}
