<?php

namespace App\Http\Controllers;

use App\Models\EndpointCollection;
use App\Services\MockRequestLogger;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MockController extends Controller
{
    public function __construct(private MockRequestLogger $logger) {}

    public function handle(Request $request, string $collectionSlug, string $endpointSlug, string $path = ''): Response
    {
        $collection = EndpointCollection::where('slug', $collectionSlug)->first();

        if (! $collection) {
            return response('Not Found', 404)->header('Content-Type', 'application/json');
        }

        $endpoint = $collection->endpoints()
            ->where('slug', $endpointSlug)
            ->where('method', $request->method())
            ->first();

        if (! $endpoint) {
            $allowedMethods = $collection->endpoints()
                ->where('slug', $endpointSlug)
                ->pluck('method');

            if ($allowedMethods->isEmpty()) {
                return response('Not Found', 404)->header('Content-Type', 'application/json');
            }

            return response('Method Not Allowed', 405)
                ->header('Allow', $allowedMethods->join(', '))
                ->header('Content-Type', 'application/json');
        }

        if (! $endpoint->is_active) {
            return response('Not Found', 404)->header('Content-Type', 'application/json');
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

        $this->logger->log($request, $endpoint, $matchedConditional, $responseStatus, $responseBody);

        return response($responseBody ?? '', $responseStatus)
            ->header('Content-Type', $responseType);
    }
}
