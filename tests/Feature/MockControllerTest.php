<?php

use App\Models\ConditionalResponse;
use App\Models\Endpoint;
use App\Models\EndpointLog;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

// --- Inactive / not found ---

test('inactive endpoint returns 404', function () {
    $endpoint = Endpoint::factory()->create(['method' => 'GET', 'is_active' => false]);

    $this->getJson(route('mock', $endpoint))
        ->assertStatus(404);
});

test('unknown slug returns 404', function () {
    $this->getJson('/mock/nonexistent-slug')
        ->assertStatus(404);
});

// --- Method enforcement ---

test('returns 405 with allow header when method does not match', function () {
    $endpoint = Endpoint::factory()->create(['method' => 'POST']);

    $this->getJson(route('mock', $endpoint))
        ->assertStatus(405)
        ->assertHeader('Allow', 'POST');
});

test('all http methods are accepted when configured', function () {
    foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
        $endpoint = Endpoint::factory()->create(['method' => $method, 'status_code' => 200]);

        $response = $this->json($method, route('mock', $endpoint));

        $response->assertStatus(200);
    }
});

// --- Content-Type header ---

test('response has the configured content type', function () {
    $endpoint = Endpoint::factory()->create([
        'method' => 'GET',
        'content_type' => 'application/json',
        'status_code' => 200,
    ]);

    $this->getJson(route('mock', $endpoint))
        ->assertHeader('Content-Type', 'application/json');
});

test('response uses conditional content type when condition matches', function () {
    $endpoint = Endpoint::factory()->create(['method' => 'GET', 'content_type' => 'text/plain']);

    ConditionalResponse::factory()->create([
        'endpoint_id' => $endpoint->id,
        'condition_source' => 'query',
        'condition_field' => 'format',
        'condition_operator' => 'equals',
        'condition_value' => 'json',
        'content_type' => 'application/json',
        'status_code' => 200,
    ]);

    $this->getJson(route('mock', $endpoint).'?format=json')
        ->assertHeader('Content-Type', 'application/json');
});

// --- Response body ---

test('returns empty body when response body is null', function () {
    $endpoint = Endpoint::factory()->create(['method' => 'GET', 'response_body' => null, 'status_code' => 204]);

    $this->getJson(route('mock', $endpoint))
        ->assertStatus(204)
        ->assertNoContent();
});

// --- CSRF exempt ---

test('post request does not require csrf token', function () {
    $endpoint = Endpoint::factory()->create(['method' => 'POST', 'status_code' => 200]);

    $this->post(route('mock', $endpoint))
        ->assertStatus(200);
});

// --- Logging ---

test('every request is logged', function () {
    $endpoint = Endpoint::factory()->create(['method' => 'GET']);

    $this->getJson(route('mock', $endpoint));

    expect(EndpointLog::where('endpoint_id', $endpoint->id)->count())->toBe(1);
});

test('log stores request method ip and user agent', function () {
    $endpoint = Endpoint::factory()->create(['method' => 'GET']);

    $this->getJson(route('mock', $endpoint), ['User-Agent' => 'TestAgent/1.0']);

    $log = EndpointLog::where('endpoint_id', $endpoint->id)->first();

    expect($log->request_method)->toBe('GET')
        ->and($log->request_ip)->not->toBeNull()
        ->and($log->request_user_agent)->toContain('TestAgent/1.0');
});

test('log stores request body', function () {
    $endpoint = Endpoint::factory()->create(['method' => 'POST']);

    $this->postJson(route('mock', $endpoint), ['foo' => 'bar']);

    $log = EndpointLog::where('endpoint_id', $endpoint->id)->first();

    expect($log->request_body)->toContain('foo');
});

test('log stores query parameters', function () {
    $endpoint = Endpoint::factory()->create(['method' => 'GET']);

    $this->getJson(route('mock', $endpoint).'?page=2');

    $log = EndpointLog::where('endpoint_id', $endpoint->id)->first();

    expect($log->request_query)->toHaveKey('page', '2');
});

test('log stores the response status code and body', function () {
    $endpoint = Endpoint::factory()->create([
        'method' => 'GET',
        'status_code' => 418,
        'response_body' => '{"teapot":true}',
    ]);

    $this->getJson(route('mock', $endpoint));

    $log = EndpointLog::where('endpoint_id', $endpoint->id)->first();

    expect($log->response_status_code)->toBe(418)
        ->and($log->response_body)->toBe('{"teapot":true}');
});

test('log stores matched conditional response id when condition matches', function () {
    $endpoint = Endpoint::factory()->create(['method' => 'GET']);

    $cr = ConditionalResponse::factory()->create([
        'endpoint_id' => $endpoint->id,
        'condition_source' => 'query',
        'condition_field' => 'match',
        'condition_operator' => 'equals',
        'condition_value' => 'yes',
        'status_code' => 200,
    ]);

    $this->getJson(route('mock', $endpoint).'?match=yes');

    $log = EndpointLog::where('endpoint_id', $endpoint->id)->first();

    expect($log->matched_conditional_response_id)->toBe($cr->id);
});

test('log has null matched conditional response id when no condition matches', function () {
    $endpoint = Endpoint::factory()->create(['method' => 'GET']);

    $this->getJson(route('mock', $endpoint));

    $log = EndpointLog::where('endpoint_id', $endpoint->id)->first();

    expect($log->matched_conditional_response_id)->toBeNull();
});

test('multiple requests create multiple log entries', function () {
    $endpoint = Endpoint::factory()->create(['method' => 'GET']);

    $this->getJson(route('mock', $endpoint));
    $this->getJson(route('mock', $endpoint));
    $this->getJson(route('mock', $endpoint));

    expect(EndpointLog::where('endpoint_id', $endpoint->id)->count())->toBe(3);
});
