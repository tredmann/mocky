<?php

use App\Models\ConditionalResponse;
use App\Models\Endpoint;
use App\Models\EndpointLog;
use Illuminate\Support\Str;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('endpoint id is a uuid', function () {
    $endpoint = Endpoint::factory()->create();

    expect(Str::isUuid($endpoint->id))->toBeTrue();
});

test('conditional response id is a uuid', function () {
    $endpoint = Endpoint::factory()->create();
    $cr = ConditionalResponse::factory()->create(['endpoint_id' => $endpoint->id]);

    expect(Str::isUuid($cr->id))->toBeTrue();
});

test('endpoint log id is an integer', function () {
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

    expect($log->id)->toBeInt();
});
