<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'legal_name' => fake()->company(),
            'trade_name' => fake()->company(),
            'document' => fake()->unique()->numerify('##############'),
            'email' => fake()->unique()->safeEmail(),
            'status' => 'active',
        ];
    }
}
