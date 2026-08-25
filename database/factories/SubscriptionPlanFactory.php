<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionPlan>
 */
class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'price_in_cents' => fake()->randomElement([3000000, 5000000, 7500000, 10000000]),
            'duration_months' => fake()->randomElement([1, 3, 6, 12]),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
