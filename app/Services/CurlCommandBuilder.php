<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ConditionOperator;
use App\Enums\ConditionSource;
use App\Models\ConditionalResponse;
use App\Models\Endpoint;

class CurlCommandBuilder
{
    public function forDefault(Endpoint $endpoint): string
    {
        if ($endpoint->isSoap()) {
            return $this->buildSoapDefault($endpoint);
        }

        return $this->build($endpoint->mock_url, $endpoint->method);
    }

    public function forConditional(Endpoint $endpoint, ConditionalResponse $cr): string
    {
        if ($cr->condition_source === ConditionSource::SoapAction) {
            return $this->buildSoapActionCurl($endpoint, $cr);
        }

        if ($cr->condition_source === ConditionSource::SoapBody) {
            return $this->buildSoapBodyCurl($endpoint, $cr);
        }

        $url = $endpoint->mock_url;
        $headers = [];
        $body = null;
        $queryParams = [];
        $value = $this->exampleValue($cr);

        if ($cr->condition_source === ConditionSource::Query) {
            $queryParams[$cr->condition_field] = $value;
        } elseif ($cr->condition_source === ConditionSource::Header) {
            $headers[$cr->condition_field] = $value;
        } elseif ($cr->condition_source === ConditionSource::Body) {
            $body = json_encode([$cr->condition_field => $value]);
            $headers['Content-Type'] = 'application/json';
        } elseif ($cr->condition_source === ConditionSource::Path) {
            $index = (int) $cr->condition_field;
            $segments = array_fill(0, $index, ':segment');
            $segments[] = $value;
            $url .= '/'.implode('/', $segments);
        }

        return $this->build($url, $endpoint->method, $headers, $body, $queryParams);
    }

    private function exampleValue(ConditionalResponse $cr): string
    {
        if ($cr->condition_operator === ConditionOperator::NotEquals) {
            return 'not_'.$cr->condition_value;
        }

        return $cr->condition_value;
    }

    private function buildSoapDefault(Endpoint $endpoint): string
    {
        $body = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
              <soap:Body>
                <YourOperation/>
              </soap:Body>
            </soap:Envelope>
            XML;

        return $this->build(
            $endpoint->soap_url,
            'POST',
            ['Content-Type' => 'text/xml'],
            $body,
        );
    }

    private function buildSoapActionCurl(Endpoint $endpoint, ConditionalResponse $cr): string
    {
        $action = $this->exampleValue($cr);
        $operationName = basename(str_replace(['urn:', '#', ':'], ['', '/', '/'], $action));
        $body = $this->soapEnvelopeWithOperation($operationName);

        return $this->build(
            $endpoint->soap_url,
            'POST',
            [
                'Content-Type' => 'text/xml',
                'SOAPAction' => "\"{$action}\"",
            ],
            $body,
        );
    }

    private function buildSoapBodyCurl(Endpoint $endpoint, ConditionalResponse $cr): string
    {
        $value = $this->exampleValue($cr);
        $segments = explode('.', $cr->condition_field);
        $operationName = $segments[0];
        $fieldName = count($segments) > 1 ? $segments[count($segments) - 1] : null;

        if ($fieldName !== null) {
            $body = <<<XML
                <?xml version="1.0" encoding="UTF-8"?>
                <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
                  <soap:Body>
                    <{$operationName}>
                      <{$fieldName}>{$value}</{$fieldName}>
                    </{$operationName}>
                  </soap:Body>
                </soap:Envelope>
                XML;
        } else {
            $body = $this->soapEnvelopeWithOperation($operationName);
        }

        return $this->build(
            $endpoint->soap_url,
            'POST',
            ['Content-Type' => 'text/xml'],
            $body,
        );
    }

    private function soapEnvelopeWithOperation(string $operationName): string
    {
        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
              <soap:Body>
                <{$operationName}/>
              </soap:Body>
            </soap:Envelope>
            XML;
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, string>  $queryParams
     */
    private function build(string $url, string $method, array $headers = [], ?string $body = null, array $queryParams = []): string
    {
        $parts = ['curl'];

        if ($method !== 'GET') {
            $parts[] = "-X {$method}";
        }

        foreach ($headers as $name => $value) {
            $parts[] = '-H '.escapeshellarg("{$name}: {$value}");
        }

        if ($body !== null) {
            $parts[] = '-d '.escapeshellarg($body);
        }

        if ($queryParams !== []) {
            $url .= '?'.http_build_query($queryParams);
        }

        $parts[] = escapeshellarg($url);

        return implode(" \\\n  ", $parts);
    }
}
