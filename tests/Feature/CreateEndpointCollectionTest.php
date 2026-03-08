<?php

use App\Actions\CreateEndpointCollection;
use App\Models\EndpointCollection;
use App\Models\User;

function createCollectionAction(): CreateEndpointCollection
{
    return app(CreateEndpointCollection::class);
}

function createCollectionUser(): User
{
    return User::factory()->create();
}

test('creates a collection for the given user', function () {
    $user = createCollectionUser();

    $collection = createCollectionAction()->handle($user, 'My API', 'A description');

    expect($collection->user_id)->toBe($user->id)
        ->and($collection->name)->toBe('My API')
        ->and($collection->description)->toBe('A description');
});

test('returns a persisted EndpointCollection', function () {
    $user = createCollectionUser();

    $collection = createCollectionAction()->handle($user, 'My API');

    expect($collection)->toBeInstanceOf(EndpointCollection::class)
        ->and($collection->exists)->toBeTrue();
});

test('auto-generates a slug when none is provided', function () {
    $user = createCollectionUser();

    $collection = createCollectionAction()->handle($user, 'My API');

    expect($collection->slug)->not->toBeEmpty();
});

test('uses the provided slug when it does not already exist', function () {
    $user = createCollectionUser();

    $collection = createCollectionAction()->handle($user, 'My API', null, 'my-custom-slug');

    expect($collection->slug)->toBe('my-custom-slug');
});

test('falls back to a uuid slug when the provided slug is already taken', function () {
    $user = createCollectionUser();

    createCollectionAction()->handle($user, 'First', null, 'taken-slug');
    $second = createCollectionAction()->handle($user, 'Second', null, 'taken-slug');

    expect($second->slug)->not->toBe('taken-slug');
});

test('defaults description to null when not provided', function () {
    $user = createCollectionUser();

    $collection = createCollectionAction()->handle($user, 'My API');

    expect($collection->description)->toBeNull();
});
