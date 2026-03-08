<?php

use App\Livewire\ConditionalResponseManager;
use App\Models\ConditionalResponse;
use App\Models\Endpoint;
use App\Models\EndpointCollection;
use App\Models\User;
use Livewire\Livewire;

test('add creates a conditional response', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $endpoint = Endpoint::factory()->create([
        'user_id' => $user->id,
        'collection_id' => $collection->id,
        'method' => 'POST',
    ]);
    $this->actingAs($user);

    Livewire::test(ConditionalResponseManager::class, ['endpoint' => $endpoint, 'method' => 'POST'])
        ->set('condition_source', 'body')
        ->set('condition_field', 'user.id')
        ->set('condition_operator', 'equals')
        ->set('condition_value', '42')
        ->set('status_code', 201)
        ->set('content_type', 'application/json')
        ->set('response_body', '{"created":true}')
        ->call('add');

    $this->assertDatabaseHas('conditional_responses', [
        'endpoint_id' => $endpoint->id,
        'condition_source' => 'body',
        'condition_field' => 'user.id',
        'condition_operator' => 'equals',
        'condition_value' => '42',
        'status_code' => 201,
    ]);
});

test('add resets the form after saving', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $endpoint = Endpoint::factory()->create([
        'user_id' => $user->id,
        'collection_id' => $collection->id,
        'method' => 'POST',
    ]);
    $this->actingAs($user);

    Livewire::test(ConditionalResponseManager::class, ['endpoint' => $endpoint, 'method' => 'POST'])
        ->set('condition_source', 'body')
        ->set('condition_field', 'id')
        ->set('condition_operator', 'equals')
        ->set('condition_value', '1')
        ->set('status_code', 200)
        ->set('content_type', 'application/json')
        ->call('add')
        ->assertSet('showForm', false)
        ->assertSet('condition_field', '')
        ->assertSet('condition_value', '');
});

test('add fails validation when condition_field is empty', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $endpoint = Endpoint::factory()->create([
        'user_id' => $user->id,
        'collection_id' => $collection->id,
    ]);
    $this->actingAs($user);

    Livewire::test(ConditionalResponseManager::class, ['endpoint' => $endpoint, 'method' => 'GET'])
        ->set('condition_field', '')
        ->call('add')
        ->assertHasErrors(['condition_field']);
});

test('add fails validation when condition_value is empty', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $endpoint = Endpoint::factory()->create([
        'user_id' => $user->id,
        'collection_id' => $collection->id,
    ]);
    $this->actingAs($user);

    Livewire::test(ConditionalResponseManager::class, ['endpoint' => $endpoint, 'method' => 'GET'])
        ->set('condition_field', 'id')
        ->set('condition_value', '')
        ->call('add')
        ->assertHasErrors(['condition_value']);
});

test('add fails validation when condition_source is invalid', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $endpoint = Endpoint::factory()->create([
        'user_id' => $user->id,
        'collection_id' => $collection->id,
    ]);
    $this->actingAs($user);

    Livewire::test(ConditionalResponseManager::class, ['endpoint' => $endpoint, 'method' => 'GET'])
        ->set('condition_source', 'invalid')
        ->set('condition_field', 'id')
        ->set('condition_value', '1')
        ->call('add')
        ->assertHasErrors(['condition_source']);
});

test('add fails validation when status_code is out of range', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $endpoint = Endpoint::factory()->create([
        'user_id' => $user->id,
        'collection_id' => $collection->id,
    ]);
    $this->actingAs($user);

    Livewire::test(ConditionalResponseManager::class, ['endpoint' => $endpoint, 'method' => 'GET'])
        ->set('condition_field', 'id')
        ->set('condition_value', '1')
        ->set('status_code', 99)
        ->call('add')
        ->assertHasErrors(['status_code']);
});

test('delete removes the conditional response', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $endpoint = Endpoint::factory()->create([
        'user_id' => $user->id,
        'collection_id' => $collection->id,
    ]);
    $cr = ConditionalResponse::factory()->create(['endpoint_id' => $endpoint->id]);
    $this->actingAs($user);

    Livewire::test(ConditionalResponseManager::class, ['endpoint' => $endpoint, 'method' => 'GET'])
        ->call('delete', $cr->id);

    $this->assertDatabaseMissing('conditional_responses', ['id' => $cr->id]);
});

test('delete only removes responses belonging to the endpoint', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $endpoint = Endpoint::factory()->create(['user_id' => $user->id, 'collection_id' => $collection->id]);
    $other = Endpoint::factory()->create(['user_id' => $user->id, 'collection_id' => $collection->id]);

    $cr = ConditionalResponse::factory()->create(['endpoint_id' => $endpoint->id]);
    $otherCr = ConditionalResponse::factory()->create(['endpoint_id' => $other->id]);
    $this->actingAs($user);

    Livewire::test(ConditionalResponseManager::class, ['endpoint' => $endpoint, 'method' => 'GET'])
        ->call('delete', $otherCr->id);

    $this->assertDatabaseHas('conditional_responses', ['id' => $otherCr->id]);
});

test('resetForm clears all form fields', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $endpoint = Endpoint::factory()->create([
        'user_id' => $user->id,
        'collection_id' => $collection->id,
        'method' => 'POST',
    ]);
    $this->actingAs($user);

    Livewire::test(ConditionalResponseManager::class, ['endpoint' => $endpoint, 'method' => 'POST'])
        ->set('showForm', true)
        ->set('condition_field', 'some.field')
        ->set('condition_value', 'some-value')
        ->set('status_code', 404)
        ->call('resetForm')
        ->assertSet('showForm', false)
        ->assertSet('condition_field', '')
        ->assertSet('condition_value', '')
        ->assertSet('status_code', 200);
});

test('mount sets condition_source to query for GET endpoints', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $endpoint = Endpoint::factory()->create([
        'user_id' => $user->id,
        'collection_id' => $collection->id,
        'method' => 'GET',
    ]);
    $this->actingAs($user);

    Livewire::test(ConditionalResponseManager::class, ['endpoint' => $endpoint, 'method' => 'GET'])
        ->assertSet('condition_source', 'query');
});

test('mount sets condition_source to body for non-GET endpoints', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $endpoint = Endpoint::factory()->create([
        'user_id' => $user->id,
        'collection_id' => $collection->id,
        'method' => 'POST',
    ]);
    $this->actingAs($user);

    Livewire::test(ConditionalResponseManager::class, ['endpoint' => $endpoint, 'method' => 'POST'])
        ->assertSet('condition_source', 'body');
});
