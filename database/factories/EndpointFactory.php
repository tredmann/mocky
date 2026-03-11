<?php

namespace Database\Factories;

use App\Models\EndpointCollection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EndpointFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'collection_id' => EndpointCollection::factory(),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->optional()->sentence(),
            'slug' => $this->faker->unique()->slug(2),
            'method' => 'GET',
            'status_code' => 200,
            'content_type' => 'application/json',
            'response_body' => '{"message":"ok"}',
            'is_active' => true,
            'type' => 'rest',
        ];
    }
}
