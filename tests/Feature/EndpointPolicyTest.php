<?php

use App\Models\Endpoint;
use App\Models\EndpointCollection;
use App\Models\User;
use App\Policies\EndpointPolicy;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function endpointPolicy(): EndpointPolicy
{
    return new EndpointPolicy;
}

test('owner can view endpoint', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $endpoint = Endpoint::factory()->create(['user_id' => $user->id, 'collection_id' => $collection->id]);

    expect(endpointPolicy()->view($user, $endpoint))->toBeTrue();
});

test('non-owner cannot view endpoint', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $owner->id]);
    $endpoint = Endpoint::factory()->create(['user_id' => $owner->id, 'collection_id' => $collection->id]);

    expect(endpointPolicy()->view($other, $endpoint))->toBeFalse();
});

test('owner can update endpoint', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $endpoint = Endpoint::factory()->create(['user_id' => $user->id, 'collection_id' => $collection->id]);

    expect(endpointPolicy()->update($user, $endpoint))->toBeTrue();
});

test('non-owner cannot update endpoint', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $owner->id]);
    $endpoint = Endpoint::factory()->create(['user_id' => $owner->id, 'collection_id' => $collection->id]);

    expect(endpointPolicy()->update($other, $endpoint))->toBeFalse();
});

test('owner can delete endpoint', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $endpoint = Endpoint::factory()->create(['user_id' => $user->id, 'collection_id' => $collection->id]);

    expect(endpointPolicy()->delete($user, $endpoint))->toBeTrue();
});

test('non-owner cannot delete endpoint', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $owner->id]);
    $endpoint = Endpoint::factory()->create(['user_id' => $owner->id, 'collection_id' => $collection->id]);

    expect(endpointPolicy()->delete($other, $endpoint))->toBeFalse();
});
