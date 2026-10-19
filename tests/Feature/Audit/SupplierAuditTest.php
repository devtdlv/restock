<?php

namespace Tests\Feature\Audit;

use App\Enums\AuditAction;
use App\Livewire\AuditLogs\Index as AuditLogsIndex;
use App\Livewire\Suppliers\Index as SuppliersIndex;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Catalog\CategoryCatalogService;
use App\Services\Catalog\SupplierCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SupplierAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_create_update_and_delete_are_audited_and_history_survives_deletion(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(SuppliersIndex::class)
            ->call('create')
            ->set('name', 'Audit Supplier')
            ->set('code', 'AUD-SUP-1')
            ->set('contact_person', 'Casey')
            ->set('email', 'casey@audit.test')
            ->call('save')
            ->assertHasNoErrors();

        $supplier = Supplier::query()->where('code', 'AUD-SUP-1')->firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => Supplier::class,
            'entity_id' => $supplier->id,
            'action' => AuditAction::Created->value,
            'user_id' => $user->id,
        ]);

        $created = AuditLog::query()
            ->where('entity_type', Supplier::class)
            ->where('entity_id', $supplier->id)
            ->where('action', AuditAction::Created)
            ->sole();

        $this->assertSame('Audit Supplier', $created->after_values['name']);
        $this->assertSame('AUD-SUP-1', $created->after_values['code']);
        $this->assertArrayNotHasKey('password', $created->after_values ?? []);

        Livewire::actingAs($user)
            ->test(SuppliersIndex::class)
            ->call('edit', $supplier->id)
            ->set('contact_person', 'Casey Updated')
            ->set('phone', '555-9999')
            ->call('save')
            ->assertHasNoErrors();

        $updated = AuditLog::query()
            ->where('entity_type', Supplier::class)
            ->where('entity_id', $supplier->id)
            ->where('action', AuditAction::Updated)
            ->sole();

        $this->assertSame('Casey', $updated->before_values['contact_person']);
        $this->assertSame('Casey Updated', $updated->after_values['contact_person']);
        $this->assertNull($updated->before_values['phone']);
        $this->assertSame('555-9999', $updated->after_values['phone']);

        $supplierId = $supplier->id;

        Livewire::actingAs($user)
            ->test(SuppliersIndex::class)
            ->call('confirmDeletion', $supplierId)
            ->call('delete')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('suppliers', ['id' => $supplierId]);
        $this->assertSame(
            3,
            AuditLog::query()
                ->where('entity_type', Supplier::class)
                ->where('entity_id', $supplierId)
                ->count()
        );
        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => Supplier::class,
            'entity_id' => $supplierId,
            'action' => AuditAction::Deleted->value,
        ]);
    }

    public function test_audit_logs_page_supports_supplier_entity_filter_and_details(): void
    {
        $user = User::factory()->create(['name' => 'Supplier Auditor']);

        $supplier = app(SupplierCatalogService::class)->create([
            'name' => 'Filterable Supplier',
            'code' => 'FILT-SUP',
            'contact_person' => 'Pat',
            'email' => 'pat@filter.test',
            'phone' => null,
            'address' => null,
            'notes' => 'Before notes',
            'is_active' => true,
        ], $user);

        app(SupplierCatalogService::class)->update($supplier, [
            'name' => 'Filterable Supplier',
            'code' => 'FILT-SUP',
            'contact_person' => 'Pat',
            'email' => 'pat@filter.test',
            'phone' => null,
            'address' => null,
            'notes' => 'After notes',
            'is_active' => true,
        ], $user);

        app(CategoryCatalogService::class)->create([
            'name' => 'Unrelated Category',
            'slug' => 'unrelated-category',
            'description' => null,
            'is_active' => true,
        ], $user);

        Livewire::actingAs($user)
            ->test(AuditLogsIndex::class)
            ->set('entityType', Supplier::class)
            ->assertSee('Filterable Supplier', false)
            ->assertDontSee('Unrelated Category', false);

        $audit = AuditLog::query()
            ->where('entity_type', Supplier::class)
            ->where('entity_id', $supplier->id)
            ->where('action', AuditAction::Updated)
            ->sole();

        Livewire::actingAs($user)
            ->test(AuditLogsIndex::class)
            ->set('entityType', Supplier::class)
            ->call('viewDetails', $audit->id)
            ->assertSee('Audit details', false)
            ->assertSee('Before notes', false)
            ->assertSee('After notes', false)
            ->assertSee('Notes', false)
            ->assertSee('Supplier', false);
    }

    public function test_supplier_entity_type_is_available_in_audit_filters(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(AuditLogsIndex::class)
            ->assertSee(Supplier::class, false)
            ->assertSee(Category::class, false);
    }
}
