<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory;

    /**
     * Mass-assignable attributes.
     *
     * quantity_on_hand is intentionally omitted. Inventory changes must go through
     * App\Services\Stock\StockMovementService (forceFill is reserved for that path).
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'sku',
        'description',
        'unit_price',
        'cost_price',
        'reorder_level',
        'is_active',
        'image_path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'quantity_on_hand' => 'integer',
            'reorder_level' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function hasImage(): bool
    {
        return filled($this->image_path)
            && app(\App\Services\Catalog\ProductImageService::class)->exists($this->image_path);
    }

    public function imageUrl(): ?string
    {
        return app(\App\Services\Catalog\ProductImageService::class)->url($this->image_path);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
}
