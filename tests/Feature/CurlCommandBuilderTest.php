<?php

use App\Models\ConditionalResponse;
use App\Models\Endpoint;
use App\Services\CurlCommandBuilder;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function builder(): CurlCommandBuilder
{
    return app(CurlCommandBuilder::class);
}

// --- Default ---

test('get request has no -X flag', function () {
    $endpoint = Endpoint::factory()->create(['method' => 'GET']);

    $curl = builder()->forDefault($endpoint);

    expect($curl)->not->toContain('-X GET')
        ->and($curl)->toContain("curl \\\n");
});

test('non-get request includes -X flag', function () {
    foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
        $endpoint = Endpoint::factory()->create(['method' => $method]);

        expect(builder()->forDefault($endpoint))->toContain("-X {$method}");
    }
});

test('default curl contains mock url', function () {
    $endpoint = Endpoint::factory()->create(['slug' => 'my-slug']);

    expect(builder()->forDefault($endpoint))->toContain($endpoint->mock_url);
});

// --- Query condition ---

test('query condition appends query string to url', function () {
    $endpoint = Endpoint::factory()->create(['method' => 'GET', 'slug' => 'my-slug']);
    $cr = ConditionalResponse::factory()->create([
        'endpoint_id' => $endpoint->id,
        'condition_source' => 'query',
        'condition_field' => 'status',
        'condition_value' => 'active',
    ]);

    $curl = builder()->forConditional($endpoint, $cr);

    expect($curl)->toContain('status=active')
        ->and($curl)->toContain($endpoint->mock_url);
});

// --- Header condition ---

test('header condition adds -H flag', function () {
    $endpoint = Endpoint::factory()->create(['method' => 'GET']);
    $cr = ConditionalResponse::factory()->create([
        'endpoint_id' => $endpoint->id,
        'condition_source' => 'header',
        'condition_field' => 'X-Api-Version',
        'condition_value' => '2',
    ]);

    $curl = builder()->forConditional($endpoint, $cr);

    expect($curl)->toContain('-H "X-Api-Version: 2"');
});

// --- Body condition ---

test('body condition adds content-type header and -d flag', function () {
    $endpoint = Endpoint::factory()->create(['method' => 'POST']);
    $cr = ConditionalResponse::factory()->create([
        'endpoint_id' => $endpoint->id,
        'condition_source' => 'body',
        'condition_field' => 'id',
        'condition_value' => '42',
    ]);

    $curl = builder()->forConditional($endpoint, $cr);

    expect($curl)->toContain('-H "Content-Type: application/json"')
        ->and($curl)->toContain('-d \'{"id":"42"}\'');
});

// --- Operators ---

test('equals operator uses condition value', function () {
    $endpoint = Endpoint::factory()->create(['method' => 'GET']);
    $cr = ConditionalResponse::factory()->create([
        'endpoint_id' => $endpoint->id,
        'condition_source' => 'query',
        'condition_field' => 'status',
        'condition_operator' => 'equals',
        'condition_value' => 'active',
    ]);

    expect(builder()->forConditional($endpoint, $cr))->toContain('status=active');
});

test('contains operator uses condition value', function () {
    $endpoint = Endpoint::factory()->create(['method' => 'GET']);
    $cr = ConditionalResponse::factory()->create([
        'endpoint_id' => $endpoint->id,
        'condition_source' => 'query',
        'condition_field' => 'name',
        'condition_operator' => 'contains',
        'condition_value' => 'john',
    ]);

    expect(builder()->forConditional($endpoint, $cr))->toContain('name=john');
});

test('not_equals operator uses a different value to trigger the condition', function () {
    $endpoint = Endpoint::factory()->create(['method' => 'GET']);
    $cr = ConditionalResponse::factory()->create([
        'endpoint_id' => $endpoint->id,
        'condition_source' => 'query',
        'condition_field' => 'status',
        'condition_operator' => 'not_equals',
        'condition_value' => 'active',
    ]);

    $curl = builder()->forConditional($endpoint, $cr);

    expect($curl)->toContain('status=other')
        ->and($curl)->not->toContain('status=active');
});

// --- Path condition ---

test('path condition at index 0 appends value to url', function () {
    $endpoint = Endpoint::factory()->create(['method' => 'GET', 'slug' => 'my-slug']);
    $cr = ConditionalResponse::factory()->create([
        'endpoint_id' => $endpoint->id,
        'condition_source' => 'path',
        'condition_field' => '0',
        'condition_value' => 'users',
    ]);

    $curl = builder()->forConditional($endpoint, $cr);

    expect($curl)->toContain($endpoint->mock_url.'/users');
});

test('path condition at index 1 adds segment placeholder before value', function () {
    $endpoint = Endpoint::factory()->create(['method' => 'GET', 'slug' => 'my-slug']);
    $cr = ConditionalResponse::factory()->create([
        'endpoint_id' => $endpoint->id,
        'condition_source' => 'path',
        'condition_field' => '1',
        'condition_value' => '123',
    ]);

    $curl = builder()->forConditional($endpoint, $cr);

    expect($curl)->toContain($endpoint->mock_url.'/:segment/123');
});
