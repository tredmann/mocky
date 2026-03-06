<?php

use App\Models\ConditionalResponse;
use App\Models\Endpoint;
use App\Models\EndpointLog;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('endpoint relationship returns the parent endpoint', function () {
    $endpoint = Endpoint::factory()->create();

    $log = EndpointLog::create([
        'endpoint_id' => $endpoint->id,
        'request_method' => 'GET',
        'request_ip' => '127.0.0.1',
        'request_headers' => [],
        'request_query' => [],
        'response_status_code' => 200,
        'created_at' => now(),
    ]);

    expect($log->endpoint->id)->toBe($endpoint->id);
});

test('matched conditional response relationship returns the conditional response', function () {
    $endpoint = Endpoint::factory()->create();
    $cr = ConditionalResponse::factory()->create(['endpoint_id' => $endpoint->id]);

    $log = EndpointLog::create([
        'endpoint_id' => $endpoint->id,
        'matched_conditional_response_id' => $cr->id,
        'request_method' => 'GET',
        'request_ip' => '127.0.0.1',
        'request_headers' => [],
        'request_query' => [],
        'response_status_code' => 200,
        'created_at' => now(),
    ]);

    expect($log->matchedConditionalResponse->id)->toBe($cr->id);
});

test('matched conditional response relationship is null when not set', function () {
    $endpoint = Endpoint::factory()->create();

    $log = EndpointLog::create([
        'endpoint_id' => $endpoint->id,
        'matched_conditional_response_id' => null,
        'request_method' => 'GET',
        'request_ip' => '127.0.0.1',
        'request_headers' => [],
        'request_query' => [],
        'response_status_code' => 200,
        'created_at' => now(),
    ]);

    expect($log->matchedConditionalResponse)->toBeNull();
});

test('request headers and query are cast to arrays', function () {
    $endpoint = Endpoint::factory()->create();

    $log = EndpointLog::create([
        'endpoint_id' => $endpoint->id,
        'request_method' => 'GET',
        'request_ip' => '127.0.0.1',
        'request_headers' => ['X-Foo' => ['bar']],
        'request_query' => ['page' => '2'],
        'response_status_code' => 200,
        'created_at' => now(),
    ]);

    $fresh = $log->fresh();

    expect($fresh->request_headers)->toBeArray()->toHaveKey('X-Foo')
        ->and($fresh->request_query)->toBeArray()->toHaveKey('page', '2');
});

test('created at is cast to a datetime', function () {
    $endpoint = Endpoint::factory()->create();

    $log = EndpointLog::create([
        'endpoint_id' => $endpoint->id,
        'request_method' => 'GET',
        'request_ip' => '127.0.0.1',
        'request_headers' => [],
        'request_query' => [],
        'response_status_code' => 200,
        'created_at' => now(),
    ]);

    expect($log->created_at)->toBeInstanceOf(Carbon\CarbonInterface::class);
});
