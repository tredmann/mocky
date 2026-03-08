<?php

declare(strict_types=1);

namespace App\Data;

readonly class CollectionData
{
    /**
     * @param  list<EndpointData>  $endpoints
     */
    public function __construct(
        public string $name,
        public ?string $description,
        public ?string $slug,
        public array $endpoints,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            description: isset($data['description']) ? (string) $data['description'] : null,
            slug: isset($data['slug']) ? (string) $data['slug'] : null,
            endpoints: array_map(
                fn (array $ep) => EndpointData::fromArray($ep),
                $data['endpoints'] ?? [],
            ),
        );
    }
}
