<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\CreateEndpointCollection;
use App\Data\CollectionData;
use App\Models\EndpointCollection;
use App\Models\User;

class CollectionImportService
{
    public function __construct(
        private EndpointImportService $endpointImportService,
        private CreateEndpointCollection $createCollection,
    ) {}

    public function import(User $user, CollectionData $data): EndpointCollection
    {
        $collection = $this->createCollection->handle(
            $user,
            $data->name,
            $data->description,
            $data->slug,
        );

        foreach ($data->endpoints as $endpointData) {
            $this->endpointImportService->import($user, $endpointData, $collection);
        }

        return $collection;
    }
}
