<?php

namespace App\Services;

use App\Models\Endpoint;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EndpointExportService
{
    public function export(Endpoint $endpoint): StreamedResponse
    {
        $data = [
            'name' => $endpoint->name,
            'slug' => $endpoint->slug,
            'method' => $endpoint->method,
            'status_code' => $endpoint->status_code,
            'content_type' => $endpoint->content_type,
            'response_body' => $endpoint->response_body,
            'is_active' => $endpoint->is_active,
            'conditional_responses' => $endpoint->conditionalResponses()
                ->get()
                ->map(fn ($cr) => [
                    'condition_source' => $cr->condition_source,
                    'condition_field' => $cr->condition_field,
                    'condition_operator' => $cr->condition_operator,
                    'condition_value' => $cr->condition_value,
                    'status_code' => $cr->status_code,
                    'content_type' => $cr->content_type,
                    'response_body' => $cr->response_body,
                    'priority' => $cr->priority,
                ])
                ->all(),
        ];

        $filename = str($endpoint->name)->slug()->append('.json')->toString();

        return response()->streamDownload(
            fn () => print json_encode($data, JSON_PRETTY_PRINT),
            $filename,
            ['Content-Type' => 'application/json'],
        );
    }
}
