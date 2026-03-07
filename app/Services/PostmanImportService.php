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
        foreach ($items as $item) {
            if (isset($item['item']) && is_array($item['item'])) {
                $folderName = $item['name'] ?? '';
                $newPrefix = $prefix ? "{$prefix}/{$folderName}" : $folderName;
                $this->processItems($item['item'], $endpoints, $newPrefix);

                continue;
            }

            if (! isset($item['request'])) {
                continue;
            }

            $endpoints[] = $this->buildEndpointData($item, $prefix);
        }
    }

    /** @return array<string, mixed> */
    private function buildEndpointData(array $item, string $prefix): array
    {
        $request = $item['request'];
        $name = $item['name'] ?? 'Unnamed request';

        $method = is_string($request['method'] ?? null)
            ? strtoupper($request['method'])
            : 'GET';

        $path = $this->extractPath($request['url'] ?? null);
        $slug = $this->buildSlug($name, $method, $path, $prefix);

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

    private function buildSlug(string $name, string $method, string $path, string $prefix): string
    {
        $slug = Str::slug($name);

        if (empty($slug)) {
            $cleaned = str_replace(['{', '}', ':'], '', $path);
            $cleaned = trim($cleaned, '/');
            $slug = Str::slug(str_replace('/', '-', $cleaned));

            if (empty($slug)) {
                $slug = 'endpoint';
            }

            $slug = strtolower($method).'-'.$slug;
        }

        return $slug;
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
