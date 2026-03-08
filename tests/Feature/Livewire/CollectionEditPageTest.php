<?php

use App\Models\EndpointCollection;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to login', function () {
    $collection = EndpointCollection::factory()->create();

    $this->get(route('collections.edit', $collection))->assertRedirect(route('login'));
});

test('owner can view the edit page with pre-filled data', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create([
        'user_id' => $user->id,
        'name' => 'My API',
        'description' => 'Some description',
    ]);
    $this->actingAs($user);

    Livewire::test('pages::collections.edit', ['collection' => $collection])
        ->assertOk()
        ->assertSet('name', 'My API')
        ->assertSet('description', 'Some description');
});

test('non-owner cannot view the edit page', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($other);

    $this->get(route('collections.edit', $collection))->assertForbidden();
});

test('save updates the collection and redirects', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id, 'name' => 'Old Name']);
    $this->actingAs($user);

    Livewire::test('pages::collections.edit', ['collection' => $collection])
        ->set('name', 'New Name')
        ->set('description', 'Updated description')
        ->call('save')
        ->assertRedirect();

    $this->assertDatabaseHas('endpoint_collections', [
        'id' => $collection->id,
        'name' => 'New Name',
        'description' => 'Updated description',
    ]);
});

test('save fails validation when name is empty', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    Livewire::test('pages::collections.edit', ['collection' => $collection])
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name']);
});
