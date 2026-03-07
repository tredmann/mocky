<?php

namespace App\Services;

use App\Models\EndpointCollection;
use App\Models\User;
use Illuminate\Support\Str;

class CollectionImportService
{
    public function __construct(private EndpointImportService $endpointImportService) {}

    public function import(User $user, array $data): EndpointCollection
    {
        $slug = isset($data['slug']) && ! EndpointCollection::where('slug', $data['slug'])->exists()
            ? $data['slug']
            : Str::uuid()->toString();

        /** @var EndpointCollection $collection */
        $collection = $user->endpointCollections()->create([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
        ]);

        foreach ($data['endpoints'] ?? [] as $endpointData) {
            $this->endpointImportService->import($user, $endpointData, $collection);
        }

        return $collection;
    }
}
