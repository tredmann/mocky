<?php

use App\Models\ConditionalResponse;
use App\Models\Endpoint;
use App\Models\EndpointLog;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function mockEndpoint(array $attributes = []): Endpoint
{
    $endpoint = Endpoint::factory()->create($attributes);
    $endpoint->load('collection');

    return $endpoint;
}

function endpointMockUrl(Endpoint $endpoint): string
{
    return '/mock/'.$endpoint->collection->slug.'/'.$endpoint->slug;
}

// --- Inactive / not found ---

test('inactive endpoint returns 404', function () {
    $endpoint = mockEndpoint(['method' => 'GET', 'is_active' => false]);

    $this->getJson(endpointMockUrl($endpoint))
        ->assertStatus(404);
});

test('unknown slug returns 404', function () {
    $this->getJson('/mock/nonexistent-collection/nonexistent-slug')
        ->assertStatus(404);
});

// --- Method enforcement ---

test('returns 405 with allow header when method does not match', function () {
    $endpoint = mockEndpoint(['method' => 'POST']);

    $this->getJson(endpointMockUrl($endpoint))
        ->assertStatus(405)
        ->assertHeader('Allow', 'POST');
});

test('all http methods are accepted when configured', function () {
    foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
        $endpoint = mockEndpoint(['method' => $method, 'status_code' => 200]);

        $response = $this->json($method, endpointMockUrl($endpoint));

        $response->assertStatus(200);
    }
});

// --- Content-Type header ---

test('response has the configured content type', function () {
    $endpoint = mockEndpoint([
        'method' => 'GET',
        'content_type' => 'application/json',
        'status_code' => 200,
    ]);

    $this->getJson(endpointMockUrl($endpoint))
        ->assertHeader('Content-Type', 'application/json');
});

test('response uses conditional content type when condition matches', function () {
    $endpoint = mockEndpoint(['method' => 'GET', 'content_type' => 'text/plain']);

    ConditionalResponse::factory()->create([
        'endpoint_id' => $endpoint->id,
        'condition_source' => 'query',
        'condition_field' => 'format',
        'condition_operator' => 'equals',
        'condition_value' => 'json',
        'content_type' => 'application/json',
        'status_code' => 200,
    ]);

    $this->getJson(endpointMockUrl($endpoint).'?format=json')
        ->assertHeader('Content-Type', 'application/json');
});

// --- Response body ---

test('returns empty body when response body is null', function () {
    $endpoint = mockEndpoint(['method' => 'GET', 'response_body' => null, 'status_code' => 204]);

    $this->getJson(endpointMockUrl($endpoint))
        ->assertStatus(204)
        ->assertNoContent();
});

// --- CSRF exempt ---

test('post request does not require csrf token', function () {
    $endpoint = mockEndpoint(['method' => 'POST', 'status_code' => 200]);

    $this->post(endpointMockUrl($endpoint))
        ->assertStatus(200);
});

// --- Logging ---

test('every request is logged', function () {
    $endpoint = mockEndpoint(['method' => 'GET']);

    $this->getJson(endpointMockUrl($endpoint));

    expect(EndpointLog::where('endpoint_id', $endpoint->id)->count())->toBe(1);
});

test('log stores request method ip and user agent', function () {
    $endpoint = mockEndpoint(['method' => 'GET']);

    $this->getJson(endpointMockUrl($endpoint), ['User-Agent' => 'TestAgent/1.0']);

    $log = EndpointLog::where('endpoint_id', $endpoint->id)->first();

    expect($log->request_method)->toBe('GET')
        ->and($log->request_ip)->not->toBeNull()
        ->and($log->request_user_agent)->toContain('TestAgent/1.0');
});

test('log stores request body', function () {
    $endpoint = mockEndpoint(['method' => 'POST']);

    $this->postJson(endpointMockUrl($endpoint), ['foo' => 'bar']);

    $log = EndpointLog::where('endpoint_id', $endpoint->id)->first();

    expect($log->request_body)->toContain('foo');
});

test('log stores query parameters', function () {
    $endpoint = mockEndpoint(['method' => 'GET']);

    $this->getJson(endpointMockUrl($endpoint).'?page=2');

    $log = EndpointLog::where('endpoint_id', $endpoint->id)->first();

    expect($log->request_query)->toHaveKey('page', '2');
});

test('log stores the response status code and body', function () {
    $endpoint = mockEndpoint([
        'method' => 'GET',
        'status_code' => 418,
        'response_body' => '{"teapot":true}',
    ]);

    $this->getJson(endpointMockUrl($endpoint));

    $log = EndpointLog::where('endpoint_id', $endpoint->id)->first();

    expect($log->response_status_code)->toBe(418)
        ->and($log->response_body)->toBe("{\n    \"teapot\": true\n}");
});

test('log stores matched conditional response id when condition matches', function () {
    $endpoint = mockEndpoint(['method' => 'GET']);

    $cr = ConditionalResponse::factory()->create([
        'endpoint_id' => $endpoint->id,
        'condition_source' => 'query',
        'condition_field' => 'match',
        'condition_operator' => 'equals',
        'condition_value' => 'yes',
        'status_code' => 200,
    ]);

    $this->getJson(endpointMockUrl($endpoint).'?match=yes');

    $log = EndpointLog::where('endpoint_id', $endpoint->id)->first();

    expect($log->matched_conditional_response_id)->toBe($cr->id);
});

test('log has null matched conditional response id when no condition matches', function () {
    $endpoint = mockEndpoint(['method' => 'GET']);

    $this->getJson(endpointMockUrl($endpoint));

    $log = EndpointLog::where('endpoint_id', $endpoint->id)->first();

    expect($log->matched_conditional_response_id)->toBeNull();
});

test('multiple requests create multiple log entries', function () {
    $endpoint = mockEndpoint(['method' => 'GET']);

    $this->getJson(endpointMockUrl($endpoint));
    $this->getJson(endpointMockUrl($endpoint));
    $this->getJson(endpointMockUrl($endpoint));

    expect(EndpointLog::where('endpoint_id', $endpoint->id)->count())->toBe(3);
});
