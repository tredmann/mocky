<?php

use App\Actions\UpdateEndpoint;
use App\Models\Endpoint;
use App\Models\EndpointCollection;
use App\Models\User;

function updateEndpointFixture(): array
{
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $endpoint = Endpoint::factory()->create([
        'user_id' => $user->id,
        'collection_id' => $collection->id,
    ]);

    return [$user, $collection, $endpoint];
}

function updateEndpointAction(): UpdateEndpoint
{
    return app(UpdateEndpoint::class);
}

test('updates all provided fields', function () {
    [, , $endpoint] = updateEndpointFixture();

    updateEndpointAction()->handle(
        $endpoint,
        name: 'New Name',
        slug: 'new-slug',
        method: 'POST',
        statusCode: 201,
        contentType: 'text/plain',
        description: 'A description',
        responseBody: '{"ok":true}',
    );

    $endpoint->refresh();

    expect($endpoint->name)->toBe('New Name')
        ->and($endpoint->slug)->toBe('new-slug')
        ->and($endpoint->method)->toBe('POST')
        ->and($endpoint->status_code)->toBe(201)
        ->and($endpoint->content_type)->toBe('text/plain')
        ->and($endpoint->description)->toBe('A description')
        ->and($endpoint->response_body)->not->toBeNull();
});

test('persists changes to the database', function () {
    [, , $endpoint] = updateEndpointFixture();

    updateEndpointAction()->handle(
        $endpoint,
        name: 'Persisted Name',
        slug: 'persisted-slug',
        method: 'GET',
        statusCode: 200,
        contentType: 'application/json',
    );

    expect(Endpoint::find($endpoint->id)->name)->toBe('Persisted Name');
});

test('sets description to null when omitted', function () {
    [, , $endpoint] = updateEndpointFixture();

    updateEndpointAction()->handle(
        $endpoint,
        name: 'Name',
        slug: 'slug',
        method: 'GET',
        statusCode: 200,
        contentType: 'application/json',
    );

    expect($endpoint->fresh()->description)->toBeNull();
});

test('sets response_body to null when omitted', function () {
    [, , $endpoint] = updateEndpointFixture();

    updateEndpointAction()->handle(
        $endpoint,
        name: 'Name',
        slug: 'slug',
        method: 'GET',
        statusCode: 200,
        contentType: 'application/json',
    );

    expect($endpoint->fresh()->response_body)->toBeNull();
});

test('can update description to null explicitly', function () {
    [, , $endpoint] = updateEndpointFixture();
    $endpoint->update(['description' => 'Old description']);

    updateEndpointAction()->handle(
        $endpoint,
        name: 'Name',
        slug: 'slug',
        method: 'GET',
        statusCode: 200,
        contentType: 'application/json',
        description: null,
    );

    expect($endpoint->fresh()->description)->toBeNull();
});

test('does not change user_id or collection_id', function () {
    [$user, $collection, $endpoint] = updateEndpointFixture();

    updateEndpointAction()->handle(
        $endpoint,
        name: 'Updated',
        slug: 'updated',
        method: 'GET',
        statusCode: 200,
        contentType: 'application/json',
    );

    $fresh = $endpoint->fresh();
    expect($fresh->user_id)->toBe($user->id)
        ->and($fresh->collection_id)->toBe($collection->id);
});
