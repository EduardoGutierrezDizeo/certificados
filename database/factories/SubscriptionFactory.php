<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'plan' => 'standard',
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => 'cancelled',
            'ends_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => 'expired',
            'ends_at' => now()->subDay(),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => [
            'status' => 'suspended',
        ]);
    }
}
