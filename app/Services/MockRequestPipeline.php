<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\EndpointNotFoundException;
use App\Exceptions\MethodNotAllowedException;
use App\Models\Endpoint;
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
            $endpoint = $this->resolver->resolve($collectionSlug, $endpointSlug, $request->method(), 'rest');
        } catch (MethodNotAllowedException $e) {
            return response('Method Not Allowed', 405)
                ->header('Allow', $e->getAllowedMethods())
                ->header('Content-Type', 'application/json');
        } catch (EndpointNotFoundException) {
            return response('Not Found', 404)->header('Content-Type', 'application/json');
        }

        $endpoint->load('conditionalResponses');
        $pathSegments = $path ? explode('/', $path) : [];

        return $this->runPipeline($request, $endpoint, $pathSegments);
    }

    public function handleSoap(Request $request, string $collectionSlug, string $endpointSlug): Response
    {
        try {
            $endpoint = $this->resolver->resolve($collectionSlug, $endpointSlug, 'POST', 'soap');
        } catch (MethodNotAllowedException) {
            return $this->soapFault('Method Not Allowed', 405);
        } catch (EndpointNotFoundException) {
            return $this->soapFault('Endpoint not found', 404);
        }

        $endpoint->load('conditionalResponses');

        return $this->runPipeline($request, $endpoint, []);
    }

    private function runPipeline(Request $request, Endpoint $endpoint, array $pathSegments): Response
    {
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

    private function soapFault(string $message, int $status): Response
    {
        $body = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
              <soap:Body>
                <soap:Fault>
                  <faultcode>soap:Client</faultcode>
                  <faultstring>{$message}</faultstring>
                </soap:Fault>
              </soap:Body>
            </soap:Envelope>
            XML;

        return response($body, $status)->header('Content-Type', 'text/xml');
    }
}
