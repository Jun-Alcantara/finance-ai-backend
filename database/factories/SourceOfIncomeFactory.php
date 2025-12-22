<?php

namespace Database\Factories;

use App\Models\SourceOfIncome;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SourceOfIncomeFactory extends Factory
{
    protected $model = SourceOfIncome::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->jobTitle, // e.g. "Software Engineer"
        ];
    }
}
