<?php

namespace App\Services;

use App\Actions\CreateEndpoint;
use App\Models\Endpoint;
use App\Models\EndpointCollection;
use App\Models\User;
use Illuminate\Support\Str;

class EndpointImportService
{
    public function __construct(private CreateEndpoint $createEndpoint) {}

    public function import(User $user, array $data, EndpointCollection $collection): Endpoint
    {
        $slug = $data['slug'] ?? Str::slug($data['name']);

        $endpoint = $this->createEndpoint->handle(
            $user,
            $collection,
            $data['name'],
            $slug,
            $data['method'],
            $data['status_code'],
            $data['content_type'],
            $data['description'] ?? null,
            $data['response_body'] ?? null,
            $data['is_active'] ?? true,
        );

        foreach ($data['conditional_responses'] ?? [] as $cr) {
            $endpoint->conditionalResponses()->create([
                'condition_source' => $cr['condition_source'],
                'condition_field' => $cr['condition_field'],
                'condition_operator' => $cr['condition_operator'],
                'condition_value' => $cr['condition_value'],
                'status_code' => $cr['status_code'],
                'content_type' => $cr['content_type'],
                'response_body' => $cr['response_body'] ?? null,
                'priority' => $cr['priority'] ?? 0,
            ]);
        }

        return $endpoint;
    }
}
