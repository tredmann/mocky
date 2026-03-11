<?php

declare(strict_types=1);

namespace App\Data;

readonly class EndpointData
{
    /**
     * @param  list<ConditionalResponseData>  $conditionalResponses
     */
    public function __construct(
        public string $name,
        public ?string $slug,
        public string $method,
        public int $statusCode,
        public string $contentType,
        public ?string $responseBody,
        public bool $isActive,
        public ?string $description,
        public array $conditionalResponses,
        public ?string $type = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            slug: isset($data['slug']) ? (string) $data['slug'] : null,
            method: (string) $data['method'],
            statusCode: (int) $data['status_code'],
            contentType: (string) $data['content_type'],
            responseBody: isset($data['response_body']) ? (string) $data['response_body'] : null,
            isActive: (bool) ($data['is_active'] ?? true),
            description: isset($data['description']) ? (string) $data['description'] : null,
            conditionalResponses: array_map(
                fn (array $cr) => ConditionalResponseData::fromArray($cr),
                $data['conditional_responses'] ?? [],
            ),
            type: isset($data['type']) ? (string) $data['type'] : null,
        );
    }

    /**
     * Return a new instance with additional conditional responses appended.
     *
     * @param  list<ConditionalResponseData>  $extra
     */
    public function withExtraConditionals(array $extra): self
    {
        return new self(
            name: $this->name,
            slug: $this->slug,
            method: $this->method,
            statusCode: $this->statusCode,
            contentType: $this->contentType,
            responseBody: $this->responseBody,
            isActive: $this->isActive,
            description: $this->description,
            conditionalResponses: array_merge($this->conditionalResponses, $extra),
            type: $this->type,
        );
    }
}
