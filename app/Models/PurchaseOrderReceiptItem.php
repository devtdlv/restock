<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderReceiptItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'purchase_order_receipt_id',
        'purchase_order_item_id',
        'quantity',
        'stock_movement_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderReceipt::class, 'purchase_order_receipt_id');
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }
}
