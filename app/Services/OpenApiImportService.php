<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\CollectionData;
use App\Data\ConditionalResponseData;
use App\Data\EndpointData;
use App\Models\EndpointCollection;
use App\Models\User;
use Symfony\Component\Yaml\Yaml;

class OpenApiImportService extends AbstractImportService
{
    public function importFromFile(User $user, string $filePath): EndpointCollection
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        $spec = match ($extension) {
            'yaml', 'yml' => Yaml::parseFile($filePath, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE),
            'json' => json_decode(file_get_contents($filePath), true),
            default => throw new \InvalidArgumentException("Unsupported file format: {$extension}. Use .yaml, .yml, or .json."),
        };

        if (! is_array($spec)) {
            throw new \InvalidArgumentException('Invalid OpenAPI spec: could not parse file.');
        }

        return $this->import($user, $spec);
    }

    /** @param array<string, mixed> $spec */
    public function import(User $user, array $spec): EndpointCollection
    {
        $rawItems = [];

        foreach ($spec['paths'] ?? [] as $path => $pathItem) {
            if (! is_array($pathItem)) {
                continue;
            }

            foreach (['get', 'post', 'put', 'patch', 'delete'] as $method) {
                $operation = $pathItem[$method] ?? null;

                if (! is_array($operation)) {
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

        $info = $spec['info'] ?? [];

        $collectionData = new CollectionData(
            name: $info['title'] ?? 'Imported API',
            description: $info['description'] ?? null,
            slug: null,
            endpoints: $this->buildEndpoints($rawItems),
        );

        return $this->collectionImportService->import($user, $collectionData);
    }

    /** @param array<string, mixed> $rawItem */
    protected function buildEndpointData(array $rawItem): EndpointData
    {
        return $this->buildEndpointDataFromOperation(
            $rawItem['path'],
            $rawItem['method'],
            $rawItem['operation'],
            $rawItem['base_slug'],
        );
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    private function buildEndpointDataFromOperation(string $path, string $method, array $operation, string $slug): EndpointData
    {
        $name = $operation['operationId']
            ?? $operation['summary']
            ?? "{$method} {$path}";

        $statusCode = 200;
        $contentType = 'application/json';
        $responseBody = null;
        $conditionalResponses = [];

        $defaultSet = false;

        foreach ($operation['responses'] ?? [] as $code => $response) {
            if (! is_array($response) || isset($response['$ref'])) {
                continue;
            }

            $code = (string) $code;
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
    private function resolveResponseData(array $response): array
    {
        $contentType = 'application/json';
        $body = null;

        foreach ($response['content'] ?? [] as $mediaTypeName => $mediaType) {
            $contentType = $mediaTypeName;

            if (isset($mediaType['example'])) {
                $example = $mediaType['example'];
                $body = is_string($example)
                    ? $example
                    : json_encode($example, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            } elseif (isset($mediaType['schema'])) {
                $generated = $this->generateExampleFromSchema($mediaType['schema']);
                if ($generated !== null) {
                    $body = json_encode($generated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                }
            }

            break;
        }

        if ($body === null && isset($response['description'])) {
            $body = json_encode(['message' => $response['description']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        return ['content_type' => $contentType, 'body' => $body];
    }

    /** @param array<string, mixed> $schema */
    private function generateExampleFromSchema(array $schema): mixed
    {
        if (isset($schema['example'])) {
            return $schema['example'];
        }

        $type = $schema['type'] ?? null;

        if ($type === 'object' || isset($schema['properties'])) {
            $obj = [];
            foreach ($schema['properties'] ?? [] as $name => $property) {
                if (is_array($property)) {
                    $obj[$name] = $this->generateExampleFromSchema($property);
                }
            }

            return $obj;
        }

        if ($type === 'array' && isset($schema['items']) && is_array($schema['items'])) {
            $item = $this->generateExampleFromSchema($schema['items']);

            return $item !== null ? [$item] : [];
        }

        if (isset($schema['enum'])) {
            return $schema['enum'][0] ?? null;
        }

        $format = $schema['format'] ?? null;

        return match ($type) {
            'string' => match ($format) {
                'date' => '2024-01-01',
                'date-time' => '2024-01-01T00:00:00Z',
                'email' => 'user@example.com',
                'uuid' => '550e8400-e29b-41d4-a716-446655440000',
                default => 'string',
            },
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
