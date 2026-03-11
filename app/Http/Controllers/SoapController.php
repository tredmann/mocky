<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\MockRequestPipeline;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SoapController extends Controller
{
    public function __construct(private MockRequestPipeline $pipeline) {}

    public function handle(Request $request, string $collectionSlug, string $endpointSlug): Response
    {
        if (! str_contains($request->header('Content-Type', ''), 'xml')) {
            return $this->soapFault('Unsupported Media Type: Content-Type must be XML', 415);
        }

        return $this->pipeline->handleSoap($request, $collectionSlug, $endpointSlug);
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
