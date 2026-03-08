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
            'response_body' => $this->formatBody($contentType, $responseBody),
        ]);
    }

    private function formatBody(string $contentType, ?string $body): ?string
    {
        if ($body === null || $body === '') {
            return $body;
        }

        if (str_contains($contentType, 'json')) {
            $decoded = json_decode($body);
            if (json_last_error() === JSON_ERROR_NONE) {
                return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        }

        return $body;
    }
}
