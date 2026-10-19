<?php

namespace Tests\Feature\Catalog;

use App\Enums\AuditAction;
use App\Livewire\Products\Index as ProductsIndex;
use App\Livewire\PurchaseOrders\Show as PurchaseOrdersShow;
use App\Livewire\StockMovements\Index as StockMovementsIndex;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Catalog\ProductCatalogService;
use App\Services\Purchasing\PurchaseOrderService;
use App\Services\Stock\StockMovementService;
use App\Enums\StockMovementType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_guests_cannot_upload_product_images(): void
    {
        $this->get(route('products.index'))->assertRedirect(route('login'));

        Livewire::test(ProductsIndex::class)
            ->call('create')
            ->assertForbidden();
    }

    public function test_products_without_images_show_placeholder(): void
    {
        $user = User::factory()->create();
        Product::factory()->create(['name' => 'No Photo Item', 'sku' => 'SKU-NOPHOTO']);

        Livewire::actingAs($user)
            ->test(ProductsIndex::class)
            ->assertSee('No Photo Item', false)
            ->assertSee('no photo', false);
    }

    public function test_authenticated_users_can_create_products_with_jpeg_png_and_webp_images(): void
    {
        $user = User::factory()->create();

        foreach ([
            ['file' => 'photo.jpg', 'sku' => 'SKU-IMG-JPG'],
            ['file' => 'photo.png', 'sku' => 'SKU-IMG-PNG'],
            ['file' => 'photo.webp', 'sku' => 'SKU-IMG-WEBP'],
        ] as $case) {
            Livewire::actingAs($user)
                ->test(ProductsIndex::class)
                ->call('create')
                ->set('name', 'Imaged '.$case['file'])
                ->set('sku', $case['sku'])
                ->set('unit_price', '10.00')
                ->set('cost_price', '5.00')
                ->set('quantity_on_hand', '0')
                ->set('reorder_level', '0')
                ->set('image', UploadedFile::fake()->image($case['file'], 80, 80))
                ->call('save')
                ->assertHasNoErrors();

            $product = Product::query()->where('sku', $case['sku'])->first();
            $this->assertNotNull($product);
            $this->assertNotNull($product->image_path);
            $this->assertTrue(Storage::disk('public')->exists($product->image_path));
            $this->assertStringStartsWith('products/', $product->image_path);
        }
    }

    public function test_invalid_formats_oversized_and_spoofed_extensions_are_rejected(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ProductsIndex::class)
            ->call('create')
            ->set('name', 'Bad Image Product')
            ->set('sku', 'SKU-BAD-1')
            ->set('unit_price', '1.00')
            ->set('cost_price', '1.00')
            ->set('quantity_on_hand', '0')
            ->set('reorder_level', '0')
            ->set('image', UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'))
            ->call('save')
            ->assertHasErrors(['image']);

        Livewire::actingAs($user)
            ->test(ProductsIndex::class)
            ->call('create')
            ->set('name', 'Huge Image Product')
            ->set('sku', 'SKU-BAD-2')
            ->set('unit_price', '1.00')
            ->set('cost_price', '1.00')
            ->set('quantity_on_hand', '0')
            ->set('reorder_level', '0')
            ->set('image', UploadedFile::fake()->image('huge.jpg')->size(3072))
            ->call('save')
            ->assertHasErrors(['image']);

        $spoofed = UploadedFile::fake()->create('fake.jpg', 20, 'text/plain');

        Livewire::actingAs($user)
            ->test(ProductsIndex::class)
            ->call('create')
            ->set('name', 'Spoofed Image Product')
            ->set('sku', 'SKU-BAD-3')
            ->set('unit_price', '1.00')
            ->set('cost_price', '1.00')
            ->set('quantity_on_hand', '0')
            ->set('reorder_level', '0')
            ->set('image', $spoofed)
            ->call('save')
            ->assertHasErrors(['image']);

        $this->assertDatabaseMissing('products', ['sku' => 'SKU-BAD-1']);
        $this->assertDatabaseMissing('products', ['sku' => 'SKU-BAD-2']);
        $this->assertDatabaseMissing('products', ['sku' => 'SKU-BAD-3']);
        $this->assertSame([], Storage::disk('public')->allFiles('products'));
    }

    public function test_image_can_be_replaced_and_removed_with_audit_logging(): void
    {
        $user = User::factory()->create();
        $service = app(ProductCatalogService::class);

        $product = $service->create(
            [
                'name' => 'Replaceable',
                'sku' => 'SKU-REP-1',
                'unit_price' => '1.00',
                'cost_price' => '1.00',
                'reorder_level' => 0,
                'is_active' => true,
            ],
            [],
            $user,
            0,
            UploadedFile::fake()->image('original.jpg', 40, 40),
        );

        $originalPath = $product->image_path;
        $this->assertTrue(Storage::disk('public')->exists($originalPath));

        Livewire::actingAs($user)
            ->test(ProductsIndex::class)
            ->call('edit', $product->id)
            ->set('image', UploadedFile::fake()->image('replacement.png', 50, 50))
            ->call('save')
            ->assertHasNoErrors();

        $product->refresh();
        $this->assertNotSame($originalPath, $product->image_path);
        $this->assertTrue(Storage::disk('public')->exists($product->image_path));
        $this->assertFalse(Storage::disk('public')->exists($originalPath));

        $this->assertTrue(
            AuditLog::query()
                ->where('entity_type', Product::class)
                ->where('entity_id', $product->id)
                ->where('action', AuditAction::Updated)
                ->exists()
        );

        $replacementPath = $product->image_path;

        Livewire::actingAs($user)
            ->test(ProductsIndex::class)
            ->call('edit', $product->id)
            ->call('removeExistingImage')
            ->call('save')
            ->assertHasNoErrors();

        $product->refresh();
        $this->assertNull($product->image_path);
        $this->assertFalse(Storage::disk('public')->exists($replacementPath));
    }

    public function test_validation_failure_preserves_existing_image_and_cleans_failed_uploads(): void
    {
        $user = User::factory()->create();
        $service = app(ProductCatalogService::class);

        $product = $service->create(
            [
                'name' => 'Keep Image',
                'sku' => 'SKU-KEEP-1',
                'unit_price' => '1.00',
                'cost_price' => '1.00',
                'reorder_level' => 0,
                'is_active' => true,
            ],
            [],
            $user,
            0,
            UploadedFile::fake()->image('keep.jpg', 40, 40),
        );

        $originalPath = $product->image_path;
        $filesBefore = Storage::disk('public')->allFiles('products');

        Livewire::actingAs($user)
            ->test(ProductsIndex::class)
            ->call('edit', $product->id)
            ->set('name', '')
            ->set('image', UploadedFile::fake()->image('should-not-stick.png', 40, 40))
            ->call('save')
            ->assertHasErrors(['name']);

        $product->refresh();
        $this->assertSame($originalPath, $product->image_path);
        $this->assertTrue(Storage::disk('public')->exists($originalPath));
        $this->assertCount(count($filesBefore), Storage::disk('public')->allFiles('products'));
    }

    public function test_deleting_product_deletes_image_file_and_keeps_audit_history(): void
    {
        $user = User::factory()->create();
        $service = app(ProductCatalogService::class);

        $product = $service->create(
            [
                'name' => 'Delete Image',
                'sku' => 'SKU-DEL-1',
                'unit_price' => '1.00',
                'cost_price' => '1.00',
                'reorder_level' => 0,
                'is_active' => true,
            ],
            [],
            $user,
            0,
            UploadedFile::fake()->image('delete-me.jpg', 40, 40),
        );

        $path = $product->image_path;
        $productId = $product->id;

        Livewire::actingAs($user)
            ->test(ProductsIndex::class)
            ->call('confirmDeletion', $productId)
            ->call('delete')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('products', ['id' => $productId]);
        $this->assertFalse(Storage::disk('public')->exists($path));
        $this->assertTrue(
            AuditLog::query()
                ->where('entity_type', Product::class)
                ->where('entity_id', $productId)
                ->where('action', AuditAction::Deleted)
                ->exists()
        );
    }

    public function test_product_images_appear_on_purchase_orders_and_stock_movements(): void
    {
        $user = User::factory()->create();
        $service = app(ProductCatalogService::class);
        $product = $service->create(
            [
                'name' => 'Visible Image Product',
                'sku' => 'SKU-VIS-1',
                'unit_price' => '3.00',
                'cost_price' => '1.00',
                'reorder_level' => 0,
                'is_active' => true,
            ],
            [],
            $user,
            0,
            UploadedFile::fake()->image('visible.jpg', 60, 60),
        );

        app(StockMovementService::class)->apply(
            $product->fresh(),
            StockMovementType::StockIn,
            2,
            $user,
            'Visibility movement',
        );

        $supplier = Supplier::factory()->create();
        $order = app(PurchaseOrderService::class)->createDraft($user, $supplier->id, [
            ['product_id' => $product->id, 'ordered_quantity' => 2, 'unit_cost' => '1.25'],
        ]);

        $imageUrl = $product->fresh()->imageUrl();
        $this->assertNotNull($imageUrl);

        Livewire::actingAs($user)
            ->test(ProductsIndex::class)
            ->assertSee($imageUrl, false);

        Livewire::actingAs($user)
            ->test(StockMovementsIndex::class)
            ->assertSee('Visible Image Product', false)
            ->assertSee($imageUrl, false);

        Livewire::actingAs($user)
            ->test(PurchaseOrdersShow::class, ['purchaseOrder' => $order])
            ->assertSee('Visible Image Product', false)
            ->assertSee($imageUrl, false);
    }

    public function test_svg_uploads_are_rejected(): void
    {
        $user = User::factory()->create();
        $svg = UploadedFile::fake()->create('icon.svg', 10, 'image/svg+xml');

        Livewire::actingAs($user)
            ->test(ProductsIndex::class)
            ->call('create')
            ->set('name', 'SVG Product')
            ->set('sku', 'SKU-SVG-1')
            ->set('unit_price', '1.00')
            ->set('cost_price', '1.00')
            ->set('quantity_on_hand', '0')
            ->set('reorder_level', '0')
            ->set('image', $svg)
            ->call('save')
            ->assertHasErrors(['image']);

        $this->assertDatabaseMissing('products', ['sku' => 'SKU-SVG-1']);
    }
}
