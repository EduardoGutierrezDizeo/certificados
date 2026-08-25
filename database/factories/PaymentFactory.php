<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subscription_plan_id' => SubscriptionPlan::factory(),
            'reference' => 'CERTICHECK-'.$this->faker->unique()->numberBetween(1, 99999).'-'.$this->faker->lexify('??????????'),
            'payment_provider' => 'epayco',
            'amount_in_cents' => 5000000,
            'status' => 'pending',
        ];
    }
}
