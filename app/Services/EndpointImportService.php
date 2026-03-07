<?php

namespace App\Services;

use App\Models\Endpoint;
use App\Models\EndpointCollection;
use App\Models\User;
use Illuminate\Support\Str;

class EndpointImportService
{
    public function import(User $user, array $data, EndpointCollection $collection): Endpoint
    {
        $slug = $data['slug'] ?? null;

        if (! $slug || $collection->endpoints()->where('slug', $slug)->exists()) {
            $slug = Str::uuid();
        }

        /** @var Endpoint $endpoint */
        $endpoint = $collection->endpoints()->create([
            'user_id' => $user->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'slug' => $slug,
            'method' => $data['method'],
            'status_code' => $data['status_code'],
            'content_type' => $data['content_type'],
            'response_body' => $data['response_body'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

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
