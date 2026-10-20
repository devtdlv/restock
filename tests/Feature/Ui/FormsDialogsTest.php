<?php

namespace Tests\Feature\Ui;

use App\Enums\StockMovementType;
use App\Livewire\Categories\Index as CategoriesIndex;
use App\Livewire\Products\Index as ProductsIndex;
use App\Livewire\PurchaseOrders\Index as PurchaseOrdersIndex;
use App\Livewire\PurchaseOrders\Show as PurchaseOrdersShow;
use App\Livewire\Settings\Index as SettingsIndex;
use App\Livewire\Suppliers\Index as SuppliersIndex;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\PurchaseOrderService;
use App\Services\Stock\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FormsDialogsTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_create_form_uses_panel_semantics_and_required_indicators(): void
    {
        $user = User::factory()->create();
        Category::factory()->create(['name' => 'Accessories']);

        Livewire::actingAs($user)
            ->test(ProductsIndex::class)
            ->call('create')
            ->assertSeeHtml('role="region"')
            ->assertSeeHtml('id="product-form-title"')
            ->assertSeeHtml('(required)')
            ->assertSeeHtml('(optional)')
            ->assertSeeHtml('<fieldset')
            ->assertSee('Categories', false)
            ->assertSee('Product image', false)
            ->assertSee('JPEG, PNG, or WebP', false)
            ->assertDontSeeHtml('role="dialog"');
    }

    public function test_product_validation_summary_and_field_errors_render(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ProductsIndex::class)
            ->call('create')
            ->set('name', '')
            ->set('sku', '')
            ->set('unit_price', '')
            ->call('save')
            ->assertHasErrors(['name', 'sku', 'unit_price'])
            ->assertSee('Please correct the highlighted fields below.', false)
            ->assertSeeHtml('role="alert"');
    }

    public function test_product_delete_confirmation_uses_accessible_dialog_markup(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        Livewire::actingAs($user)
            ->test(ProductsIndex::class)
            ->call('confirmDeletion', $product->id)
            ->assertSeeHtml('role="dialog"')
            ->assertSeeHtml('aria-modal="true"')
            ->assertSee('Delete product?', false)
            ->assertSee('Confirm delete', false)
            ->assertSee('Cancel', false);
    }

    public function test_stock_adjustment_panel_shows_expected_quantity_preview(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Preview Widget',
            'sku' => 'SKU-PREV-1',
        ]);
        app(StockMovementService::class)->apply(
            $product,
            StockMovementType::StockIn,
            20,
            $user,
            'Seed',
        );

        Livewire::actingAs($user)
            ->test(ProductsIndex::class)
            ->call('openAdjustStock', $product->id)
            ->assertSee('Adjust stock', false)
            ->assertSee('Preview Widget', false)
            ->assertSee('Current quantity', false)
            ->assertSee('20', false)
            ->set('adjustmentType', StockMovementType::StockOut->value)
            ->set('adjustmentQuantity', '5')
            ->assertSee('Expected quantity after change', false)
            ->assertSee('15', false)
            ->assertSeeHtml('role="region"')
            ->assertDontSeeHtml('role="dialog"');
    }

    public function test_product_image_form_accepts_previewable_upload_selection(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $file = UploadedFile::fake()->image('widget.jpg', 120, 120);

        Livewire::actingAs($user)
            ->test(ProductsIndex::class)
            ->call('create')
            ->set('name', 'Image Form Product')
            ->set('sku', 'SKU-IMG-FORM-1')
            ->set('unit_price', '9.99')
            ->set('cost_price', '4.50')
            ->set('quantity_on_hand', '0')
            ->set('reorder_level', '0')
            ->set('image', $file)
            ->assertHasNoErrors('image')
            ->assertSee('Clear selection', false)
            ->assertSee('Uploading image preview', false);
    }

    public function test_category_and_supplier_delete_dialogs_use_modal_semantics(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $supplier = Supplier::factory()->create();

        Livewire::actingAs($user)
            ->test(CategoriesIndex::class)
            ->call('confirmDeletion', $category->id)
            ->assertSeeHtml('role="dialog"')
            ->assertSeeHtml('aria-modal="true"')
            ->assertSee('Delete category?', false);

        Livewire::actingAs($user)
            ->test(SuppliersIndex::class)
            ->call('confirmDeletion', $supplier->id)
            ->assertSeeHtml('role="dialog"')
            ->assertSeeHtml('aria-modal="true"')
            ->assertSee('Delete supplier?', false);
    }

    public function test_purchase_order_form_and_receive_workflow_markup(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create(['name' => 'PO Form Widget', 'sku' => 'SKU-PO-FORM']);

        Livewire::actingAs($user)
            ->test(PurchaseOrdersIndex::class)
            ->call('create')
            ->assertSeeHtml('role="region"')
            ->assertSee('Create draft purchase order', false)
            ->assertSee('Line items', false)
            ->assertSee('Grand total', false)
            ->assertSeeHtml('(required)');

        $order = app(PurchaseOrderService::class)->createDraft($user, $supplier->id, [
            ['product_id' => $product->id, 'ordered_quantity' => 10, 'unit_cost' => '2.00'],
        ]);
        $order = app(PurchaseOrderService::class)->markOrdered($order, $user);
        $itemId = $order->items->first()->id;

        Livewire::actingAs($user)
            ->test(PurchaseOrdersShow::class, ['purchaseOrder' => $order])
            ->call('confirmCancelOrder')
            ->assertSeeHtml('role="dialog"')
            ->assertSee('Cancel purchase order?', false)
            ->call('cancelCancelOrder')
            ->call('openReceiveForm')
            ->assertSeeHtml('role="region"')
            ->assertSee('Receive inventory', false)
            ->assertSee('outstanding', false)
            ->assertSee('Receipt key:', false)
            ->set('receiveQuantities.'.$itemId, '3')
            ->assertSee('After this receipt:', false)
            ->assertSee('still outstanding', false);
    }

    public function test_draft_mark_ordered_confirmation_uses_dialog_markup(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        $order = app(PurchaseOrderService::class)->createDraft($user, $supplier->id, [
            ['product_id' => $product->id, 'ordered_quantity' => 4, 'unit_cost' => '1.25'],
        ]);

        Livewire::actingAs($user)
            ->test(PurchaseOrdersShow::class, ['purchaseOrder' => $order])
            ->call('confirmMarkOrdered')
            ->assertSeeHtml('role="dialog"')
            ->assertSeeHtml('aria-modal="true"')
            ->assertSee('Mark as Ordered?', false)
            ->assertSee('Confirm submit', false)
            ->assertSee('Keep as draft', false);
    }

    public function test_settings_form_grouping_and_validation_feedback(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(SettingsIndex::class)
            ->assertSeeHtml('id="settings-business-title"')
            ->assertSeeHtml('id="settings-inventory-title"')
            ->assertSeeHtml('id="settings-display-title"')
            ->assertSee('Default low-stock threshold', false)
            ->assertSee('Currency display symbol', false)
            ->set('business_name', '')
            ->set('currency_symbol', '')
            ->call('save')
            ->assertHasErrors(['business_name', 'currency_symbol'])
            ->assertSee('Please correct the highlighted fields below.', false);
    }

    public function test_login_form_marks_required_fields(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSeeHtml('(required)')
            ->assertSeeHtml('aria-required="true"')
            ->assertSee('Forgot password?', false);
    }
}
