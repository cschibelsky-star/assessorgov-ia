<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerOpportunity;
use App\Models\Opportunity;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CustomerOpportunity> */
class CustomerOpportunityFactory extends Factory
{
    protected $model = CustomerOpportunity::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'opportunity_id' => Opportunity::factory(),
            'stage' => CustomerOpportunity::STAGE_DETECTED,
            'metadata' => [],
        ];
    }
}
