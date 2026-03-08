<?php

use App\Models\EndpointCollection;
use App\Models\User;
use App\Policies\EndpointCollectionPolicy;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function collectionPolicy(): EndpointCollectionPolicy
{
    return new EndpointCollectionPolicy;
}

test('owner can view collection', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);

    expect(collectionPolicy()->view($user, $collection))->toBeTrue();
});

test('non-owner cannot view collection', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $owner->id]);

    expect(collectionPolicy()->view($other, $collection))->toBeFalse();
});

test('owner can update collection', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);

    expect(collectionPolicy()->update($user, $collection))->toBeTrue();
});

test('non-owner cannot update collection', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $owner->id]);

    expect(collectionPolicy()->update($other, $collection))->toBeFalse();
});

test('owner can delete collection', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);

    expect(collectionPolicy()->delete($user, $collection))->toBeTrue();
});

test('non-owner cannot delete collection', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $owner->id]);

    expect(collectionPolicy()->delete($other, $collection))->toBeFalse();
});

test('owner can create endpoint in collection', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);

    expect(collectionPolicy()->createEndpoint($user, $collection))->toBeTrue();
});

test('non-owner cannot create endpoint in collection', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $owner->id]);

    expect(collectionPolicy()->createEndpoint($other, $collection))->toBeFalse();
});
