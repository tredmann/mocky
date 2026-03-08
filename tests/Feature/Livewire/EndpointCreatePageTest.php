<?php

use App\Models\EndpointCollection;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to login', function () {
    $collection = EndpointCollection::factory()->create();

    $this->get(route('endpoints.create', $collection))->assertRedirect(route('login'));
});

test('authenticated user can view the create endpoint page', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    Livewire::test('pages::endpoints.create', ['collection' => $collection])->assertOk();
});

test('save creates an endpoint and redirects', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    Livewire::test('pages::endpoints.create', ['collection' => $collection])
        ->set('name', 'Get User')
        ->set('slug', 'get-user')
        ->set('method', 'GET')
        ->set('status_code', 200)
        ->set('content_type', 'application/json')
        ->call('save')
        ->assertRedirect();

    $this->assertDatabaseHas('endpoints', [
        'user_id' => $user->id,
        'collection_id' => $collection->id,
        'name' => 'Get User',
        'slug' => 'get-user',
        'method' => 'GET',
    ]);
});

test('save fails validation when name is missing', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    Livewire::test('pages::endpoints.create', ['collection' => $collection])
        ->set('name', '')
        ->set('slug', 'my-endpoint')
        ->call('save')
        ->assertHasErrors(['name']);
});

test('save fails validation when slug is missing', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    Livewire::test('pages::endpoints.create', ['collection' => $collection])
        ->set('name', 'My Endpoint')
        ->set('slug', '')
        ->call('save')
        ->assertHasErrors(['slug']);
});

test('save fails validation when status code is out of range', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    Livewire::test('pages::endpoints.create', ['collection' => $collection])
        ->set('name', 'My Endpoint')
        ->set('slug', 'my-endpoint')
        ->set('status_code', 99)
        ->call('save')
        ->assertHasErrors(['status_code']);
});
