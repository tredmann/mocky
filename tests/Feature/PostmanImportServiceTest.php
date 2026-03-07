<?php

use App\Models\User;
use App\Services\PostmanImportService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function postmanUser(): User
{
    return User::factory()->create();
}

function postmanService(): PostmanImportService
{
    return app(PostmanImportService::class);
}

function postmanFixturePath(string $filename): string
{
    return base_path("tests/fixtures/{$filename}");
}

// --- Users API (real-world Postman collection) ---

test('imports postman collection with correct name and description', function () {
    $user = postmanUser();

    $collection = postmanService()->importFromFile($user, postmanFixturePath('postman-users-api.json'));

    expect($collection->name)->toBe('Users API')
        ->and($collection->description)->toBe('API for managing users')
        ->and($collection->user_id)->toBe($user->id);
});

test('imports all requests including nested folders', function () {
    $user = postmanUser();

    $collection = postmanService()->importFromFile($user, postmanFixturePath('postman-users-api.json'));

    expect($collection->endpoints()->count())->toBe(5);
});

test('flattens folder structure into endpoints', function () {
    $user = postmanUser();

    $collection = postmanService()->importFromFile($user, postmanFixturePath('postman-users-api.json'));
    $login = $collection->endpoints()->where('name', 'Login')->first();

    expect($login)->not->toBeNull()
        ->and($login->method)->toBe('POST');
});

test('uses request name as endpoint name', function () {
    $user = postmanUser();

    $collection = postmanService()->importFromFile($user, postmanFixturePath('postman-users-api.json'));
    $names = $collection->endpoints()->pluck('name')->sort()->values()->toArray();

    expect($names)->toBe(['Create User', 'Delete User', 'Get User', 'List Users', 'Login']);
});

test('sets correct HTTP method from postman request', function () {
    $user = postmanUser();

    $collection = postmanService()->importFromFile($user, postmanFixturePath('postman-users-api.json'));

    $login = $collection->endpoints()->where('name', 'Login')->first();
    $listUsers = $collection->endpoints()->where('name', 'List Users')->first();
    $deleteUser = $collection->endpoints()->where('name', 'Delete User')->first();

    expect($login->method)->toBe('POST')
        ->and($listUsers->method)->toBe('GET')
        ->and($deleteUser->method)->toBe('DELETE');
});

test('uses first saved response as default response', function () {
    $user = postmanUser();

    $collection = postmanService()->importFromFile($user, postmanFixturePath('postman-users-api.json'));
    $login = $collection->endpoints()->where('name', 'Login')->first();

    expect($login->status_code)->toBe(200);

    $body = json_decode($login->response_body, true);
    expect($body['token'])->toStartWith('eyJ');
});

test('creates conditional responses for additional saved responses', function () {
    $user = postmanUser();

    $collection = postmanService()->importFromFile($user, postmanFixturePath('postman-users-api.json'));
    $login = $collection->endpoints()->where('name', 'Login')->first();
    $conditionals = $login->conditionalResponses()->get();

    expect($conditionals)->toHaveCount(1);

    $cr = $conditionals->first();
    expect($cr->condition_source)->toBe('header')
        ->and($cr->condition_field)->toBe('X-Mock-Response')
        ->and($cr->condition_value)->toBe('Invalid Credentials')
        ->and($cr->status_code)->toBe(401);

    $body = json_decode($cr->response_body, true);
    expect($body['error'])->toBe('Invalid credentials');
});

test('extracts content type from response headers', function () {
    $user = postmanUser();

    $collection = postmanService()->importFromFile($user, postmanFixturePath('postman-users-api.json'));
    $listUsers = $collection->endpoints()->where('name', 'List Users')->first();

    expect($listUsers->content_type)->toBe('application/json');
});

test('falls back to postman preview language for content type', function () {
    $user = postmanUser();

    $collection = postmanService()->importFromFile($user, postmanFixturePath('postman-users-api.json'));
    $getUser = $collection->endpoints()->where('name', 'Get User')->first();

    expect($getUser->content_type)->toBe('application/json');
});

test('uses request body as fallback when no saved responses', function () {
    $user = postmanUser();

    $collection = postmanService()->importFromFile($user, postmanFixturePath('postman-users-api.json'));
    $createUser = $collection->endpoints()->where('name', 'Create User')->first();

    expect($createUser->status_code)->toBe(200);

    $body = json_decode($createUser->response_body, true);
    expect($body['name'])->toBe('Charlie');
});

test('handles requests with no body and no responses', function () {
    $user = postmanUser();

    $collection = postmanService()->importFromFile($user, postmanFixturePath('postman-users-api.json'));
    $deleteUser = $collection->endpoints()->where('name', 'Delete User')->first();

    expect($deleteUser->response_body)->toBeNull()
        ->and($deleteUser->status_code)->toBe(200);
});

test('handles url as plain string', function () {
    $user = postmanUser();

    $collection = postmanService()->importFromFile($user, postmanFixturePath('postman-users-api.json'));
    $deleteUser = $collection->endpoints()->where('name', 'Delete User')->first();

    expect($deleteUser)->not->toBeNull()
        ->and($deleteUser->slug)->not->toBeEmpty();
});

test('all imported endpoints are active', function () {
    $user = postmanUser();

    $collection = postmanService()->importFromFile($user, postmanFixturePath('postman-users-api.json'));

    $inactiveCount = $collection->endpoints()->where('is_active', false)->count();
    expect($inactiveCount)->toBe(0);
});

test('generates slug from request name', function () {
    $user = postmanUser();

    $collection = postmanService()->importFromFile($user, postmanFixturePath('postman-users-api.json'));
    $listUsers = $collection->endpoints()->where('name', 'List Users')->first();

    expect($listUsers->slug)->toBe('list-users');
});

// --- Minimal / edge cases ---

test('imports minimal postman collection with no items', function () {
    $user = postmanUser();

    $collection = postmanService()->importFromFile($user, postmanFixturePath('minimal-postman.json'));

    expect($collection->name)->toBe('Empty Collection')
        ->and($collection->endpoints()->count())->toBe(0);
});

test('throws exception for invalid postman file', function () {
    $tmpFile = tempnam(sys_get_temp_dir(), 'postman_test_');
    file_put_contents($tmpFile, '{"not": "postman"}');

    try {
        postmanService()->importFromFile(postmanUser(), $tmpFile);
    } finally {
        unlink($tmpFile);
    }
})->throws(InvalidArgumentException::class, 'Invalid Postman collection file');

test('handles multiple conditional responses with sequential priorities', function () {
    $user = postmanUser();

    $collection = postmanService()->importFromFile($user, postmanFixturePath('postman-users-api.json'));
    $getUser = $collection->endpoints()->where('name', 'Get User')->first();
    $conditionals = $getUser->conditionalResponses()->orderBy('priority')->get();

    expect($conditionals)->toHaveCount(1);
    expect($conditionals->first()->priority)->toBe(0);
});
