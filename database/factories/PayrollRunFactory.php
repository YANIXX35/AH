<?php

namespace Database\Factories;

use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollRun>
 */
class PayrollRunFactory extends Factory
{
    protected $model = PayrollRun::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => 'Paie '.$this->faker->monthName(),
            'period_month' => $this->faker->monthName().' 2026',
            'payment_date' => $this->faker->dateTimeThisYear(),
            'payment_method' => 'bank_transfer',
            'payment_account' => 'Compte Principal',
            'total_gross' => 500000,
            'total_cnps' => 50000,
            'total_its' => 30000,
            'total_net' => 420000,
            'status' => 'draft',
        ];
    }
}
