<?php

namespace Tests\Feature\Catalog;

use App\Livewire\Suppliers\Index;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Catalog\SupplierCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SupplierManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_suppliers_page(): void
    {
        $this->get(route('suppliers.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_suppliers_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('suppliers.index'))
            ->assertOk()
            ->assertSee('Suppliers', false);
    }

    public function test_suppliers_can_be_created(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('create')
            ->set('name', 'Acme Supplies')
            ->set('code', 'ACME-001')
            ->set('contact_person', 'Jane Doe')
            ->set('email', 'jane@acme.test')
            ->set('phone', '555-0100')
            ->set('address', '100 Industrial Way')
            ->set('notes', 'Preferred vendor')
            ->set('is_active', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('statusMessage', 'Supplier created successfully.');

        $this->assertDatabaseHas('suppliers', [
            'name' => 'Acme Supplies',
            'code' => 'ACME-001',
            'contact_person' => 'Jane Doe',
            'email' => 'jane@acme.test',
            'phone' => '555-0100',
            'address' => '100 Industrial Way',
            'notes' => 'Preferred vendor',
            'is_active' => true,
        ]);
    }

    public function test_supplier_validation_and_duplicate_codes_are_enforced(): void
    {
        $user = User::factory()->create();
        Supplier::factory()->create([
            'name' => 'Existing Vendor',
            'code' => 'EXIST-1',
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('create')
            ->set('name', '')
            ->set('code', '')
            ->set('email', 'not-an-email')
            ->call('save')
            ->assertHasErrors(['name', 'code', 'email']);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('create')
            ->set('name', 'Another Vendor')
            ->set('code', 'EXIST-1')
            ->call('save')
            ->assertHasErrors(['code']);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('create')
            ->set('name', 'Bad Code Vendor')
            ->set('code', 'BAD CODE!')
            ->call('save')
            ->assertHasErrors(['code']);
    }

    public function test_supplier_unique_code_rule_ignores_the_current_record_when_editing(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create([
            'name' => 'Editable Vendor',
            'code' => 'EDIT-1',
            'contact_person' => 'Sam',
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('edit', $supplier->id)
            ->set('contact_person', 'Sam Updated')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('statusMessage', 'Supplier updated successfully.');

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'code' => 'EDIT-1',
            'contact_person' => 'Sam Updated',
        ]);
    }

    public function test_suppliers_can_be_updated(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create([
            'name' => 'Old Name',
            'code' => 'OLD-1',
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('edit', $supplier->id)
            ->set('name', 'New Name')
            ->set('code', 'NEW-1')
            ->set('email', 'new@vendor.test')
            ->set('is_active', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'name' => 'New Name',
            'code' => 'NEW-1',
            'email' => 'new@vendor.test',
            'is_active' => false,
        ]);
    }

    public function test_noop_supplier_updates_do_not_write_duplicate_audit_logs(): void
    {
        $user = User::factory()->create();
        $service = app(SupplierCatalogService::class);

        $supplier = $service->create([
            'name' => 'Noop Supplier',
            'code' => 'NOOP-1',
            'contact_person' => null,
            'email' => null,
            'phone' => null,
            'address' => null,
            'notes' => null,
            'is_active' => true,
        ], $user);

        $service->update($supplier, [
            'name' => 'Noop Supplier',
            'code' => 'NOOP-1',
            'contact_person' => null,
            'email' => null,
            'phone' => null,
            'address' => null,
            'notes' => null,
            'is_active' => true,
        ], $user);

        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => Supplier::class,
            'entity_id' => $supplier->id,
            'action' => 'created',
        ]);
    }

    public function test_suppliers_can_be_deleted_with_confirmation(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create(['name' => 'Delete Me', 'code' => 'DEL-1']);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('confirmDeletion', $supplier->id)
            ->assertSet('confirmingDeletionId', $supplier->id)
            ->call('delete')
            ->assertHasNoErrors()
            ->assertSet('statusMessage', 'Supplier deleted successfully.');

        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    }

    public function test_suppliers_can_be_searched_and_paginated(): void
    {
        $user = User::factory()->create();
        Supplier::factory()->create([
            'name' => 'Alpha Unique Supplier',
            'code' => 'ALPHA-9',
            'contact_person' => 'Alpha Contact',
            'email' => 'alpha@supplier.test',
        ]);
        Supplier::factory()->create([
            'name' => 'Beta Other Supplier',
            'code' => 'BETA-9',
            'contact_person' => 'Beta Contact',
            'email' => 'beta@supplier.test',
        ]);
        Supplier::factory()->count(10)->create();

        Livewire::actingAs($user)
            ->test(Index::class)
            ->set('search', 'Alpha Unique')
            ->assertSee('Alpha Unique Supplier', false)
            ->assertDontSee('Beta Other Supplier', false);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->set('search', 'ALPHA-9')
            ->assertSee('Alpha Unique Supplier', false)
            ->assertDontSee('Beta Other Supplier', false);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->set('search', 'Alpha Contact')
            ->assertSee('Alpha Unique Supplier', false)
            ->assertDontSee('Beta Other Supplier', false);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->set('search', 'alpha@supplier.test')
            ->assertSee('Alpha Unique Supplier', false)
            ->assertDontSee('Beta Other Supplier', false);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->assertSee('aria-label="Pagination Navigation"', false)
            ->call('nextPage')
            ->assertHasNoErrors();
    }

    public function test_supplier_active_status_can_be_toggled(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create([
            'name' => 'Status Vendor',
            'code' => 'STATUS-1',
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('edit', $supplier->id)
            ->set('is_active', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse($supplier->fresh()->is_active);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->assertSee('Inactive', false);
    }

    public function test_guests_cannot_mutate_suppliers_through_livewire(): void
    {
        Livewire::test(Index::class)
            ->call('create')
            ->assertForbidden();

        Livewire::test(Index::class)
            ->set('name', 'Blocked')
            ->set('code', 'BLOCKED-1')
            ->call('save')
            ->assertForbidden();
    }

    public function test_empty_optional_fields_are_stored_as_null(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('create')
            ->set('name', 'Minimal Supplier')
            ->set('code', 'MIN-1')
            ->set('contact_person', '')
            ->set('email', '')
            ->set('phone', '')
            ->set('address', '')
            ->set('notes', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('suppliers', [
            'code' => 'MIN-1',
            'contact_person' => null,
            'email' => null,
            'phone' => null,
            'address' => null,
            'notes' => null,
        ]);
    }
}
