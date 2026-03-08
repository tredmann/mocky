<?php

namespace App\Services;

use App\Actions\CreateEndpointCollection;
use App\Models\EndpointCollection;
use App\Models\User;

class CollectionImportService
{
    public function __construct(
        private EndpointImportService $endpointImportService,
        private CreateEndpointCollection $createCollection,
    ) {}

    public function import(User $user, array $data): EndpointCollection
    {
        $collection = $this->createCollection->handle(
            $user,
            $data['name'],
            $data['description'] ?? null,
            $data['slug'] ?? null,
        );

        foreach ($data['endpoints'] ?? [] as $endpointData) {
            $this->endpointImportService->import($user, $endpointData, $collection);
        }

        return $collection;
    }
}
