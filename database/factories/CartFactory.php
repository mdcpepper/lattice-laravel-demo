<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cart>
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
