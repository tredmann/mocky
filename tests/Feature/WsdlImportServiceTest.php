<?php

use App\Models\User;
use App\Services\WsdlImportService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function minimalWsdl(string $serviceName = 'TestService', array $operations = []): string
{
    $operationXml = '';
    foreach ($operations as $op) {
        $soapAction = isset($op['soapAction']) ? "soapAction=\"{$op['soapAction']}\"" : '';
        $operationXml .= <<<XML
            <wsdl:operation name="{$op['name']}">
              <soap:operation {$soapAction}/>
            </wsdl:operation>
        XML;
    }

    return <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <wsdl:definitions
            name="{$serviceName}"
            xmlns:wsdl="http://schemas.xmlsoap.org/wsdl/"
            xmlns:soap="http://schemas.xmlsoap.org/wsdl/soap/">
          <wsdl:binding name="{$serviceName}Binding" type="tns:{$serviceName}PortType">
            <soap:binding style="document" transport="http://schemas.xmlsoap.org/soap/http"/>
            {$operationXml}
          </wsdl:binding>
          <wsdl:service name="{$serviceName}">
            <wsdl:port name="{$serviceName}Port" binding="tns:{$serviceName}Binding">
              <soap:address location="http://example.com/service"/>
            </wsdl:port>
          </wsdl:service>
        </wsdl:definitions>
        XML;
}

test('imports WSDL and creates collection with SOAP endpoint', function () {
    $user = User::factory()->create();
    $wsdl = minimalWsdl('UserService', [
        ['name' => 'GetUser', 'soapAction' => 'urn:UserService#GetUser'],
    ]);

    $collection = app(WsdlImportService::class)->import($user, $wsdl);

    expect($collection->name)->toBe('UserService');
    expect($collection->endpoints()->count())->toBe(1);

    $endpoint = $collection->endpoints()->first();
    expect($endpoint->type->value)->toBe('soap');
    expect($endpoint->method)->toBe('POST');
    expect($endpoint->content_type)->toBe('text/xml');
});

test('creates one conditional response per operation', function () {
    $user = User::factory()->create();
    $wsdl = minimalWsdl('OrderService', [
        ['name' => 'CreateOrder', 'soapAction' => 'urn:CreateOrder'],
        ['name' => 'GetOrder', 'soapAction' => 'urn:GetOrder'],
        ['name' => 'CancelOrder', 'soapAction' => 'urn:CancelOrder'],
    ]);

    $collection = app(WsdlImportService::class)->import($user, $wsdl);
    $endpoint = $collection->endpoints()->first();

    expect($endpoint->conditionalResponses()->count())->toBe(3);
});

test('conditional responses use soap_action condition source', function () {
    $user = User::factory()->create();
    $wsdl = minimalWsdl('Svc', [
        ['name' => 'DoThing', 'soapAction' => 'urn:DoThing'],
    ]);

    $collection = app(WsdlImportService::class)->import($user, $wsdl);
    $cr = $collection->endpoints()->first()->conditionalResponses()->first();

    expect($cr->condition_source->value)->toBe('soap_action');
    expect($cr->condition_value)->toBe('urn:DoThing');
});

test('operations without soapAction fall back to soap_body condition', function () {
    $user = User::factory()->create();
    $wsdl = minimalWsdl('Svc', [
        ['name' => 'DoThing'],
    ]);

    $collection = app(WsdlImportService::class)->import($user, $wsdl);
    $cr = $collection->endpoints()->first()->conditionalResponses()->first();

    expect($cr->condition_source->value)->toBe('soap_body');
    expect($cr->condition_field)->toBe('DoThing');
});

test('throws InvalidArgumentException on invalid XML', function () {
    $user = User::factory()->create();

    expect(fn () => app(WsdlImportService::class)->import($user, '<not valid'))
        ->toThrow(InvalidArgumentException::class);
});

test('throws InvalidArgumentException when no bindings found', function () {
    $user = User::factory()->create();
    $wsdl = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <wsdl:definitions xmlns:wsdl="http://schemas.xmlsoap.org/wsdl/">
          <wsdl:service name="Empty"/>
        </wsdl:definitions>
        XML;

    expect(fn () => app(WsdlImportService::class)->import($user, $wsdl))
        ->toThrow(InvalidArgumentException::class);
});
