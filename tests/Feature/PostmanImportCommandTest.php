<?php

use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('imports postman collection via artisan command', function () {
    $user = User::factory()->create();

    $this->artisan('postman:import', [
        'file' => base_path('tests/fixtures/postman-users-api.json'),
    ])
        ->expectsOutputToContain('5 endpoint(s)')
        ->assertSuccessful();
});

test('fails when file does not exist', function () {
    User::factory()->create();

    $this->artisan('postman:import', [
        'file' => '/nonexistent/file.json',
    ])
        ->expectsOutputToContain('not found')
        ->assertFailed();
});

test('fails when no user exists', function () {
    $this->artisan('postman:import', [
        'file' => base_path('tests/fixtures/postman-users-api.json'),
    ])
        ->expectsOutputToContain('User not found')
        ->assertFailed();
});

test('uses specified user by email', function () {
    User::factory()->create(['email' => 'first@example.com']);
    User::factory()->create(['email' => 'target@example.com']);

    $this->artisan('postman:import', [
        'file' => base_path('tests/fixtures/postman-users-api.json'),
        '--user' => 'target@example.com',
    ])
        ->expectsOutputToContain('target@example.com')
        ->assertSuccessful();
});

test('fails with oversized file', function () {
    User::factory()->create();
    $tmpFile = tempnam(sys_get_temp_dir(), 'postman_big_');
    file_put_contents($tmpFile, str_repeat('x', 6 * 1024 * 1024));

    $this->artisan('postman:import', [
        'file' => $tmpFile,
    ])
        ->expectsOutputToContain('too large')
        ->assertFailed();

    unlink($tmpFile);
});

test('imports minimal empty postman collection', function () {
    User::factory()->create();

    $this->artisan('postman:import', [
        'file' => base_path('tests/fixtures/minimal-postman.json'),
    ])
        ->expectsOutputToContain('0 endpoint(s)')
        ->assertSuccessful();
});
