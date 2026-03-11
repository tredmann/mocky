<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\CollectionData;
use App\Data\ConditionalResponseData;
use App\Data\EndpointData;
use App\Models\EndpointCollection;
use App\Models\User;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Str;

class WsdlImportService
{
    private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB

    public function __construct(private CollectionImportService $collectionImportService) {}

    public function importFromFile(User $user, string $filePath): EndpointCollection
    {
        if (filesize($filePath) > self::MAX_FILE_SIZE) {
            throw new \InvalidArgumentException('File is too large (max 5MB).');
        }

        $xml = file_get_contents($filePath);

        if ($xml === false || $xml === '') {
            throw new \InvalidArgumentException('Could not read file.');
        }

        return $this->import($user, $xml);
    }

    public function import(User $user, string $wsdlXml): EndpointCollection
    {
        $doc = new DOMDocument;
        libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($wsdlXml);
        libxml_clear_errors();

        if (! $loaded) {
            throw new \InvalidArgumentException('Invalid WSDL: could not parse XML.');
        }

        $serviceName = $this->extractServiceName($doc) ?? 'WSDL Service';
        $collectionSlug = Str::slug($serviceName);

        $endpoints = $this->buildEndpoints($doc);

        if (empty($endpoints)) {
            throw new \InvalidArgumentException('No SOAP bindings found in WSDL.');
        }

        $collectionData = new CollectionData(
            name: $serviceName,
            description: null,
            slug: $collectionSlug,
            endpoints: $endpoints,
        );

        return $this->collectionImportService->import($user, $collectionData);
    }

    private function extractServiceName(DOMDocument $doc): ?string
    {
        $services = $doc->getElementsByTagNameNS('*', 'service');

        if ($services->length > 0) {
            $service = $services->item(0);

            if ($service instanceof DOMElement) {
                $name = $service->getAttribute('name');

                if ($name !== '') {
                    return $name;
                }
            }
        }

        // Fall back to definitions/@name
        $root = $doc->documentElement;

        if ($root instanceof DOMElement) {
            $name = $root->getAttribute('name');

            if ($name !== '') {
                return $name;
            }
        }

        return null;
    }

    /** @return list<EndpointData> */
    private function buildEndpoints(DOMDocument $doc): array
    {
        $bindings = $doc->getElementsByTagNameNS('*', 'binding');
        $endpoints = [];

        foreach ($bindings as $binding) {
            // Skip non-SOAP bindings (e.g. HTTP bindings in WSDL 2.0)
            if (! $this->hasSoapBinding($binding)) {
                continue;
            }

            $bindingName = $binding->getAttribute('name');
            $slug = Str::slug($bindingName ?: 'soap-service');

            $conditionals = $this->buildConditionals($binding);

            $endpoints[] = new EndpointData(
                name: $bindingName ?: 'SOAP Service',
                slug: $slug,
                method: 'POST',
                statusCode: 500,
                contentType: 'text/xml',
                responseBody: $this->defaultFaultEnvelope(),
                isActive: true,
                description: null,
                conditionalResponses: $conditionals,
                type: 'soap',
            );
        }

        return $endpoints;
    }

    private function hasSoapBinding(DOMElement $binding): bool
    {
        foreach ($binding->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'binding') {
                return true;
            }
        }

        return false;
    }

    /** @return list<ConditionalResponseData> */
    private function buildConditionals(DOMElement $binding): array
    {
        $conditionals = [];
        $priority = 0;

        foreach ($binding->childNodes as $child) {
            if (! $child instanceof DOMElement || $child->localName !== 'operation') {
                continue;
            }

            $operationName = $child->getAttribute('name');

            if ($operationName === '') {
                continue;
            }

            $soapAction = $this->extractSoapAction($child);

            if ($soapAction !== null && $soapAction !== '') {
                // Match on SOAPAction header
                $conditionals[] = new ConditionalResponseData(
                    conditionSource: 'soap_action',
                    conditionField: 'soap_action',
                    conditionOperator: 'equals',
                    conditionValue: $soapAction,
                    statusCode: 200,
                    contentType: 'text/xml',
                    responseBody: $this->successEnvelope($operationName),
                    priority: $priority++,
                );
            } else {
                // Fallback: match on operation element name in body
                $conditionals[] = new ConditionalResponseData(
                    conditionSource: 'soap_body',
                    conditionField: $operationName,
                    conditionOperator: 'not_equals',
                    conditionValue: '',
                    statusCode: 200,
                    contentType: 'text/xml',
                    responseBody: $this->successEnvelope($operationName),
                    priority: $priority++,
                );
            }
        }

        return $conditionals;
    }

    private function extractSoapAction(DOMElement $operation): ?string
    {
        foreach ($operation->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'operation') {
                $action = $child->getAttribute('soapAction');

                return $action !== '' ? $action : null;
            }
        }

        return null;
    }

    private function defaultFaultEnvelope(): string
    {
        return <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
              <soap:Body>
                <soap:Fault>
                  <faultcode>soap:Server</faultcode>
                  <faultstring>No matching operation configured</faultstring>
                </soap:Fault>
              </soap:Body>
            </soap:Envelope>
            XML;
    }

    private function successEnvelope(string $operationName): string
    {
        $responseElement = $operationName.'Response';

        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
              <soap:Body>
                <{$responseElement}>
                  <!-- TODO: configure response body for {$operationName} -->
                </{$responseElement}>
              </soap:Body>
            </soap:Envelope>
            XML;
    }
}
