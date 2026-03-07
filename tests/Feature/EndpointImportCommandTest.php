<?php

use App\Models\Endpoint;
use App\Models\EndpointCollection;
use App\Models\User;
use Illuminate\Support\Facades\File;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function importFixture(array $overrides = []): string
{
    $data = array_merge([
        'name' => 'Test Endpoint',
        'slug' => 'test-endpoint',
        'method' => 'GET',
        'status_code' => 200,
        'content_type' => 'application/json',
        'response_body' => '{"ok":true}',
        'is_active' => true,
        'conditional_responses' => [],
    ], $overrides);

    $path = sys_get_temp_dir().'/import-test-'.uniqid().'.json';
    File::put($path, json_encode($data));

    return $path;
}

function userWithCollection(): array
{
    $user = User::factory()->create();
    $collection = EndpointCollection::factory()->create(['user_id' => $user->id, 'slug' => 'test-collection']);

    return [$user, $collection];
}

test('imports endpoint from file', function () {
    [$user, $collection] = userWithCollection();
    $path = importFixture();

    $this->artisan('endpoint:import', ['file' => $path, '--user' => $user->email, '--collection' => $collection->slug])
        ->assertSuccessful();

    expect(Endpoint::where('slug', 'test-endpoint')->where('collection_id', $collection->id)->exists())->toBeTrue();

    File::delete($path);
});

test('assigns endpoint to user by email', function () {
    [$user, $collection] = userWithCollection();
    $path = importFixture(['slug' => 'by-email']);

    $this->artisan('endpoint:import', ['file' => $path, '--user' => $user->email, '--collection' => $collection->slug])
        ->assertSuccessful();

    $endpoint = Endpoint::where('slug', 'by-email')->first();

    expect($endpoint->user_id)->toBe($user->id);

    File::delete($path);
});

test('assigns endpoint to user by id', function () {
    [$user, $collection] = userWithCollection();
    $path = importFixture(['slug' => 'by-id']);

    $this->artisan('endpoint:import', ['file' => $path, '--user' => $user->id, '--collection' => $collection->slug])
        ->assertSuccessful();

    $endpoint = Endpoint::where('slug', 'by-id')->first();

    expect($endpoint->user_id)->toBe($user->id);

    File::delete($path);
});

test('defaults to first user and their first collection when no options given', function () {
    [$user, $collection] = userWithCollection();
    $path = importFixture(['slug' => 'default-user']);

    $this->artisan('endpoint:import', ['file' => $path])
        ->assertSuccessful();

    $endpoint = Endpoint::where('slug', 'default-user')->first();

    expect($endpoint->user_id)->toBe($user->id)
        ->and($endpoint->collection_id)->toBe($collection->id);

    File::delete($path);
});

test('imports conditional responses', function () {
    [$user, $collection] = userWithCollection();
    $path = importFixture([
        'slug' => 'with-conditionals',
        'conditional_responses' => [
            [
                'condition_source' => 'query',
                'condition_field' => 'status',
                'condition_operator' => 'equals',
                'condition_value' => 'active',
                'status_code' => 200,
                'content_type' => 'application/json',
                'response_body' => '{"status":"active"}',
                'priority' => 0,
            ],
        ],
    ]);

    $this->artisan('endpoint:import', ['file' => $path, '--user' => $user->email, '--collection' => $collection->slug])
        ->assertSuccessful();

    $endpoint = Endpoint::where('slug', 'with-conditionals')->first();

    expect($endpoint->conditionalResponses()->count())->toBe(1);

    File::delete($path);
});

test('fails when file does not exist', function () {
    $this->artisan('endpoint:import', ['file' => '/tmp/nonexistent.json'])
        ->assertFailed();
});

test('fails when file contains invalid json', function () {
    $path = sys_get_temp_dir().'/invalid.json';
    File::put($path, 'not json');

    $this->artisan('endpoint:import', ['file' => $path])
        ->assertFailed();

    File::delete($path);
});

test('fails when user is not found', function () {
    $path = importFixture();

    $this->artisan('endpoint:import', ['file' => $path, '--user' => 'nobody@example.com'])
        ->assertFailed();

    File::delete($path);
});

test('outputs success message containing endpoint name', function () {
    [$user, $collection] = userWithCollection();
    $path = importFixture(['name' => 'My API', 'slug' => 'my-api-cmd']);

    $this->artisan('endpoint:import', ['file' => $path, '--user' => $user->email, '--collection' => $collection->slug])
        ->expectsOutputToContain('My API')
        ->assertSuccessful();

    File::delete($path);
});
