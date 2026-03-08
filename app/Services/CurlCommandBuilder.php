<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ConditionOperator;
use App\Enums\ConditionSource;
use App\Models\ConditionalResponse;
use App\Models\Endpoint;

class CurlCommandBuilder
{
    public function forDefault(Endpoint $endpoint): string
    {
        return $this->build($endpoint->mock_url, $endpoint->method);
    }

    public function forConditional(Endpoint $endpoint, ConditionalResponse $cr): string
    {
        $url = $endpoint->mock_url;
        $headers = [];
        $body = null;
        $queryParams = [];
        $value = $this->exampleValue($cr);

        if ($cr->condition_source === ConditionSource::Query) {
            $queryParams[$cr->condition_field] = $value;
        } elseif ($cr->condition_source === ConditionSource::Header) {
            $headers[$cr->condition_field] = $value;
        } elseif ($cr->condition_source === ConditionSource::Body) {
            $body = json_encode([$cr->condition_field => $value]);
            $headers['Content-Type'] = 'application/json';
        } elseif ($cr->condition_source === ConditionSource::Path) {
            $index = (int) $cr->condition_field;
            $segments = array_fill(0, $index, ':segment');
            $segments[] = $value;
            $url .= '/'.implode('/', $segments);
        }

        return $this->build($url, $endpoint->method, $headers, $body, $queryParams);
    }

    private function exampleValue(ConditionalResponse $cr): string
    {
        if ($cr->condition_operator === ConditionOperator::NotEquals) {
            return 'not_'.$cr->condition_value;
        }

        return $cr->condition_value;
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, string>  $queryParams
     */
    private function build(string $url, string $method, array $headers = [], ?string $body = null, array $queryParams = []): string
    {
        $parts = ['curl'];

        if ($method !== 'GET') {
            $parts[] = "-X {$method}";
        }

        foreach ($headers as $name => $value) {
            $parts[] = '-H '.escapeshellarg("{$name}: {$value}");
        }

        if ($body !== null) {
            $parts[] = '-d '.escapeshellarg($body);
        }

        if ($queryParams !== []) {
            $url .= '?'.http_build_query($queryParams);
        }

        $parts[] = escapeshellarg($url);

        return implode(" \\\n  ", $parts);
    }
}
