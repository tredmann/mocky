<?php

use App\Models\ConditionalResponse;
use App\Models\Endpoint;
use Illuminate\Support\Facades\File;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('exports endpoint to default filename', function () {
    $endpoint = Endpoint::factory()->create(['slug' => 'my-slug']);

    $path = sys_get_temp_dir().'/my-slug.json';

    $this->artisan('endpoint:export', ['slug' => 'my-slug', '--output' => $path])
        ->assertSuccessful();

    expect(File::exists($path))->toBeTrue();

    File::delete($path);
});

test('output file contains endpoint data', function () {
    $endpoint = Endpoint::factory()->create([
        'name' => 'Get User',
        'slug' => 'get-user',
        'method' => 'GET',
        'status_code' => 200,
        'content_type' => 'application/json',
        'response_body' => '{"id":1}',
        'is_active' => true,
    ]);

    $path = sys_get_temp_dir().'/get-user.json';

    $this->artisan('endpoint:export', ['slug' => 'get-user', '--output' => $path])
        ->assertSuccessful();

    $data = json_decode(File::get($path), true);

    expect($data['name'])->toBe('Get User')
        ->and($data['slug'])->toBe('get-user')
        ->and($data['method'])->toBe('GET')
        ->and($data['status_code'])->toBe(200)
        ->and($data['content_type'])->toBe('application/json')
        ->and($data['response_body'])->toBe('{"id":1}')
        ->and($data['is_active'])->toBeTrue();

    File::delete($path);
});

test('exports conditional responses to file', function () {
    $endpoint = Endpoint::factory()->create(['slug' => 'with-conditions']);

    ConditionalResponse::factory()->count(2)->create(['endpoint_id' => $endpoint->id]);

    $path = sys_get_temp_dir().'/with-conditions.json';

    $this->artisan('endpoint:export', ['slug' => 'with-conditions', '--output' => $path])
        ->assertSuccessful();

    $data = json_decode(File::get($path), true);

    expect($data['conditional_responses'])->toHaveCount(2);

    File::delete($path);
});

test('fails when slug does not exist', function () {
    $this->artisan('endpoint:export', ['slug' => 'nonexistent'])
        ->assertFailed();
});

test('outputs success message with endpoint name', function () {
    $endpoint = Endpoint::factory()->create(['name' => 'My API', 'slug' => 'my-api']);

    $path = sys_get_temp_dir().'/my-api.json';

    $this->artisan('endpoint:export', ['slug' => 'my-api', '--output' => $path])
        ->expectsOutputToContain('My API')
        ->assertSuccessful();

    File::delete($path);
});
