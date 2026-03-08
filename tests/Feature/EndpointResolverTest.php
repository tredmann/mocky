<?php

use App\Exceptions\EndpointNotFoundException;
use App\Exceptions\MethodNotAllowedException;
use App\Models\Endpoint;
use App\Services\EndpointResolver;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function resolver(): EndpointResolver
{
    return new EndpointResolver;
}

function resolverEndpoint(array $attributes = []): Endpoint
{
    $endpoint = Endpoint::factory()->create($attributes);
    $endpoint->load('collection');

    return $endpoint;
}

test('resolves an active endpoint', function () {
    $endpoint = resolverEndpoint(['method' => 'GET', 'is_active' => true]);

    $resolved = resolver()->resolve(
        $endpoint->collection->slug,
        $endpoint->slug,
        'GET',
    );

    expect($resolved->id)->toBe($endpoint->id);
});

test('throws EndpointNotFoundException when collection slug does not exist', function () {
    resolver()->resolve('no-such-collection', 'any-endpoint', 'GET');
})->throws(EndpointNotFoundException::class);

test('throws EndpointNotFoundException when endpoint slug does not exist', function () {
    $endpoint = resolverEndpoint(['method' => 'GET']);

    resolver()->resolve($endpoint->collection->slug, 'no-such-endpoint', 'GET');
})->throws(EndpointNotFoundException::class);

test('throws MethodNotAllowedException when method does not match', function () {
    $endpoint = resolverEndpoint(['method' => 'GET']);

    resolver()->resolve($endpoint->collection->slug, $endpoint->slug, 'POST');
})->throws(MethodNotAllowedException::class);

test('MethodNotAllowedException carries the allowed methods', function () {
    $endpoint = resolverEndpoint(['method' => 'GET']);

    try {
        resolver()->resolve($endpoint->collection->slug, $endpoint->slug, 'POST');
    } catch (MethodNotAllowedException $e) {
        expect($e->getAllowedMethods())->toContain('GET');
    }
});

test('throws EndpointNotFoundException when endpoint is inactive', function () {
    $endpoint = resolverEndpoint(['method' => 'GET', 'is_active' => false]);

    resolver()->resolve($endpoint->collection->slug, $endpoint->slug, 'GET');
})->throws(EndpointNotFoundException::class);
