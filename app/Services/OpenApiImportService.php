<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EndpointCollection;
use App\Models\User;
use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Response;
use cebe\openapi\spec\Schema;
use Illuminate\Support\Str;

class OpenApiImportService
{
    public function __construct(
        private CollectionImportService $collectionImportService,
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
        $collectionData = [
            'name' => $openApi->info->title ?? 'Imported API',
            'description' => $openApi->info->description ?? null,
            'endpoints' => [],
        ];

        foreach ($openApi->paths as $path => $pathItem) {
            $methods = ['get', 'post', 'put', 'patch', 'delete'];

            foreach ($methods as $method) {
                /** @var Operation|null $operation */
                $operation = $pathItem->$method ?? null;

                if ($operation === null) {
                    continue;
                }

                $collectionData['endpoints'][] = $this->buildEndpointData(
                    $path,
                    strtoupper($method),
                    $operation,
                );
            }
        }

        return $this->collectionImportService->import($user, $collectionData);
    }

    private function buildEndpointData(string $path, string $method, Operation $operation): array
    {
        $slug = $this->pathToSlug($path);
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
                    $conditionalResponses[] = [
                        'condition_source' => 'header',
                        'condition_field' => 'X-Mock-Response',
                        'condition_operator' => 'equals',
                        'condition_value' => $code,
                        'status_code' => $numericCode,
                        'content_type' => $resolved['content_type'],
                        'response_body' => $resolved['body'],
                        'priority' => count($conditionalResponses),
                    ];
                }
            }
        }

        return [
            'name' => $name,
            'slug' => $slug,
            'method' => $method,
            'status_code' => $statusCode,
            'content_type' => $contentType,
            'response_body' => $responseBody,
            'is_active' => true,
            'conditional_responses' => $conditionalResponses,
        ];
    }

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

    private function pathToSlug(string $path): string
    {
        $cleaned = str_replace(['{', '}'], '', $path);
        $cleaned = trim($cleaned, '/');
        $slug = Str::slug(str_replace('/', '-', $cleaned));

        if (empty($slug)) {
            $slug = 'root';
        }

        return $slug;
    }

    private function isSuccessCode(string $code): bool
    {
        return is_numeric($code) && (int) $code >= 200 && (int) $code < 300;
    }
}
