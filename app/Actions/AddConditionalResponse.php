<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ConditionalResponse;
use App\Models\Endpoint;

class AddConditionalResponse
{
    public function handle(
        Endpoint $endpoint,
        string $conditionSource,
        string $conditionField,
        string $conditionOperator,
        string $conditionValue,
        int $statusCode,
        string $contentType,
        string $responseBody = '',
    ): ConditionalResponse {
        $body = $this->formatBody($contentType, $responseBody);

        $priority = $endpoint->conditionalResponses()->max('priority') + 1;

        return $endpoint->conditionalResponses()->create([
            'condition_source' => $conditionSource,
            'condition_field' => $conditionField,
            'condition_operator' => $conditionOperator,
            'condition_value' => $conditionValue,
            'status_code' => $statusCode,
            'content_type' => $contentType,
            'response_body' => $body,
            'priority' => $priority,
        ]);
    }

    private function formatBody(string $contentType, string $body): string
    {
        if ($body === '') {
            return $body;
        }

        if (str_contains($contentType, 'json')) {
            $decoded = json_decode($body);
            if (json_last_error() === JSON_ERROR_NONE) {
                return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        }

        return $body;
    }
}
