<?php

namespace App\Services\Catalog;

use App\Enums\AuditAction;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Creates, updates, and deletes suppliers with audit logging.
 */
class SupplierCatalogService
{
    public function __construct(
        protected AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, User $user): Supplier
    {
        return DB::transaction(function () use ($attributes, $user) {
            $supplier = Supplier::query()->create($this->attributesForPersistence($attributes));

            $this->auditLogger->record(
                AuditAction::Created,
                $supplier,
                null,
                $this->snapshot($supplier),
                $user,
            );

            return $supplier;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Supplier $supplier, array $attributes, User $user): Supplier
    {
        return DB::transaction(function () use ($supplier, $attributes, $user) {
            /** @var Supplier $locked */
            $locked = Supplier::query()
                ->whereKey($supplier->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $before = $this->snapshot($locked);

            $locked->update($this->attributesForPersistence($attributes));
            $locked->refresh();

            $this->auditLogger->record(
                AuditAction::Updated,
                $locked,
                $before,
                $this->snapshot($locked),
                $user,
            );

            return $locked;
        });
    }

    public function delete(Supplier $supplier, User $user): void
    {
        DB::transaction(function () use ($supplier, $user) {
            /** @var Supplier $locked */
            $locked = Supplier::query()
                ->whereKey($supplier->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $before = $this->snapshot($locked);

            // When purchase orders exist, prefer DB restrictOnDelete or a pre-check here.
            $locked->delete();

            $this->auditLogger->record(
                AuditAction::Deleted,
                $locked,
                $before,
                null,
                $user,
            );
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(Supplier $supplier): array
    {
        return [
            'name' => $supplier->name,
            'code' => $supplier->code,
            'contact_person' => $supplier->contact_person,
            'email' => $supplier->email,
            'phone' => $supplier->phone,
            'address' => $supplier->address,
            'notes' => $supplier->notes,
            'is_active' => (bool) $supplier->is_active,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function attributesForPersistence(array $attributes): array
    {
        return collect($attributes)
            ->only([
                'name',
                'code',
                'contact_person',
                'email',
                'phone',
                'address',
                'notes',
                'is_active',
            ])
            ->all();
    }
}
