<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Endpoint;

class UpdateEndpoint
{
    public function handle(
        Endpoint $endpoint,
        string $name,
        string $slug,
        string $method,
        int $statusCode,
        string $contentType,
        ?string $description = null,
        ?string $responseBody = null,
    ): void {
        $endpoint->update([
            'name' => $name,
            'description' => $description,
            'slug' => $slug,
            'method' => $method,
            'status_code' => $statusCode,
            'content_type' => $contentType,
            'response_body' => $responseBody,
        ]);
    }
}
