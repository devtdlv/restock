<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * quantity_on_hand defaults to 0 at the database level and is not mass-assigned.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-????-####')),
            'description' => fake()->optional()->sentence(),
            'unit_price' => number_format(fake()->randomFloat(2, 0, 9999.99), 2, '.', ''),
            'cost_price' => number_format(fake()->randomFloat(2, 0, 5000.00), 2, '.', ''),
            'reorder_level' => fake()->numberBetween(0, 50),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the product is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Attach one or more categories to the product after creation.
     */
    public function withCategories(int $count = 2): static
    {
        return $this->afterCreating(function (Product $product) use ($count): void {
            $categories = Category::factory()->count($count)->create();

            $product->categories()->attach($categories->pluck('id'));
        });
    }
}
