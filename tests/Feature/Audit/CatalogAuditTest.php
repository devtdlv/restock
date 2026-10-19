<?php

namespace Tests\Feature\Audit;

use App\Enums\AuditAction;
use App\Enums\StockMovementType;
use App\Livewire\Categories\Index as CategoriesIndex;
use App\Livewire\Products\Index as ProductsIndex;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\Catalog\CategoryCatalogService;
use App\Services\Catalog\ProductCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_create_update_and_delete_produce_audit_records(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        Livewire::actingAs($user)
            ->test(ProductsIndex::class)
            ->call('create')
            ->set('name', 'Audit Product')
            ->set('sku', 'SKU-AUDIT-1')
            ->set('unit_price', '9.99')
            ->set('cost_price', '4.00')
            ->set('quantity_on_hand', '5')
            ->set('reorder_level', '1')
            ->set('categoryIds', [$category->id])
            ->call('save')
            ->assertHasNoErrors();

        $product = Product::query()->where('sku', 'SKU-AUDIT-1')->firstOrFail();

        $created = AuditLog::query()
            ->where('entity_type', Product::class)
            ->where('entity_id', $product->id)
            ->where('action', AuditAction::Created)
            ->sole();

        $this->assertSame($user->id, $created->user_id);
        $this->assertSame(5, $created->after_values['quantity_on_hand']);
        $this->assertSame([$category->id], $created->after_values['category_ids']);
        $this->assertArrayNotHasKey('password', $created->after_values ?? []);

        Livewire::actingAs($user)
            ->test(ProductsIndex::class)
            ->call('edit', $product->id)
            ->set('name', 'Audit Product Updated')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(
            1,
            AuditLog::query()
                ->where('entity_type', Product::class)
                ->where('entity_id', $product->id)
                ->where('action', AuditAction::Updated)
                ->count()
        );

        Livewire::actingAs($user)
            ->test(ProductsIndex::class)
            ->call('confirmDeletion', $product->id)
            ->call('delete')
            ->assertHasNoErrors();

        $deleted = AuditLog::query()
            ->where('entity_type', Product::class)
            ->where('entity_id', $product->id)
            ->where('action', AuditAction::Deleted)
            ->sole();

        $this->assertSame('Audit Product Updated', $deleted->before_values['name']);
        $this->assertNull($deleted->after_values);
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => Product::class,
            'entity_id' => $product->id,
            'action' => AuditAction::Deleted->value,
        ]);
    }

    public function test_category_assignment_changes_are_audited_without_duplicates_on_noop(): void
    {
        $user = User::factory()->create();
        $first = Category::factory()->create(['name' => 'First']);
        $second = Category::factory()->create(['name' => 'Second']);
        $product = app(ProductCatalogService::class)->create(
            [
                'name' => 'Sync Product',
                'sku' => 'SKU-AUDIT-2',
                'unit_price' => '1.00',
                'cost_price' => '0.50',
                'reorder_level' => 0,
                'is_active' => true,
            ],
            [$first->id],
            $user,
            0,
        );

        $beforeUpdates = AuditLog::query()
            ->where('entity_type', Product::class)
            ->where('entity_id', $product->id)
            ->where('action', AuditAction::Updated)
            ->count();

        app(ProductCatalogService::class)->update(
            $product,
            [
                'name' => $product->name,
                'sku' => $product->sku,
                'description' => $product->description,
                'unit_price' => $product->unit_price,
                'cost_price' => $product->cost_price,
                'reorder_level' => $product->reorder_level,
                'is_active' => $product->is_active,
            ],
            [$first->id, $second->id],
            $user,
        );

        $updated = AuditLog::query()
            ->where('entity_type', Product::class)
            ->where('entity_id', $product->id)
            ->where('action', AuditAction::Updated)
            ->latest('id')
            ->first();

        $this->assertSame($beforeUpdates + 1, AuditLog::query()
            ->where('entity_type', Product::class)
            ->where('entity_id', $product->id)
            ->where('action', AuditAction::Updated)
            ->count());
        $this->assertEqualsCanonicalizing([$first->id], $updated->before_values['category_ids']);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $updated->after_values['category_ids']);

        app(ProductCatalogService::class)->update(
            $product->fresh(),
            [
                'name' => $product->name,
                'sku' => $product->sku,
                'description' => $product->description,
                'unit_price' => $product->unit_price,
                'cost_price' => $product->cost_price,
                'reorder_level' => $product->reorder_level,
                'is_active' => $product->is_active,
            ],
            [$first->id, $second->id],
            $user,
        );

        $this->assertSame($beforeUpdates + 1, AuditLog::query()
            ->where('entity_type', Product::class)
            ->where('entity_id', $product->id)
            ->where('action', AuditAction::Updated)
            ->count());
    }

    public function test_category_create_update_and_delete_are_audited_and_history_survives_deletion(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(CategoriesIndex::class)
            ->call('create')
            ->set('name', 'Audit Category')
            ->set('slug', 'audit-category')
            ->set('description', 'Tracked')
            ->call('save')
            ->assertHasNoErrors();

        $category = Category::query()->where('slug', 'audit-category')->firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => Category::class,
            'entity_id' => $category->id,
            'action' => AuditAction::Created->value,
        ]);

        Livewire::actingAs($user)
            ->test(CategoriesIndex::class)
            ->call('edit', $category->id)
            ->set('description', 'Updated tracked')
            ->call('save')
            ->assertHasNoErrors();

        Livewire::actingAs($user)
            ->test(CategoriesIndex::class)
            ->call('confirmDeletion', $category->id)
            ->call('delete')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
        $this->assertSame(
            3,
            AuditLog::query()
                ->where('entity_type', Category::class)
                ->where('entity_id', $category->id)
                ->count()
        );
        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => Category::class,
            'entity_id' => $category->id,
            'action' => AuditAction::Deleted->value,
        ]);
    }

    public function test_product_create_with_opening_stock_does_not_duplicate_created_audits(): void
    {
        $user = User::factory()->create();

        $product = app(ProductCatalogService::class)->create(
            [
                'name' => 'Opening Audit',
                'sku' => 'SKU-AUDIT-3',
                'unit_price' => '2.00',
                'cost_price' => '1.00',
                'reorder_level' => 0,
                'is_active' => true,
            ],
            [],
            $user,
            9,
        );

        $this->assertSame(
            1,
            AuditLog::query()
                ->where('entity_type', Product::class)
                ->where('entity_id', $product->id)
                ->count()
        );
        $this->assertSame(
            1,
            AuditLog::query()
                ->where('entity_type', Product::class)
                ->where('entity_id', $product->id)
                ->where('action', AuditAction::Created)
                ->count()
        );
        $this->assertSame(9, $product->quantity_on_hand);
        $this->assertSame(StockMovementType::StockIn, $product->stockMovements()->first()->type);
    }

    public function test_category_service_noop_update_does_not_write_duplicate_audit(): void
    {
        $user = User::factory()->create();
        $category = app(CategoryCatalogService::class)->create([
            'name' => 'Noop Category',
            'slug' => 'noop-category',
            'description' => null,
            'is_active' => true,
        ], $user);

        app(CategoryCatalogService::class)->update($category, [
            'name' => 'Noop Category',
            'slug' => 'noop-category',
            'description' => null,
            'is_active' => true,
        ], $user);

        $this->assertSame(
            1,
            AuditLog::query()
                ->where('entity_type', Category::class)
                ->where('entity_id', $category->id)
                ->count()
        );
    }
}
