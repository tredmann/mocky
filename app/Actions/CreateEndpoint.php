<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Endpoint;
use App\Models\EndpointCollection;
use App\Models\User;
use App\Services\ResponseBodyFormatter;

class CreateEndpoint
{
    public function __construct(private ResponseBodyFormatter $formatter) {}

    public function handle(
        User $user,
        EndpointCollection $collection,
        string $name,
        string $slug,
        string $method,
        int $statusCode,
        string $contentType,
        ?string $description = null,
        ?string $responseBody = null,
        bool $isActive = true,
    ): Endpoint {
        $slug = $this->uniqueSlug($collection, $slug, $method);

        return $collection->endpoints()->create([
            'user_id' => $user->id,
            'name' => $name,
            'description' => $description,
            'slug' => $slug,
            'method' => $method,
            'status_code' => $statusCode,
            'content_type' => $contentType,
            'response_body' => $this->formatter->format($contentType, $responseBody),
            'is_active' => $isActive,
        ]);
    }

    private function uniqueSlug(EndpointCollection $collection, string $slug, string $method): string
    {
        if (! $collection->endpoints()->where('slug', $slug)->where('method', $method)->exists()) {
            return $slug;
        }

        $i = 1;
        do {
            $candidate = "{$slug}-{$i}";
            $i++;
        } while ($collection->endpoints()->where('slug', $candidate)->where('method', $method)->exists());

        return $candidate;
    }
}
