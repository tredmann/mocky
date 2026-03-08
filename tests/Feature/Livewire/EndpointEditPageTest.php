<?php

use App\Models\Endpoint;
use App\Models\EndpointCollection;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to login', function () {
    $collection = EndpointCollection::factory()->create();
    $endpoint = Endpoint::factory()->create(['collection_id' => $collection->id]);

    $this->get(route('endpoints.edit', [$collection, $endpoint]))->assertRedirect(route('login'));
});

test('owner can view the edit page with pre-filled data', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $endpoint = Endpoint::factory()->create([
        'user_id' => $user->id,
        'collection_id' => $collection->id,
        'name' => 'Get User',
        'slug' => 'get-user',
        'method' => 'GET',
        'status_code' => 200,
        'content_type' => 'application/json',
    ]);
    $this->actingAs($user);

    Livewire::test('pages::endpoints.edit', ['collection' => $collection, 'endpoint' => $endpoint])
        ->assertOk()
        ->assertSet('name', 'Get User')
        ->assertSet('slug', 'get-user')
        ->assertSet('method', 'GET')
        ->assertSet('status_code', 200)
        ->assertSet('content_type', 'application/json');
});

test('non-owner cannot view the edit page', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $owner->id]);
    $endpoint = Endpoint::factory()->create([
        'user_id' => $owner->id,
        'collection_id' => $collection->id,
    ]);
    $this->actingAs($other);

    $this->get(route('endpoints.edit', [$collection, $endpoint]))->assertForbidden();
});

test('save updates the endpoint and redirects', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $endpoint = Endpoint::factory()->create([
        'user_id' => $user->id,
        'collection_id' => $collection->id,
        'name' => 'Old Name',
        'slug' => 'old-slug',
    ]);
    $this->actingAs($user);

    Livewire::test('pages::endpoints.edit', ['collection' => $collection, 'endpoint' => $endpoint])
        ->set('name', 'New Name')
        ->set('slug', 'new-slug')
        ->call('save')
        ->assertRedirect();

    $this->assertDatabaseHas('endpoints', [
        'id' => $endpoint->id,
        'name' => 'New Name',
        'slug' => 'new-slug',
    ]);
});

test('save fails validation when name is empty', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $endpoint = Endpoint::factory()->create([
        'user_id' => $user->id,
        'collection_id' => $collection->id,
    ]);
    $this->actingAs($user);

    Livewire::test('pages::endpoints.edit', ['collection' => $collection, 'endpoint' => $endpoint])
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name']);
});

test('save fails validation when slug is empty', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $endpoint = Endpoint::factory()->create([
        'user_id' => $user->id,
        'collection_id' => $collection->id,
    ]);
    $this->actingAs($user);

    Livewire::test('pages::endpoints.edit', ['collection' => $collection, 'endpoint' => $endpoint])
        ->set('slug', '')
        ->call('save')
        ->assertHasErrors(['slug']);
});

test('save fails validation when status code is out of range', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $endpoint = Endpoint::factory()->create([
        'user_id' => $user->id,
        'collection_id' => $collection->id,
    ]);
    $this->actingAs($user);

    Livewire::test('pages::endpoints.edit', ['collection' => $collection, 'endpoint' => $endpoint])
        ->set('status_code', 99)
        ->call('save')
        ->assertHasErrors(['status_code']);
});
