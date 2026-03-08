<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\EndpointNotFoundException;
use App\Exceptions\MethodNotAllowedException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MockRequestPipeline
{
    public function __construct(
        private EndpointResolver $resolver,
        private ConditionalMatcher $matcher,
        private MockRequestLogger $logger,
    ) {}

    public function handle(Request $request, string $collectionSlug, string $endpointSlug, string $path): Response
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

        if ($matched !== null) {
            $responseBody = $matched->response_body ?? $endpoint->response_body;
            $responseStatus = $matched->status_code;
            $responseType = $matched->content_type;
        } else {
            $responseBody = $endpoint->response_body;
            $responseStatus = $endpoint->status_code;
            $responseType = $endpoint->content_type;
        }

        $this->logger->log($request, $endpoint, $matched, $responseStatus, $responseBody);

        return response($responseBody ?? '', $responseStatus)
            ->header('Content-Type', $responseType);
    }
}
