<?php

namespace Database\Factories;

use App\Models\Opportunity;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Opportunity> */
class OpportunityFactory extends Factory
{
    protected $model = Opportunity::class;

    public function definition(): array
    {
        return [
            'channel' => Opportunity::CHANNEL_LICITACAO,
            'source_name' => 'test:'.fake()->unique()->slug(2),
            'source_type' => 'official',
            'source_url' => fake()->url(),
            'external_id' => fake()->unique()->uuid(),
            'title' => fake()->sentence(5),
            'summary' => fake()->sentence(),
            'status' => 'active',
            'source_checked_at' => now(),
        ];
    }
}
