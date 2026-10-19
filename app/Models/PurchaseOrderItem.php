<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'ordered_quantity',
        'received_quantity',
        'unit_cost',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ordered_quantity' => 'integer',
            'received_quantity' => 'integer',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function receiptItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderReceiptItem::class);
    }

    public function outstandingQuantity(): int
    {
        return max(0, (int) $this->ordered_quantity - (int) $this->received_quantity);
    }

    public function lineTotal(): string
    {
        return Money::multiply(Money::of($this->unit_cost), (int) $this->ordered_quantity);
    }
}
