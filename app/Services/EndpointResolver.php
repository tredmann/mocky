<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\EndpointNotFoundException;
use App\Exceptions\MethodNotAllowedException;
use App\Models\Endpoint;
use App\Models\EndpointCollection;

class EndpointResolver
{
    /**
     * @throws EndpointNotFoundException
     * @throws MethodNotAllowedException
     */
    public function resolve(string $collectionSlug, string $endpointSlug, string $method): Endpoint
    {
        $collection = EndpointCollection::where('slug', $collectionSlug)->first();

        if (! $collection) {
            throw new EndpointNotFoundException;
        }

        $endpoint = $collection->endpoints()
            ->where('slug', $endpointSlug)
            ->where('method', $method)
            ->first();

        if (! $endpoint) {
            $allowedMethods = $collection->endpoints()
                ->where('slug', $endpointSlug)
                ->pluck('method');

            if ($allowedMethods->isEmpty()) {
                throw new EndpointNotFoundException;
            }

            throw new MethodNotAllowedException($allowedMethods->join(', '));
        }

        if (! $endpoint->is_active) {
            throw new EndpointNotFoundException;
        }

        return $endpoint;
    }
}
