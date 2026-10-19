<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Vendor contact record for inventory purchasing.
 *
 * Future purchase orders should reference this model via supplier_id.
 * When that relationship exists, hard deletes should be blocked while
 * related purchase orders remain (restrictOnDelete or an explicit service guard).
 */
class Supplier extends Model
{
    /** @use HasFactory<\Database\Factories\SupplierFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'contact_person',
        'email',
        'phone',
        'address',
        'notes',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
