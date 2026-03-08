<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\EndpointData;

abstract class AbstractImportService
{
    public function __construct(
        protected CollectionImportService $collectionImportService,
        protected ImportPathResolver $pathResolver,
    ) {}

    /**
     * Group raw items by (base_slug, method), build one EndpointData per group.
     *
     * @param  list<array<string, mixed>>  $rawItems
     * @return list<EndpointData>
     */
    final protected function buildEndpoints(array $rawItems): array
    {
        $groups = $this->pathResolver->groupBySlugAndMethod($rawItems);

        return array_values(array_map(
            fn (array $group) => $this->buildGroupEndpoint($group),
            $groups,
        ));
    }

    /**
     * Merge a group of path-variant raw items into a single EndpointData.
     *
     * @param  list<array<string, mixed>>  $group
     */
    private function buildGroupEndpoint(array $group): EndpointData
    {
        [$baseItem, $variantItems] = $this->pathResolver->separateBaseAndVariants($group);

        $endpoint = $this->buildEndpointData($baseItem);

        $pathConditionals = $this->pathResolver->buildPathConditionals(
            $variantItems,
            fn (array $rawItem) => $this->buildEndpointData($rawItem),
            count($endpoint->conditionalResponses),
        );

        return $endpoint->withExtraConditionals($pathConditionals);
    }

    /**
     * Build an EndpointData from a single format-specific raw item array.
     *
     * @param  array<string, mixed>  $rawItem
     */
    abstract protected function buildEndpointData(array $rawItem): EndpointData;
}
