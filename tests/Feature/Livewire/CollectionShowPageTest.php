<?php

use App\Models\Endpoint;
use App\Models\EndpointCollection;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to login', function () {
    $collection = EndpointCollection::factory()->create();

    $this->get(route('collections.show', $collection))->assertRedirect(route('login'));
});

test('owner can view the collection page', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    Livewire::test('pages::collections.show', ['collection' => $collection])
        ->assertOk()
        ->assertSee($collection->name);
});

test('non-owner cannot view the collection page', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($other);

    $this->get(route('collections.show', $collection))->assertForbidden();
});

test('endpoints are listed', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $endpoint = Endpoint::factory()->create([
        'user_id' => $user->id,
        'collection_id' => $collection->id,
    ]);
    $this->actingAs($user);

    Livewire::test('pages::collections.show', ['collection' => $collection])
        ->assertSee($endpoint->name);
});

test('delete removes the collection and redirects to dashboard', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    Livewire::test('pages::collections.show', ['collection' => $collection])
        ->call('delete')
        ->assertRedirect(route('dashboard'));

    $this->assertDatabaseMissing('endpoint_collections', ['id' => $collection->id]);
});

test('toggleActive flips endpoint active status', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $endpoint = Endpoint::factory()->create([
        'user_id' => $user->id,
        'collection_id' => $collection->id,
        'is_active' => true,
    ]);
    $this->actingAs($user);

    Livewire::test('pages::collections.show', ['collection' => $collection])
        ->call('toggleActive', $endpoint->id);

    expect($endpoint->fresh()->is_active)->toBeFalse();
});

test('cancelImport resets import state', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    Livewire::test('pages::collections.show', ['collection' => $collection])
        ->set('showImport', true)
        ->call('cancelImport')
        ->assertSet('showImport', false)
        ->assertSet('importError', null);
});
