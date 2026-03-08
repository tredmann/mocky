<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\CreateEndpoint;
use App\Data\EndpointData;
use App\Models\Endpoint;
use App\Models\EndpointCollection;
use App\Models\User;
use Illuminate\Support\Str;

class EndpointImportService
{
    public function __construct(private CreateEndpoint $createEndpoint) {}

    public function import(User $user, EndpointData $data, EndpointCollection $collection): Endpoint
    {
        $slug = $data->slug ?: Str::slug($data->name);

        $endpoint = $this->createEndpoint->handle(
            $user,
            $collection,
            $data->name,
            $slug,
            $data->method,
            $data->statusCode,
            $data->contentType,
            $data->description,
            $data->responseBody,
            $data->isActive,
        );

        foreach ($data->conditionalResponses as $cr) {
            $endpoint->conditionalResponses()->create([
                'condition_source' => $cr->conditionSource,
                'condition_field' => $cr->conditionField,
                'condition_operator' => $cr->conditionOperator,
                'condition_value' => $cr->conditionValue,
                'status_code' => $cr->statusCode,
                'content_type' => $cr->contentType,
                'response_body' => $cr->responseBody,
                'priority' => $cr->priority,
            ]);
        }

        return $endpoint;
    }
}
