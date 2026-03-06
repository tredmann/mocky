<?php

namespace Database\Factories;

use App\Models\Endpoint;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConditionalResponseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'endpoint_id' => Endpoint::factory(),
            'condition_source' => 'body',
            'condition_field' => 'id',
            'condition_operator' => 'equals',
            'condition_value' => '1',
            'status_code' => 200,
            'content_type' => 'application/json',
            'response_body' => '{"message":"conditional"}',
            'priority' => 0,
        ];
    }
}
