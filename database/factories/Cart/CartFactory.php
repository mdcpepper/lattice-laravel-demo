<?php

namespace Database\Factories\Cart;

use App\Models\Cart\Cart;
use App\Models\Customer;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Cart>
 */
class CartFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'email' => null,
            'customer_id' => null,
            'subtotal' => 0,
            'subtotal_currency' => 'GBP',
            'total' => 0,
            'total_currency' => 'GBP',
        ];
    }

    public function anonymous(): static
    {
        return $this->state(
            fn (array $attributes) => [
                'email' => fake()->safeEmail(),
                'customer_id' => null,
            ],
        );
    }

    public function forCustomer(Customer $customer): static
    {
        return $this->state(
            fn (array $attributes) => [
                'team_id' => $customer->team_id,
                'customer_id' => $customer->id,
                'email' => null,
            ],
        );
    }
}
