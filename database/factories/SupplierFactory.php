<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $company = fake()->unique()->company();

        return [
            'name' => $company,
            'code' => 'SUP-'.Str::upper(fake()->unique()->bothify('??##')),
            'contact_person' => fake()->optional()->name(),
            'email' => fake()->optional()->safeEmail(),
            'phone' => fake()->optional()->numerify('###-###-####'),
            'address' => fake()->optional()->streetAddress(),
            'notes' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
