<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    /** @use HasFactory<\Database\Factories\PurchaseOrderFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'number',
        'supplier_id',
        'status',
        'order_date',
        'expected_delivery_date',
        'notes',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'order_date' => 'date',
            'expected_delivery_date' => 'date',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(PurchaseOrderReceipt::class);
    }

    public function totalReceivedQuantity(): int
    {
        return (int) $this->items->sum('received_quantity');
    }

    public function isFullyReceived(): bool
    {
        if ($this->items->isEmpty()) {
            return false;
        }

        return $this->items->every(
            fn (PurchaseOrderItem $item) => $item->received_quantity >= $item->ordered_quantity
        );
    }

    public function hasAnyReceivedQuantity(): bool
    {
        return $this->items->contains(
            fn (PurchaseOrderItem $item) => $item->received_quantity > 0
        );
    }

    public function totalAmount(): string
    {
        $totals = $this->items
            ->map(fn (PurchaseOrderItem $item) => $item->lineTotal())
            ->all();

        return $totals === [] ? Money::of('0') : Money::add(...$totals);
    }
}
