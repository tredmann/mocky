<?php

use App\Models\Endpoint;
use App\Models\EndpointCollection;
use App\Models\EndpointLog;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to login', function () {
    $collection = EndpointCollection::factory()->create();
    $endpoint = Endpoint::factory()->create(['collection_id' => $collection->id]);

    $this->get(route('endpoints.logs', [$collection, $endpoint]))->assertRedirect(route('login'));
});

test('owner can view the logs page', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $endpoint = Endpoint::factory()->create([
        'user_id' => $user->id,
        'collection_id' => $collection->id,
    ]);
    $this->actingAs($user);

    Livewire::test('pages::endpoints.logs', ['collection' => $collection, 'endpoint' => $endpoint])
        ->assertOk();
});

test('non-owner cannot view the logs page', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $owner->id]);
    $endpoint = Endpoint::factory()->create([
        'user_id' => $owner->id,
        'collection_id' => $collection->id,
    ]);
    $this->actingAs($other);

    $this->get(route('endpoints.logs', [$collection, $endpoint]))->assertForbidden();
});

test('logs are listed on the page', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $endpoint = Endpoint::factory()->create([
        'user_id' => $user->id,
        'collection_id' => $collection->id,
    ]);
    EndpointLog::create([
        'endpoint_id' => $endpoint->id,
        'request_method' => 'GET',
        'request_ip' => '127.0.0.1',
        'request_user_agent' => 'TestAgent/1.0',
        'request_headers' => [],
        'request_query' => [],
        'request_body' => null,
        'response_status_code' => 200,
        'response_body' => '{"ok":true}',
        'created_at' => now(),
    ]);
    $this->actingAs($user);

    Livewire::test('pages::endpoints.logs', ['collection' => $collection, 'endpoint' => $endpoint])
        ->assertSee('127.0.0.1');
});

test('expand sets the expanded log id', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $endpoint = Endpoint::factory()->create([
        'user_id' => $user->id,
        'collection_id' => $collection->id,
    ]);
    $log = EndpointLog::create([
        'endpoint_id' => $endpoint->id,
        'request_method' => 'GET',
        'request_ip' => '127.0.0.1',
        'request_user_agent' => 'Test',
        'request_headers' => [],
        'request_query' => [],
        'request_body' => null,
        'response_status_code' => 200,
        'response_body' => null,
        'created_at' => now(),
    ]);
    $this->actingAs($user);

    Livewire::test('pages::endpoints.logs', ['collection' => $collection, 'endpoint' => $endpoint])
        ->call('expand', $log->id)
        ->assertSet('expandedLog', $log->id);
});

test('expand toggles: calling twice collapses the log', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $endpoint = Endpoint::factory()->create([
        'user_id' => $user->id,
        'collection_id' => $collection->id,
    ]);
    $log = EndpointLog::create([
        'endpoint_id' => $endpoint->id,
        'request_method' => 'POST',
        'request_ip' => '10.0.0.1',
        'request_user_agent' => 'Test',
        'request_headers' => [],
        'request_query' => [],
        'request_body' => null,
        'response_status_code' => 201,
        'response_body' => null,
        'created_at' => now(),
    ]);
    $this->actingAs($user);

    Livewire::test('pages::endpoints.logs', ['collection' => $collection, 'endpoint' => $endpoint])
        ->call('expand', $log->id)
        ->call('expand', $log->id)
        ->assertSet('expandedLog', null);
});

test('clearLogs deletes all logs for the endpoint', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $endpoint = Endpoint::factory()->create([
        'user_id' => $user->id,
        'collection_id' => $collection->id,
    ]);
    EndpointLog::create([
        'endpoint_id' => $endpoint->id,
        'request_method' => 'GET',
        'request_ip' => '127.0.0.1',
        'request_user_agent' => 'Test',
        'request_headers' => [],
        'request_query' => [],
        'request_body' => null,
        'response_status_code' => 200,
        'response_body' => null,
        'created_at' => now(),
    ]);
    $this->actingAs($user);

    Livewire::test('pages::endpoints.logs', ['collection' => $collection, 'endpoint' => $endpoint])
        ->call('clearLogs');

    expect(EndpointLog::where('endpoint_id', $endpoint->id)->count())->toBe(0);
});

test('clearLogs only removes logs for the given endpoint', function () {
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id]);
    $endpoint = Endpoint::factory()->create(['user_id' => $user->id, 'collection_id' => $collection->id]);
    $other = Endpoint::factory()->create(['user_id' => $user->id, 'collection_id' => $collection->id]);

    $logData = fn ($epId) => [
        'endpoint_id' => $epId,
        'request_method' => 'GET',
        'request_ip' => '127.0.0.1',
        'request_user_agent' => 'Test',
        'request_headers' => [],
        'request_query' => [],
        'request_body' => null,
        'response_status_code' => 200,
        'response_body' => null,
        'created_at' => now(),
    ];

    EndpointLog::create($logData($endpoint->id));
    EndpointLog::create($logData($other->id));
    $this->actingAs($user);

    Livewire::test('pages::endpoints.logs', ['collection' => $collection, 'endpoint' => $endpoint])
        ->call('clearLogs');

    expect(EndpointLog::where('endpoint_id', $other->id)->count())->toBe(1);
});
