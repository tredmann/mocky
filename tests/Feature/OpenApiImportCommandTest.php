<?php

use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('imports openapi yaml file via artisan command', function () {
    $user = User::factory()->create();

    $this->artisan('openapi:import', [
        'file' => base_path('tests/fixtures/petstore.yaml'),
    ])
        ->expectsOutputToContain('4 endpoint(s)')
        ->assertSuccessful();
});

test('imports openapi json file via artisan command', function () {
    $user = User::factory()->create();

    $this->artisan('openapi:import', [
        'file' => base_path('tests/fixtures/petstore.json'),
    ])
        ->expectsOutputToContain('1 endpoint(s)')
        ->assertSuccessful();
});

test('fails when file does not exist', function () {
    User::factory()->create();

    $this->artisan('openapi:import', [
        'file' => '/nonexistent/file.yaml',
    ])
        ->expectsOutputToContain('not found')
        ->assertFailed();
});

test('fails when no user exists', function () {
    $this->artisan('openapi:import', [
        'file' => base_path('tests/fixtures/petstore.yaml'),
    ])
        ->expectsOutputToContain('User not found')
        ->assertFailed();
});

test('uses specified user by email', function () {
    User::factory()->create(['email' => 'first@example.com']);
    $target = User::factory()->create(['email' => 'target@example.com']);

    $this->artisan('openapi:import', [
        'file' => base_path('tests/fixtures/petstore.yaml'),
        '--user' => 'target@example.com',
    ])
        ->expectsOutputToContain('target@example.com')
        ->assertSuccessful();
});

test('fails with oversized file', function () {
    $user = User::factory()->create();
    $tmpFile = tempnam(sys_get_temp_dir(), 'openapi_big_');
    file_put_contents($tmpFile, str_repeat('x', 6 * 1024 * 1024));

    $this->artisan('openapi:import', [
        'file' => $tmpFile,
    ])
        ->expectsOutputToContain('too large')
        ->assertFailed();

    unlink($tmpFile);
});
