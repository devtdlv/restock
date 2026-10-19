<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_category_can_be_created(): void
    {
        $category = Category::factory()->create([
            'name' => 'Office Supplies',
            'slug' => 'office-supplies',
            'description' => 'General office materials',
        ]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Office Supplies',
            'slug' => 'office-supplies',
            'description' => 'General office materials',
            'is_active' => true,
        ]);
    }

    public function test_a_valid_product_can_be_created(): void
    {
        $product = Product::factory()->create([
            'name' => 'Wireless Mouse',
            'sku' => 'SKU-MOUSE-0001',
            'unit_price' => '29.99',
            'cost_price' => '12.50',
            'quantity_on_hand' => 40,
            'reorder_level' => 5,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Wireless Mouse',
            'sku' => 'SKU-MOUSE-0001',
            'unit_price' => '29.99',
            'cost_price' => '12.50',
            'quantity_on_hand' => 40,
            'reorder_level' => 5,
            'is_active' => true,
        ]);

        $this->assertSame('29.99', $product->fresh()->unit_price);
        $this->assertSame('12.50', $product->fresh()->cost_price);
    }

    public function test_a_product_can_belong_to_multiple_categories(): void
    {
        $product = Product::factory()->create();
        $categories = Category::factory()->count(3)->create();

        $product->categories()->attach($categories->pluck('id'));

        $this->assertCount(3, $product->fresh()->categories);
        $this->assertEqualsCanonicalizing(
            $categories->pluck('id')->all(),
            $product->categories->pluck('id')->all()
        );
    }

    public function test_a_category_can_contain_multiple_products(): void
    {
        $category = Category::factory()->create();
        $products = Product::factory()->count(3)->create();

        $category->products()->attach($products->pluck('id'));

        $this->assertCount(3, $category->fresh()->products);
        $this->assertEqualsCanonicalizing(
            $products->pluck('id')->all(),
            $category->products->pluck('id')->all()
        );
    }

    public function test_duplicate_product_category_pairs_are_rejected(): void
    {
        $product = Product::factory()->create();
        $category = Category::factory()->create();

        $product->categories()->attach($category->id);

        $this->expectException(QueryException::class);

        $product->categories()->attach($category->id);
    }

    public function test_deleting_a_product_removes_its_pivot_records(): void
    {
        $product = Product::factory()->withCategories(2)->create();

        $this->assertDatabaseCount('category_product', 2);

        $product->delete();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseCount('category_product', 0);
    }

    public function test_deleting_a_category_removes_its_pivot_records(): void
    {
        $category = Category::factory()->create();
        $products = Product::factory()->count(2)->create();
        $category->products()->attach($products->pluck('id'));

        $this->assertDatabaseCount('category_product', 2);

        $category->delete();

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
        $this->assertDatabaseCount('category_product', 0);
        $this->assertDatabaseCount('products', 2);
    }

    public function test_product_skus_must_be_unique(): void
    {
        Product::factory()->create(['sku' => 'SKU-UNIQUE-0001']);

        $this->expectException(QueryException::class);

        Product::factory()->create(['sku' => 'SKU-UNIQUE-0001']);
    }

    public function test_category_names_and_slugs_must_be_unique(): void
    {
        Category::factory()->create([
            'name' => 'Electronics',
            'slug' => 'electronics',
        ]);

        try {
            Category::factory()->create([
                'name' => 'Electronics',
                'slug' => 'electronics-2',
            ]);
            $this->fail('Duplicate category names should be rejected.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $this->expectException(QueryException::class);

        Category::factory()->create([
            'name' => 'Consumer Electronics',
            'slug' => 'electronics',
        ]);
    }

    public function test_products_and_categories_receive_expected_default_values(): void
    {
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Default Category',
            'slug' => 'default-category',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = DB::table('products')->insertGetId([
            'name' => 'Default Product',
            'sku' => 'SKU-DEFAULT-0001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $category = Category::query()->findOrFail($categoryId);
        $product = Product::query()->findOrFail($productId);

        $this->assertTrue($category->is_active);
        $this->assertNull($category->description);

        $this->assertSame('0.00', $product->unit_price);
        $this->assertSame('0.00', $product->cost_price);
        $this->assertSame(0, $product->quantity_on_hand);
        $this->assertSame(0, $product->reorder_level);
        $this->assertTrue($product->is_active);
        $this->assertNull($product->description);
    }

    public function test_negative_prices_and_quantities_are_rejected_at_the_database_level(): void
    {
        $base = [
            'name' => 'Invalid Product',
            'sku' => 'SKU-INVALID-0001',
            'description' => null,
            'unit_price' => 10,
            'cost_price' => 5,
            'quantity_on_hand' => 1,
            'reorder_level' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        foreach ([
            ['unit_price' => -1.00],
            ['cost_price' => -0.01],
            ['quantity_on_hand' => -1],
            ['reorder_level' => -5],
        ] as $index => $invalid) {
            try {
                DB::table('products')->insert(array_merge($base, $invalid, [
                    'sku' => 'SKU-INVALID-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                ]));
                $this->fail('Expected a database constraint violation for '.array_key_first($invalid));
            } catch (QueryException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_product_factory_can_create_products_with_multiple_categories(): void
    {
        $product = Product::factory()->withCategories(3)->create();

        $this->assertCount(3, $product->fresh()->categories);
        $this->assertDatabaseCount('category_product', 3);
    }
}
