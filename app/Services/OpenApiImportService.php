<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\CollectionData;
use App\Data\ConditionalResponseData;
use App\Data\EndpointData;
use App\Models\EndpointCollection;
use App\Models\User;
use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Response;
use cebe\openapi\spec\Schema;

class OpenApiImportService
{
    public function __construct(
        private CollectionImportService $collectionImportService,
        private ImportPathResolver $pathResolver,
    ) {}

    public function importFromFile(User $user, string $filePath): EndpointCollection
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        $openApi = match ($extension) {
            'yaml', 'yml' => Reader::readFromYamlFile($filePath),
            'json' => Reader::readFromJsonFile($filePath),
            default => throw new \InvalidArgumentException("Unsupported file format: {$extension}. Use .yaml, .yml, or .json."),
        };

        return $this->import($user, $openApi);
    }

    public function import(User $user, OpenApi $openApi): EndpointCollection
    {
        // Collect all (path, method, operation) tuples with their slug info
        $rawItems = [];
        foreach ($openApi->paths as $path => $pathItem) {
            foreach (['get', 'post', 'put', 'patch', 'delete'] as $method) {
                /** @var Operation|null $operation */
                $operation = $pathItem->$method ?? null;

                if ($operation === null) {
                    continue;
                }

                [$baseSlug, $pathSegment] = $this->pathResolver->splitPath($path);

                $rawItems[] = [
                    'path' => $path,
                    'method' => strtoupper($method),
                    'operation' => $operation,
                    'base_slug' => $baseSlug,
                    'path_segment' => $pathSegment,
                ];
            }
        }

        // Group by (base_slug, method) so path-variant operations merge into one endpoint
        $groups = $this->pathResolver->groupBySlugAndMethod($rawItems);

        $endpoints = [];
        foreach ($groups as $group) {
            $endpoints[] = $this->buildGroupEndpoint($group);
        }

        $collectionData = new CollectionData(
            name: $openApi->info->title ?? 'Imported API',
            description: $openApi->info->description ?? null,
            slug: null,
            endpoints: $endpoints,
        );

        return $this->collectionImportService->import($user, $collectionData);
    }

    /** @param list<array<string, mixed>> $group */
    private function buildGroupEndpoint(array $group): EndpointData
    {
        [$baseItem, $variantItems] = $this->pathResolver->separateBaseAndVariants($group);

        $endpoint = $this->buildEndpointData(
            $baseItem['path'],
            $baseItem['method'],
            $baseItem['operation'],
            $baseItem['base_slug'],
        );

        // Add a path-conditional response for each variant
        $pathConditionals = $this->pathResolver->buildPathConditionals(
            $variantItems,
            fn (array $rawItem) => $this->buildEndpointData(
                $rawItem['path'],
                $rawItem['method'],
                $rawItem['operation'],
                $rawItem['base_slug'],
            ),
            count($endpoint->conditionalResponses),
        );

        return $endpoint->withExtraConditionals($pathConditionals);
    }

    private function buildEndpointData(string $path, string $method, Operation $operation, string $slug): EndpointData
    {
        $name = $operation->operationId
            ?? $operation->summary
            ?? "{$method} {$path}";

        $statusCode = 200;
        $contentType = 'application/json';
        $responseBody = null;
        $conditionalResponses = [];

        if ($operation->responses) {
            $defaultSet = false;

            foreach ($operation->responses->getResponses() as $code => $response) {
                $code = (string) $code;
                if ($response instanceof \cebe\openapi\spec\Reference) {
                    continue;
                }

                /** @var Response $response */
                $resolved = $this->resolveResponseData($response);

                if (! $defaultSet && $this->isSuccessCode($code)) {
                    $statusCode = (int) $code;
                    $contentType = $resolved['content_type'];
                    $responseBody = $resolved['body'];
                    $defaultSet = true;
                } else {
                    $numericCode = is_numeric($code) ? (int) $code : 200;
                    $conditionalResponses[] = new ConditionalResponseData(
                        conditionSource: 'header',
                        conditionField: 'X-Mock-Response',
                        conditionOperator: 'equals',
                        conditionValue: $code,
                        statusCode: $numericCode,
                        contentType: $resolved['content_type'],
                        responseBody: $resolved['body'],
                        priority: count($conditionalResponses),
                    );
                }
            }
        }

        return new EndpointData(
            name: $name,
            slug: $slug,
            method: $method,
            statusCode: $statusCode,
            contentType: $contentType,
            responseBody: $responseBody,
            isActive: true,
            description: null,
            conditionalResponses: $conditionalResponses,
        );
    }

    /** @return array{content_type: string, body: string|null} */
    private function resolveResponseData(Response $response): array
    {
        $contentType = 'application/json';
        $body = null;

        if ($response->content) {
            foreach ($response->content as $mediaTypeName => $mediaType) {
                $contentType = $mediaTypeName;

                if ($mediaType->example !== null) {
                    $body = is_string($mediaType->example)
                        ? $mediaType->example
                        : json_encode($mediaType->example, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                } elseif ($mediaType->schema) {
                    $generated = $this->generateExampleFromSchema($mediaType->schema);
                    if ($generated !== null) {
                        $body = json_encode($generated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    }
                }

                break;
            }
        }

        if ($body === null && $response->description) {
            $body = json_encode(['message' => $response->description], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        return ['content_type' => $contentType, 'body' => $body];
    }

    private function generateExampleFromSchema(Schema $schema): mixed
    {
        if ($schema->example !== null) {
            return $schema->example;
        }

        $type = $schema->type;

        if ($type === 'object' || $schema->properties) {
            $obj = [];
            if ($schema->properties) {
                foreach ($schema->properties as $name => $property) {
                    if ($property instanceof Schema) {
                        $obj[$name] = $this->generateExampleFromSchema($property);
                    }
                }
            }

            return $obj;
        }

        if ($type === 'array' && $schema->items) {
            $item = $schema->items instanceof Schema
                ? $this->generateExampleFromSchema($schema->items)
                : null;

            return $item !== null ? [$item] : [];
        }

        if ($schema->enum) {
            return $schema->enum[0];
        }

        return match ($type) {
            'string' => $schema->format === 'date' ? '2024-01-01'
                : ($schema->format === 'date-time' ? '2024-01-01T00:00:00Z'
                    : ($schema->format === 'email' ? 'user@example.com'
                        : ($schema->format === 'uuid' ? '550e8400-e29b-41d4-a716-446655440000'
                            : 'string'))),
            'integer' => 0,
            'number' => 0.0,
            'boolean' => false,
            default => null,
        };
    }

    private function isSuccessCode(string $code): bool
    {
        return is_numeric($code) && (int) $code >= 200 && (int) $code < 300;
    }
}
