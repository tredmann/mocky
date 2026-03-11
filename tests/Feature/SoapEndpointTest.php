<?php

use App\Models\ConditionalResponse;
use App\Models\Endpoint;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function soapEndpoint(array $attributes = []): Endpoint
{
    $endpoint = Endpoint::factory()->create(array_merge([
        'method' => 'POST',
        'type' => 'soap',
        'content_type' => 'text/xml',
        'status_code' => 200,
        'response_body' => '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><DefaultResponse/></soap:Body></soap:Envelope>',
    ], $attributes));
    $endpoint->load('collection');

    return $endpoint;
}

function soapUrl(Endpoint $endpoint): string
{
    return '/soap/'.$endpoint->collection->slug.'/'.$endpoint->slug;
}

function soapBody(string $operation = 'Ping'): string
{
    return <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            <{$operation}/>
          </soap:Body>
        </soap:Envelope>
        XML;
}

/** Send a raw XML POST request to a SOAP endpoint. */
function soapPost(Illuminate\Testing\TestCase|Tests\TestCase $test, string $url, string $body, array $extraHeaders = []): Illuminate\Testing\TestResponse
{
    $server = array_merge(['CONTENT_TYPE' => 'text/xml'], $extraHeaders);

    return $test->call('POST', $url, [], [], [], $server, $body);
}

// Basic routing
test('returns default SOAP response on POST with XML content-type', function () {
    $endpoint = soapEndpoint(['response_body' => '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><Response>ok</Response></soap:Body></soap:Envelope>']);

    soapPost($this, soapUrl($endpoint), soapBody())
        ->assertStatus(200)
        ->assertSee('<Response>ok</Response>', false);
});

test('returns 415 SOAP Fault for non-XML content-type', function () {
    $endpoint = soapEndpoint();

    $this->call('POST', soapUrl($endpoint), [], [], [], ['CONTENT_TYPE' => 'application/json'], '{"not":"xml"}')
        ->assertStatus(415)
        ->assertSee('Unsupported Media Type', false);
});

test('returns 404 SOAP Fault when endpoint not found', function () {
    soapPost($this, '/soap/unknown-col/unknown-ep', soapBody())
        ->assertStatus(404)
        ->assertSee('faultstring', false);
});

test('returns 404 SOAP Fault for inactive endpoint', function () {
    $endpoint = soapEndpoint(['is_active' => false]);

    soapPost($this, soapUrl($endpoint), soapBody())
        ->assertStatus(404);
});

// Conditional matching
test('matches conditional response on SOAPAction header', function () {
    $endpoint = soapEndpoint();

    ConditionalResponse::factory()->create([
        'endpoint_id' => $endpoint->id,
        'condition_source' => 'soap_action',
        'condition_field' => 'soap_action',
        'condition_operator' => 'equals',
        'condition_value' => 'urn:GetUser',
        'status_code' => 200,
        'content_type' => 'text/xml',
        'response_body' => '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><GetUserResponse/></soap:Body></soap:Envelope>',
        'priority' => 0,
    ]);

    soapPost($this, soapUrl($endpoint), soapBody('GetUser'), ['HTTP_SOAPACTION' => '"urn:GetUser"'])
        ->assertStatus(200)
        ->assertSee('<GetUserResponse/>', false);
});

test('falls back to default when SOAPAction does not match', function () {
    $endpoint = soapEndpoint();

    ConditionalResponse::factory()->create([
        'endpoint_id' => $endpoint->id,
        'condition_source' => 'soap_action',
        'condition_field' => 'soap_action',
        'condition_operator' => 'equals',
        'condition_value' => 'urn:GetUser',
        'status_code' => 200,
        'content_type' => 'text/xml',
        'response_body' => '<matched/>',
        'priority' => 0,
    ]);

    soapPost($this, soapUrl($endpoint), soapBody('GetOrder'), ['HTTP_SOAPACTION' => '"urn:GetOrder"'])
        ->assertStatus(200)
        ->assertSee('<DefaultResponse/>', false);
});

test('matches conditional response on soap_body dot notation', function () {
    $endpoint = soapEndpoint();

    $xml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            <GetUser>
              <userId>99</userId>
            </GetUser>
          </soap:Body>
        </soap:Envelope>
        XML;

    ConditionalResponse::factory()->create([
        'endpoint_id' => $endpoint->id,
        'condition_source' => 'soap_body',
        'condition_field' => 'GetUser.userId',
        'condition_operator' => 'equals',
        'condition_value' => '99',
        'status_code' => 200,
        'content_type' => 'text/xml',
        'response_body' => '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><MatchedUser/></soap:Body></soap:Envelope>',
        'priority' => 0,
    ]);

    soapPost($this, soapUrl($endpoint), $xml)
        ->assertStatus(200)
        ->assertSee('<MatchedUser/>', false);
});

// Type isolation
test('SOAP endpoint is not accessible via /mock/ route', function () {
    $endpoint = soapEndpoint();

    $this->call('POST', '/mock/'.$endpoint->collection->slug.'/'.$endpoint->slug, [], [], [], ['CONTENT_TYPE' => 'text/xml'], soapBody())
        ->assertStatus(404);
});

test('REST endpoint with same slug is not accessible via /soap/ route', function () {
    $restEndpoint = Endpoint::factory()->create(['method' => 'POST', 'type' => 'rest']);
    $restEndpoint->load('collection');

    soapPost($this, '/soap/'.$restEndpoint->collection->slug.'/'.$restEndpoint->slug, soapBody())
        ->assertStatus(404);
});

// Logging
test('SOAP requests are logged', function () {
    $endpoint = soapEndpoint();

    soapPost($this, soapUrl($endpoint), soapBody())
        ->assertStatus(200);

    expect($endpoint->logs()->count())->toBe(1);
});
