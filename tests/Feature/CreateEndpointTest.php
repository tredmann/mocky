<?php

use App\Actions\CreateEndpoint;
use App\Models\Endpoint;
use App\Models\EndpointCollection;
use App\Models\User;

function createEndpointAction(): CreateEndpoint
{
    return app(CreateEndpoint::class);
}

function createEndpointUser(): User
{
    return User::factory()->create();
}

function createEndpointCollection(User $user): EndpointCollection
{
    return EndpointCollection::factory()->create(['user_id' => $user->id]);
}

test('creates an endpoint in the given collection for the given user', function () {
    $user = createEndpointUser();
    $collection = createEndpointCollection($user);

    $endpoint = createEndpointAction()->handle($user, $collection, 'Get User', 'get-user', 'GET', 200, 'application/json');

    expect($endpoint->user_id)->toBe($user->id)
        ->and($endpoint->collection_id)->toBe($collection->id)
        ->and($endpoint->name)->toBe('Get User')
        ->and($endpoint->slug)->toBe('get-user')
        ->and($endpoint->method)->toBe('GET')
        ->and($endpoint->status_code)->toBe(200)
        ->and($endpoint->content_type)->toBe('application/json');
});

test('returns a persisted Endpoint', function () {
    $user = createEndpointUser();
    $collection = createEndpointCollection($user);

    $endpoint = createEndpointAction()->handle($user, $collection, 'Get User', 'get-user', 'GET', 200, 'application/json');

    expect($endpoint)->toBeInstanceOf(Endpoint::class)
        ->and($endpoint->exists)->toBeTrue();
});

test('uses the provided slug when it does not conflict', function () {
    $user = createEndpointUser();
    $collection = createEndpointCollection($user);

    $endpoint = createEndpointAction()->handle($user, $collection, 'Get User', 'my-slug', 'GET', 200, 'application/json');

    expect($endpoint->slug)->toBe('my-slug');
});

test('appends -N suffix when slug conflicts on the same method', function () {
    $user = createEndpointUser();
    $collection = createEndpointCollection($user);

    createEndpointAction()->handle($user, $collection, 'First', 'get-user', 'GET', 200, 'application/json');
    $second = createEndpointAction()->handle($user, $collection, 'Second', 'get-user', 'GET', 200, 'application/json');

    expect($second->slug)->toBe('get-user-1');
});

test('increments the suffix when multiple conflicts exist', function () {
    $user = createEndpointUser();
    $collection = createEndpointCollection($user);

    createEndpointAction()->handle($user, $collection, 'First', 'get-user', 'GET', 200, 'application/json');
    createEndpointAction()->handle($user, $collection, 'Second', 'get-user', 'GET', 200, 'application/json');
    $third = createEndpointAction()->handle($user, $collection, 'Third', 'get-user', 'GET', 200, 'application/json');

    expect($third->slug)->toBe('get-user-2');
});

test('allows the same slug for a different method', function () {
    $user = createEndpointUser();
    $collection = createEndpointCollection($user);

    createEndpointAction()->handle($user, $collection, 'Get User', 'users', 'GET', 200, 'application/json');
    $post = createEndpointAction()->handle($user, $collection, 'Create User', 'users', 'POST', 201, 'application/json');

    expect($post->slug)->toBe('users');
});

test('defaults description to null when not provided', function () {
    $user = createEndpointUser();
    $collection = createEndpointCollection($user);

    $endpoint = createEndpointAction()->handle($user, $collection, 'Get User', 'get-user', 'GET', 200, 'application/json');

    expect($endpoint->description)->toBeNull();
});

test('defaults response_body to null when not provided', function () {
    $user = createEndpointUser();
    $collection = createEndpointCollection($user);

    $endpoint = createEndpointAction()->handle($user, $collection, 'Get User', 'get-user', 'GET', 200, 'application/json');

    expect($endpoint->response_body)->toBeNull();
});

test('defaults is_active to true when not provided', function () {
    $user = createEndpointUser();
    $collection = createEndpointCollection($user);

    $endpoint = createEndpointAction()->handle($user, $collection, 'Get User', 'get-user', 'GET', 200, 'application/json');

    expect($endpoint->is_active)->toBeTrue();
});
