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
        $priority = $endpoint->conditionalResponses()->max('priority') + 1;

        return $endpoint->conditionalResponses()->create([
            'condition_source' => $conditionSource,
            'condition_field' => $conditionField,
            'condition_operator' => $conditionOperator,
            'condition_value' => $conditionValue,
            'status_code' => $statusCode,
            'content_type' => $contentType,
            'response_body' => $responseBody,
            'priority' => $priority,
        ]);
    }
}
