<?php

namespace Tests\Feature\Catalog;

use App\Livewire\Categories\Index;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_categories_page(): void
    {
        $this->get(route('categories.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_categories_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('categories.index'))
            ->assertOk()
            ->assertSee('Categories', false);
    }

    public function test_categories_can_be_created_with_generated_slug(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('create')
            ->set('name', 'Office Supplies')
            ->assertSet('slug', 'office-supplies')
            ->set('description', 'Desks and paper')
            ->set('is_active', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('statusMessage', 'Category created successfully.');

        $this->assertDatabaseHas('categories', [
            'name' => 'Office Supplies',
            'slug' => 'office-supplies',
            'description' => 'Desks and paper',
            'is_active' => true,
        ]);
    }

    public function test_category_slug_can_be_edited_manually_on_create(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('create')
            ->set('name', 'Office Supplies')
            ->set('slug', 'office')
            ->set('name', 'Office Supplies Updated')
            ->assertSet('slug', 'office')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('categories', [
            'name' => 'Office Supplies Updated',
            'slug' => 'office',
        ]);
    }

    public function test_categories_can_be_updated_without_changing_slug_unless_edited(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create([
            'name' => 'Electronics',
            'slug' => 'electronics',
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('edit', $category->id)
            ->set('name', 'Consumer Electronics')
            ->assertSet('slug', 'electronics')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Consumer Electronics',
            'slug' => 'electronics',
        ]);
    }

    public function test_category_validation_and_unique_constraints_are_enforced(): void
    {
        $user = User::factory()->create();
        Category::factory()->create([
            'name' => 'Hardware',
            'slug' => 'hardware',
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('create')
            ->set('name', '')
            ->set('slug', '')
            ->call('save')
            ->assertHasErrors(['name', 'slug']);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('create')
            ->set('name', 'Hardware')
            ->set('slug', 'hardware-2')
            ->call('save')
            ->assertHasErrors(['name']);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('create')
            ->set('name', 'Hardware Tools')
            ->set('slug', 'hardware')
            ->call('save')
            ->assertHasErrors(['slug']);
    }

    public function test_category_unique_rules_ignore_the_current_record_when_editing(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create([
            'name' => 'Furniture',
            'slug' => 'furniture',
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('edit', $category->id)
            ->set('description', 'Chairs and tables')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'description' => 'Chairs and tables',
        ]);
    }

    public function test_categories_can_be_deleted_with_confirmation_and_products_are_preserved(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->create();
        $product->categories()->attach($category->id);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('confirmDeletion', $category->id)
            ->assertSet('confirmingDeletionId', $category->id)
            ->call('delete')
            ->assertHasNoErrors()
            ->assertSet('statusMessage', 'Category deleted successfully.');

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
        $this->assertDatabaseMissing('category_product', [
            'category_id' => $category->id,
            'product_id' => $product->id,
        ]);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_categories_can_be_searched_and_paginated(): void
    {
        $user = User::factory()->create();
        Category::factory()->create(['name' => 'Alpha Unique Category']);
        Category::factory()->create(['name' => 'Beta Other Category']);
        Category::factory()->count(10)->create();

        Livewire::actingAs($user)
            ->test(Index::class)
            ->set('search', 'Alpha Unique')
            ->assertSee('Alpha Unique Category', false)
            ->assertDontSee('Beta Other Category', false);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->assertSee('aria-label="Pagination Navigation"', false)
            ->call('nextPage')
            ->assertHasNoErrors();
    }

    public function test_guests_cannot_mutate_categories_through_livewire(): void
    {
        Livewire::test(Index::class)
            ->call('create')
            ->assertForbidden();

        Livewire::test(Index::class)
            ->set('name', 'Blocked')
            ->set('slug', 'blocked')
            ->call('save')
            ->assertForbidden();
    }
}
