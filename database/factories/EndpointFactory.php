<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EndpointFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->optional()->sentence(),
            'slug' => Str::uuid(),
            'method' => 'GET',
            'status_code' => 200,
            'content_type' => 'application/json',
            'response_body' => '{"message":"ok"}',
            'is_active' => true,
        ];
    }
}
