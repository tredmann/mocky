<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EndpointCollection;
use App\Models\User;
use Illuminate\Support\Str;

class PostmanImportService
{
    public function __construct(
        private CollectionImportService $collectionImportService,
    ) {}

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

        $collectionData = [
            'name' => $info['name'] ?? 'Imported Postman Collection',
            'description' => $info['description'] ?? null,
            'endpoints' => [],
        ];

        $items = $postmanData['item'] ?? [];
        $this->processItems($items, $collectionData['endpoints']);

        return $this->collectionImportService->import($user, $collectionData);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  list<array<string, mixed>>  $endpoints
     *
     * @param-out list<array<string, mixed>>  $endpoints
     */
    private function processItems(array $items, array &$endpoints, string $prefix = ''): void
    {
        $rawItems = [];
        $this->collectItems($items, $rawItems, $prefix);

        // Group by (base_slug, method) so path-variant requests merge into one endpoint
        $groups = [];
        foreach ($rawItems as $rawItem) {
            $key = $rawItem['base_slug'].'|'.$rawItem['method'];
            $groups[$key][] = $rawItem;
        }

        foreach ($groups as $group) {
            $endpoints[] = $this->buildGroupEndpoint($group);
        }
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
            [$baseSlug, $pathSegment] = $this->splitPath($path);

            $rawItems[] = [
                'item' => $item,
                'method' => $method,
                'base_slug' => $baseSlug,
                'path_segment' => $pathSegment,
                'prefix' => $prefix,
            ];
        }
    }

    /**
     * Split a URL path into a base slug and an optional trailing variable segment.
     *
     * Returns a concrete value (e.g. "1") for numeric trailing segments, or "__any__"
     * for template-style params (":id"), or null when there is no trailing variable.
     *
     * @return array{string, string|null}
     */
    private function splitPath(string $path): array
    {
        // Strip Postman template variables like {{base_url}} and path params like {id}
        $cleaned = preg_replace('/\{\{[^}]+\}\}/', '', $path) ?? '';
        $cleaned = preg_replace('/\{[^}]+\}/', '', $cleaned) ?? '';
        $cleaned = preg_replace('/\/+/', '/', $cleaned) ?? '';
        $segments = array_values(array_filter(explode('/', trim($cleaned, '/'))));

        if (empty($segments)) {
            return ['endpoint', null];
        }

        $last = end($segments);

        if (count($segments) > 1 && $this->isVariableSegment($last)) {
            array_pop($segments);
            $baseSlug = Str::slug(implode('-', $segments));
            // Concrete numeric values produce an `equals` condition; placeholder params produce `not_equals ""`
            $pathSegment = is_numeric($last) ? $last : '__any__';

            return [$baseSlug !== '' ? $baseSlug : 'endpoint', $pathSegment];
        }

        $baseSlug = Str::slug(implode('-', $segments));

        return [$baseSlug !== '' ? $baseSlug : 'endpoint', null];
    }

    private function isVariableSegment(string $segment): bool
    {
        return is_numeric($segment) || str_starts_with($segment, ':');
    }

    /**
     * @param  list<array<string, mixed>>  $group
     * @return array<string, mixed>
     */
    private function buildGroupEndpoint(array $group): array
    {
        // Separate the base item (no extra path segment) from path-variant items
        $baseItem = null;
        $variantItems = [];

        foreach ($group as $rawItem) {
            if ($rawItem['path_segment'] === null) {
                $baseItem ??= $rawItem;
            } else {
                $variantItems[] = $rawItem;
            }
        }

        // If every item in the group has a path segment, promote the first as the base
        if ($baseItem === null) {
            $baseItem = array_shift($variantItems);
        }

        $endpoint = $this->buildEndpointData($baseItem['item'], $baseItem['base_slug'], $baseItem['prefix']);

        // Add a path-conditional response for each variant
        $priority = count($endpoint['conditional_responses']);
        foreach ($variantItems as $rawItem) {
            $variantData = $this->buildEndpointData($rawItem['item'], $rawItem['base_slug'], $rawItem['prefix']);
            $isTemplate = $rawItem['path_segment'] === '__any__';

            $endpoint['conditional_responses'][] = [
                'condition_source' => 'path',
                'condition_field' => '0',
                'condition_operator' => $isTemplate ? 'not_equals' : 'equals',
                'condition_value' => $isTemplate ? '' : $rawItem['path_segment'],
                'status_code' => $variantData['status_code'],
                'content_type' => $variantData['content_type'],
                'response_body' => $variantData['response_body'],
                'priority' => $priority++,
            ];
        }

        return $endpoint;
    }

    /** @return array<string, mixed> */
    private function buildEndpointData(array $item, string $slug, string $prefix): array
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

            foreach ($responses as $index => $response) {
                $respCode = $response['code'] ?? 200;
                $respContentType = $this->extractContentType($response);
                $respBody = $response['body'] ?? null;

                if (! $defaultSet) {
                    $statusCode = (int) $respCode;
                    $contentType = $respContentType;
                    $responseBody = $respBody;
                    $defaultSet = true;
                } else {
                    $conditionalResponses[] = [
                        'condition_source' => 'header',
                        'condition_field' => 'X-Mock-Response',
                        'condition_operator' => 'equals',
                        'condition_value' => (string) ($response['name'] ?? $respCode),
                        'status_code' => (int) $respCode,
                        'content_type' => $respContentType,
                        'response_body' => $respBody,
                        'priority' => count($conditionalResponses),
                    ];
                }
            }
        }

        if ($responseBody === null) {
            $responseBody = $this->extractBodyFromRequest($request);
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
