<?php

namespace Database\Factories;

use App\Models\ErrorReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ErrorReport>
 */
class ErrorReportFactory extends Factory
{
    protected $model = ErrorReport::class;

    public function definition(): array
    {
        return [
            'lawyer_id' => User::factory(),
            'subject' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'category' => fake()->randomElement(['pago', 'certificado', 'otro']),
            'status' => 'pending',
        ];
    }
}
