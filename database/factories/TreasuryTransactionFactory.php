<?php

namespace Database\Factories;

use App\Models\TreasuryTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TreasuryTransaction>
 */
class TreasuryTransactionFactory extends Factory
{
    protected $model = TreasuryTransaction::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => $this->faker->randomElement(['encaissement', 'decaissement']),
            'transaction_type' => 'Paiement client',
            'payment_module' => 'stripe',
            'stripe_payment_channel' => 'card',
            'amount' => $this->faker->randomFloat(2, 1000, 500000),
            'description' => $this->faker->sentence(4),
            'transaction_date' => now()->toDateString(),
            'status' => 'planifie',
        ];
    }
}
