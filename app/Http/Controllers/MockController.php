<?php

namespace App\Http\Controllers;

use App\Exceptions\EndpointNotFoundException;
use App\Exceptions\MethodNotAllowedException;
use App\Services\ConditionalMatcher;
use App\Services\EndpointResolver;
use App\Services\MockRequestLogger;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MockController extends Controller
{
    public function __construct(
        private MockRequestLogger $logger,
        private EndpointResolver $resolver,
        private ConditionalMatcher $matcher,
    ) {}

    public function handle(Request $request, string $collectionSlug, string $endpointSlug, string $path = ''): Response
    {
        try {
            $endpoint = $this->resolver->resolve($collectionSlug, $endpointSlug, $request->method());
        } catch (MethodNotAllowedException $e) {
            return response('Method Not Allowed', 405)
                ->header('Allow', $e->getAllowedMethods())
                ->header('Content-Type', 'application/json');
        } catch (EndpointNotFoundException) {
            return response('Not Found', 404)->header('Content-Type', 'application/json');
        }

        $endpoint->load('conditionalResponses');
        $pathSegments = $path ? explode('/', $path) : [];
        $matched = $this->matcher->match($endpoint->conditionalResponses, $request, $pathSegments);

        $responseBody = $matched?->response_body ?? $endpoint->response_body;
        $responseStatus = $matched?->status_code ?? $endpoint->status_code;
        $responseType = $matched?->content_type ?? $endpoint->content_type;

        $this->logger->log($request, $endpoint, $matched, $responseStatus, $responseBody);

        return response($responseBody ?? '', $responseStatus)
            ->header('Content-Type', $responseType);
    }
}
