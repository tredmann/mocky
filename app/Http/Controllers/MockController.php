<?php

namespace App\Http\Controllers;

use App\Models\EndpointCollection;
use App\Models\EndpointLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MockController extends Controller
{
    public function handle(Request $request, string $collectionSlug, string $endpointSlug, string $path = ''): Response
    {
        $collection = EndpointCollection::where('slug', $collectionSlug)->first();

        if (! $collection) {
            return response('Not Found', 404)->header('Content-Type', 'application/json');
        }

        $endpoint = $collection->endpoints()->where('slug', $endpointSlug)->first();

        if (! $endpoint) {
            return response('Not Found', 404)->header('Content-Type', 'application/json');
        }

        if (! $endpoint->is_active) {
            return response('Not Found', 404)->header('Content-Type', 'application/json');
        }

        if ($request->method() !== $endpoint->method) {
            return response('Method Not Allowed', 405)
                ->header('Allow', $endpoint->method)
                ->header('Content-Type', 'application/json');
        }

        $endpoint->load('conditionalResponses');

        $pathSegments = $path ? explode('/', $path) : [];
        $matchedConditional = null;

        foreach ($endpoint->conditionalResponses as $conditional) {
            if ($conditional->matches($request, $pathSegments)) {
                $matchedConditional = $conditional;
                break;
            }
        }

        $responseBody = $matchedConditional ? $matchedConditional->response_body : $endpoint->response_body;
        $responseStatus = $matchedConditional ? $matchedConditional->status_code : $endpoint->status_code;
        $responseType = $matchedConditional ? $matchedConditional->content_type : $endpoint->content_type;

        EndpointLog::create([
            'endpoint_id' => $endpoint->id,
            'matched_conditional_response_id' => $matchedConditional?->id,
            'request_method' => $request->method(),
            'request_ip' => $request->ip(),
            'request_user_agent' => $request->userAgent(),
            'request_headers' => $request->headers->all(),
            'request_query' => $request->query->all(),
            'request_body' => $request->getContent() ?: null,
            'response_status_code' => $responseStatus,
            'response_body' => $responseBody,
        ]);

        return response($responseBody ?? '', $responseStatus)
            ->header('Content-Type', $responseType);
    }
}
