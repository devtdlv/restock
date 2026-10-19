<?php

namespace Database\Factories;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'PO-'.fake()->unique()->numerify('######'),
            'supplier_id' => Supplier::factory(),
            'status' => PurchaseOrderStatus::Draft,
            'order_date' => now()->toDateString(),
            'expected_delivery_date' => null,
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }
}
