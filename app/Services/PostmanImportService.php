<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\CollectionData;
use App\Data\ConditionalResponseData;
use App\Data\EndpointData;
use App\Models\EndpointCollection;
use App\Models\User;

class PostmanImportService extends AbstractImportService
{
    public function importFromFile(User $user, string $filePath): EndpointCollection
    {
        $contents = file_get_contents($filePath);
        $data = json_decode($contents, true);

        if (! is_array($data) || ! isset($data['info'])) {
            throw new \InvalidArgumentException('Invalid Postman collection file.');
        }

        return $this->import($user, $data);
    }

    public function import(User $user, array $postmanData): EndpointCollection
    {
        $info = $postmanData['info'] ?? [];

        $rawItems = [];
        $this->collectItems($postmanData['item'] ?? [], $rawItems);

        $collectionData = new CollectionData(
            name: $info['name'] ?? 'Imported Postman Collection',
            description: $info['description'] ?? null,
            slug: null,
            endpoints: $this->buildEndpoints($rawItems),
        );

        return $this->collectionImportService->import($user, $collectionData);
    }

    protected function buildEndpointData(array $rawItem): EndpointData
    {
        return $this->buildEndpointDataFromItem(
            $rawItem['item'],
            $rawItem['base_slug'],
            $rawItem['prefix'],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  list<array<string, mixed>>  $rawItems
     *
     * @param-out list<array<string, mixed>>  $rawItems
     */
    private function collectItems(array $items, array &$rawItems, string $prefix = ''): void
    {
        foreach ($items as $item) {
            if (isset($item['item']) && is_array($item['item'])) {
                $folderName = $item['name'] ?? '';
                $newPrefix = $prefix ? "{$prefix}/{$folderName}" : $folderName;
                $this->collectItems($item['item'], $rawItems, $newPrefix);

                continue;
            }

            if (! isset($item['request'])) {
                continue;
            }

            $request = $item['request'];
            $method = strtoupper(is_string($request['method'] ?? null) ? $request['method'] : 'GET');
            $path = $this->extractPath($request['url'] ?? null);
            [$baseSlug, $pathSegment] = $this->pathResolver->splitPath($path);

            $rawItems[] = [
                'item' => $item,
                'method' => $method,
                'base_slug' => $baseSlug,
                'path_segment' => $pathSegment,
                'prefix' => $prefix,
            ];
        }
    }

    private function buildEndpointDataFromItem(array $item, string $slug, string $prefix): EndpointData
    {
        $request = $item['request'];
        $name = $item['name'] ?? 'Unnamed request';

        $method = is_string($request['method'] ?? null)
            ? strtoupper($request['method'])
            : 'GET';

        $statusCode = 200;
        $contentType = 'application/json';
        $responseBody = null;
        $conditionalResponses = [];

        $responses = $item['response'] ?? [];

        if (count($responses) > 0) {
            $defaultSet = false;

            foreach ($responses as $response) {
                $respCode = $response['code'] ?? 200;
                $respContentType = $this->extractContentType($response);
                $respBody = $response['body'] ?? null;

                if (! $defaultSet) {
                    $statusCode = (int) $respCode;
                    $contentType = $respContentType;
                    $responseBody = $respBody;
                    $defaultSet = true;
                } else {
                    $conditionalResponses[] = new ConditionalResponseData(
                        conditionSource: 'header',
                        conditionField: 'X-Mock-Response',
                        conditionOperator: 'equals',
                        conditionValue: (string) ($response['name'] ?? $respCode),
                        statusCode: (int) $respCode,
                        contentType: $respContentType,
                        responseBody: $respBody,
                        priority: count($conditionalResponses),
                    );
                }
            }
        }

        if ($responseBody === null) {
            $responseBody = $this->extractBodyFromRequest($request);
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

    private function extractPath(mixed $url): string
    {
        if (is_string($url)) {
            $parsed = parse_url($url);

            return $parsed['path'] ?? '/';
        }

        if (is_array($url) && isset($url['path'])) {
            return '/'.implode('/', $url['path']);
        }

        if (is_array($url) && isset($url['raw'])) {
            $parsed = parse_url($url['raw']);

            return $parsed['path'] ?? '/';
        }

        return '/';
    }

    private function extractContentType(array $response): string
    {
        $headers = $response['header'] ?? [];

        foreach ($headers as $header) {
            $key = $header['key'] ?? '';
            if (strtolower($key) === 'content-type') {
                return $header['value'] ?? 'application/json';
            }
        }

        if (isset($response['_postman_previewlanguage'])) {
            return match ($response['_postman_previewlanguage']) {
                'json' => 'application/json',
                'xml' => 'application/xml',
                'html' => 'text/html',
                'text' => 'text/plain',
                default => 'application/json',
            };
        }

        return 'application/json';
    }

    private function extractBodyFromRequest(array $request): ?string
    {
        $body = $request['body'] ?? null;

        if (! $body) {
            return null;
        }

        if (isset($body['raw']) && is_string($body['raw'])) {
            return $body['raw'];
        }

        return null;
    }
}
