<?php

namespace App\Services;

use App\Models\EndpointCollection;
use App\Models\User;

class CollectionImportService
{
    public function __construct(private EndpointImportService $endpointImportService) {}

    public function import(User $user, array $data): EndpointCollection
    {
        /** @var EndpointCollection $collection */
        $collection = $user->endpointCollections()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        foreach ($data['endpoints'] ?? [] as $endpointData) {
            $this->endpointImportService->import($user, $endpointData, $collection);
        }

        return $collection;
    }
}
