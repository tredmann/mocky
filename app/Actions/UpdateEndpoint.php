<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Endpoint;
use App\Services\ResponseBodyFormatter;

class UpdateEndpoint
{
    public function __construct(private ResponseBodyFormatter $formatter) {}

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
            'response_body' => $this->formatter->format($contentType, $responseBody),
        ]);
    }
}
