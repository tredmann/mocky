<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\EndpointLogCreated;
use App\Models\ConditionalResponse;
use App\Models\Endpoint;
use App\Models\EndpointLog;
use Illuminate\Http\Request;

class MockRequestLogger
{
    public function log(
        Request $request,
        Endpoint $endpoint,
        ?ConditionalResponse $matched,
        int $responseStatus,
        ?string $responseBody,
    ): EndpointLog {
        $log = EndpointLog::create([
            'endpoint_id' => $endpoint->id,
            'matched_conditional_response_id' => $matched?->id,
            'request_method' => $request->method(),
            'request_ip' => $request->ip(),
            'request_user_agent' => $request->userAgent(),
            'request_headers' => $request->headers->all(),
            'request_query' => $request->query->all(),
            'request_body' => $request->getContent() ?: null,
            'response_status_code' => $responseStatus,
            'response_body' => $responseBody,
        ]);

        EndpointLogCreated::dispatch($log);

        return $log;
    }
}
