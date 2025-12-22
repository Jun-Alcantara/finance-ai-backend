<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BankAccountFactory extends Factory
{
    protected $model = BankAccount::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->company . ' Bank',
            'balance' => $this->faker->randomFloat(2, 0, 10000),
            'account_number' => $this->faker->bankAccountNumber,
        ];
    }
}
