<?php

use App\Models\ConditionalResponse;
use App\Models\Endpoint;
use App\Models\EndpointCollection;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to login', function () {
    $collection = EndpointCollection::factory()->create();
    $endpoint = Endpoint::factory()->create(['collection_id' => $collection->id]);

    $this->get(route('endpoints.show', [$collection, $endpoint]))->assertRedirect(route('login'));
});

test('owner can view the endpoint page', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $endpoint = Endpoint::factory()->create([
        'user_id' => $user->id,
        'collection_id' => $collection->id,
        'name' => 'Get User',
    ]);
    $this->actingAs($user);

    Livewire::test('pages::endpoints.show', ['collection' => $collection, 'endpoint' => $endpoint])
        ->assertOk()
        ->assertSee('Get User');
});

test('non-owner cannot view the endpoint page', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $owner->id]);
    $endpoint = Endpoint::factory()->create([
        'user_id' => $owner->id,
        'collection_id' => $collection->id,
    ]);
    $this->actingAs($other);

    $this->get(route('endpoints.show', [$collection, $endpoint]))->assertForbidden();
});

test('toggleActive flips the active status', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $endpoint = Endpoint::factory()->create([
        'user_id' => $user->id,
        'collection_id' => $collection->id,
        'is_active' => true,
    ]);
    $this->actingAs($user);

    Livewire::test('pages::endpoints.show', ['collection' => $collection, 'endpoint' => $endpoint])
        ->call('toggleActive');

    expect($endpoint->fresh()->is_active)->toBeFalse();
});

test('toggleActive can re-activate a disabled endpoint', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $endpoint = Endpoint::factory()->create([
        'user_id' => $user->id,
        'collection_id' => $collection->id,
        'is_active' => false,
    ]);
    $this->actingAs($user);

    Livewire::test('pages::endpoints.show', ['collection' => $collection, 'endpoint' => $endpoint])
        ->call('toggleActive');

    expect($endpoint->fresh()->is_active)->toBeTrue();
});

test('delete removes the endpoint and redirects to collection', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $endpoint = Endpoint::factory()->create([
        'user_id' => $user->id,
        'collection_id' => $collection->id,
    ]);
    $this->actingAs($user);

    Livewire::test('pages::endpoints.show', ['collection' => $collection, 'endpoint' => $endpoint])
        ->call('delete')
        ->assertRedirect();

    $this->assertDatabaseMissing('endpoints', ['id' => $endpoint->id]);
});

test('showCurl sets the curl command for the default response', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $endpoint = Endpoint::factory()->create([
        'user_id' => $user->id,
        'collection_id' => $collection->id,
        'method' => 'GET',
    ]);
    $this->actingAs($user);

    Livewire::test('pages::endpoints.show', ['collection' => $collection, 'endpoint' => $endpoint])
        ->call('showCurl', 'default', null)
        ->assertSet('curlCommand', fn ($cmd) => str_contains($cmd, 'curl'));
});

test('showCurl sets the curl command for a conditional response', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $endpoint = Endpoint::factory()->create([
        'user_id' => $user->id,
        'collection_id' => $collection->id,
    ]);
    $cr = ConditionalResponse::factory()->create(['endpoint_id' => $endpoint->id]);
    $this->actingAs($user);

    Livewire::test('pages::endpoints.show', ['collection' => $collection, 'endpoint' => $endpoint])
        ->call('showCurl', 'conditional', (string) $cr->id)
        ->assertSet('curlCommand', fn ($cmd) => str_contains($cmd, 'curl'));
});
