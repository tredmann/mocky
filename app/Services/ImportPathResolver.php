<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\ConditionalResponseData;
use App\Data\EndpointData;
use Illuminate\Support\Str;

class ImportPathResolver
{
    /**
     * Split a URL path into a base slug and an optional trailing variable segment.
     *
     * Template variables ({id}, {{base_url}}, :param) are handled as follows:
     * - {{double-brace}} vars are stripped entirely (Postman environment vars)
     * - {single-brace} vars in middle segments are stripped for slug generation
     * - Trailing variable segments ({id}, :id, numeric) produce a path_segment value
     *
     * Returns "__any__" for template params, a concrete string for numeric segments,
     * or null when there is no trailing variable.
     *
     * @return array{string, string|null}
     */
    public function splitPath(string $path): array
    {
        // Strip Postman-style double-brace template variables (e.g. {{base_url}})
        $cleaned = preg_replace('/\{\{[^}]+\}\}/', '', $path) ?? '';
        $cleaned = preg_replace('/\/+/', '/', $cleaned);

        $segments = array_values(array_filter(explode('/', trim($cleaned, '/'))));

        if (empty($segments)) {
            return ['endpoint', null];
        }

        $last = end($segments);

        if (count($segments) > 1 && $this->isVariableSegment($last)) {
            array_pop($segments);
            $baseSlug = $this->segmentsToSlug($segments);
            $pathSegment = is_numeric($last) ? $last : '__any__';

            return [$baseSlug, $pathSegment];
        }

        return [$this->segmentsToSlug($segments), null];
    }

    /**
     * Check whether a path segment represents a variable (template param, numeric ID, or :param).
     */
    public function isVariableSegment(string $segment): bool
    {
        // OpenAPI/generic path param: {petId}
        if (preg_match('/^\{[^{}]+\}$/', $segment)) {
            return true;
        }

        // Concrete numeric segment or :param style (Postman/Express)
        return is_numeric($segment) || str_starts_with($segment, ':');
    }

    /**
     * Convert path segments into a URL-safe slug, stripping any remaining template variables.
     *
     * @param  list<string>  $segments
     */
    public function segmentsToSlug(array $segments): string
    {
        $clean = [];
        foreach ($segments as $segment) {
            $s = preg_replace('/\{[^}]+\}/', '', $segment) ?? '';
            $s = trim($s);
            if ($s !== '') {
                $clean[] = $s;
            }
        }

        $slug = Str::slug(implode('-', $clean));

        return $slug !== '' ? $slug : 'endpoint';
    }

    /**
     * Group raw import items by (base_slug, method).
     *
     * Each item must have 'base_slug' and 'method' keys.
     *
     * @param  list<array<string, mixed>>  $rawItems
     * @return array<string, list<array<string, mixed>>>
     */
    public function groupBySlugAndMethod(array $rawItems): array
    {
        $groups = [];
        foreach ($rawItems as $rawItem) {
            $key = $rawItem['base_slug'].'|'.$rawItem['method'];
            $groups[$key][] = $rawItem;
        }

        return $groups;
    }

    /**
     * Separate a group of raw items into a base item and variant items.
     *
     * The base item is the first item with no path_segment (or the first item overall
     * if all items have a path_segment).
     *
     * @param  list<array<string, mixed>>  $group
     * @return array{array<string, mixed>, list<array<string, mixed>>}
     */
    public function separateBaseAndVariants(array $group): array
    {
        $baseItem = null;
        $variantItems = [];

        foreach ($group as $rawItem) {
            if ($rawItem['path_segment'] === null) {
                $baseItem ??= $rawItem;
            } else {
                $variantItems[] = $rawItem;
            }
        }

        if ($baseItem === null) {
            $baseItem = array_shift($variantItems);
        }

        return [$baseItem, $variantItems];
    }

    /**
     * Build path conditional response entries from variant items.
     *
     * The $buildEndpointData callable receives a raw item and must return an EndpointData
     * whose status_code, content_type, and response_body are used for the conditional.
     *
     * @param  list<array<string, mixed>>  $variantItems
     * @param  callable(array<string, mixed>): EndpointData  $buildEndpointData
     * @param  int  $startPriority  Priority counter start value
     * @return list<ConditionalResponseData>
     */
    public function buildPathConditionals(array $variantItems, callable $buildEndpointData, int $startPriority = 0): array
    {
        $conditionals = [];
        $priority = $startPriority;

        foreach ($variantItems as $rawItem) {
            $variantData = $buildEndpointData($rawItem);
            $isTemplate = $rawItem['path_segment'] === '__any__';

            $conditionals[] = new ConditionalResponseData(
                conditionSource: 'path',
                conditionField: '0',
                conditionOperator: $isTemplate ? 'not_equals' : 'equals',
                conditionValue: $isTemplate ? '' : (string) $rawItem['path_segment'],
                statusCode: $variantData->statusCode,
                contentType: $variantData->contentType,
                responseBody: $variantData->responseBody,
                priority: $priority++,
            );
        }

        return $conditionals;
    }
}
