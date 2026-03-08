<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ConditionalResponse;
use App\Models\Endpoint;
use App\Services\ResponseBodyFormatter;

class AddConditionalResponse
{
    public function __construct(private ResponseBodyFormatter $formatter) {}

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
        $body = $this->formatter->format($contentType, $responseBody) ?? '';

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
}
