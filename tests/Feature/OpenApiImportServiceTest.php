<?php

use App\Models\User;
use App\Services\OpenApiImportService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function openApiUser(): User
{
    return User::factory()->create();
}

function openApiService(): OpenApiImportService
{
    return app(OpenApiImportService::class);
}

function fixturePath(string $filename): string
{
    return base_path("tests/fixtures/{$filename}");
}

// --- Petstore YAML (real-world example) ---

test('imports petstore yaml with correct collection name and description', function () {
    $user = openApiUser();

    $collection = openApiService()->importFromFile($user, fixturePath('petstore.yaml'));

    expect($collection->name)->toBe('Petstore API')
        ->and($collection->description)->toBe('A sample API that uses a petstore as an example')
        ->and($collection->user_id)->toBe($user->id);
});

test('groups path variants into fewer endpoints', function () {
    $user = openApiUser();

    $collection = openApiService()->importFromFile($user, fixturePath('petstore.yaml'));
    $endpoints = $collection->endpoints()->orderBy('slug')->get();

    // pets GET, pets POST, pets DELETE, pets-vaccinations GET — not 5 separate paths
    expect($endpoints)->toHaveCount(4);

    $methods = $endpoints->pluck('method')->sort()->values()->toArray();
    expect($methods)->toBe(['DELETE', 'GET', 'GET', 'POST']);
});

test('uses operationId as endpoint name when available', function () {
    $user = openApiUser();

    $collection = openApiService()->importFromFile($user, fixturePath('petstore.yaml'));
    $endpoint = $collection->endpoints()->where('slug', 'pets')->where('method', 'GET')->first();

    expect($endpoint->name)->toBe('listPets');
});

test('falls back to summary when no operationId', function () {
    $user = openApiUser();

    $collection = openApiService()->importFromFile($user, fixturePath('petstore.yaml'));
    $endpoint = $collection->endpoints()->where('slug', 'pets-vaccinations')->where('method', 'GET')->first();

    expect($endpoint->name)->toBe('List vaccinations for a pet');
});

test('generates slug from path without method prefix', function () {
    $user = openApiUser();

    $collection = openApiService()->importFromFile($user, fixturePath('petstore.yaml'));
    $slugsWithMethods = $collection->endpoints()->get()->map(fn ($e) => $e->method.' '.$e->slug)->sort()->values()->toArray();

    expect($slugsWithMethods)->toBe([
        'DELETE pets',
        'GET pets',
        'GET pets-vaccinations',
        'POST pets',
    ]);
});

test('uses first success response as default response', function () {
    $user = openApiUser();

    $collection = openApiService()->importFromFile($user, fixturePath('petstore.yaml'));
    $endpoint = $collection->endpoints()->where('slug', 'pets')->where('method', 'GET')->first();

    expect($endpoint->status_code)->toBe(200)
        ->and($endpoint->content_type)->toBe('application/json');

    $body = json_decode($endpoint->response_body, true);
    expect($body)->toBeArray()
        ->and($body[0]['name'])->toBe('Buddy');
});

test('creates conditional responses for non-default status codes', function () {
    $user = openApiUser();

    $collection = openApiService()->importFromFile($user, fixturePath('petstore.yaml'));
    $endpoint = $collection->endpoints()->where('slug', 'pets')->where('method', 'GET')->first();

    // One header conditional (500 error) + one path conditional (showPetById)
    $conditionals = $endpoint->conditionalResponses()->get();
    expect($conditionals)->toHaveCount(2);

    $headerCr = $conditionals->where('condition_source', 'header')->first();
    expect($headerCr->condition_field)->toBe('X-Mock-Response')
        ->and($headerCr->condition_value)->toBe('500')
        ->and($headerCr->status_code)->toBe(500);
});

test('show pet by id is a path conditional on list pets endpoint', function () {
    $user = openApiUser();

    $collection = openApiService()->importFromFile($user, fixturePath('petstore.yaml'));
    $endpoint = $collection->endpoints()->where('slug', 'pets')->where('method', 'GET')->first();

    $pathCr = $endpoint->conditionalResponses()->where('condition_source', 'path')->first();

    expect($pathCr)->not->toBeNull()
        ->and($pathCr->condition_field)->toBe('0')
        ->and($pathCr->condition_operator)->toBe('not_equals')
        ->and($pathCr->condition_value)->toBe('')
        ->and($pathCr->status_code)->toBe(200);

    $body = json_decode($pathCr->response_body, true);
    expect($body['name'])->toBe('Buddy');
});

test('handles 201 as success response for create endpoints', function () {
    $user = openApiUser();

    $collection = openApiService()->importFromFile($user, fixturePath('petstore.yaml'));
    $endpoint = $collection->endpoints()->where('slug', 'pets')->where('method', 'POST')->first();

    expect($endpoint->status_code)->toBe(201);

    $body = json_decode($endpoint->response_body, true);
    expect($body['name'])->toBe('Rex');
});

test('handles 204 no-content response', function () {
    $user = openApiUser();

    $collection = openApiService()->importFromFile($user, fixturePath('petstore.yaml'));
    // DELETE /pets/{petId} is promoted to slug "pets" DELETE
    $endpoint = $collection->endpoints()->where('slug', 'pets')->where('method', 'DELETE')->first();

    expect($endpoint->status_code)->toBe(204);
});

test('sets correct method for each endpoint', function () {
    $user = openApiUser();

    $collection = openApiService()->importFromFile($user, fixturePath('petstore.yaml'));

    $getEndpoint = $collection->endpoints()->where('slug', 'pets')->where('method', 'GET')->first();
    $postEndpoint = $collection->endpoints()->where('slug', 'pets')->where('method', 'POST')->first();
    $deleteEndpoint = $collection->endpoints()->where('slug', 'pets')->where('method', 'DELETE')->first();

    expect($getEndpoint->method)->toBe('GET')
        ->and($postEndpoint->method)->toBe('POST')
        ->and($deleteEndpoint->method)->toBe('DELETE');
});

test('all imported endpoints are active', function () {
    $user = openApiUser();

    $collection = openApiService()->importFromFile($user, fixturePath('petstore.yaml'));

    $inactiveCount = $collection->endpoints()->where('is_active', false)->count();
    expect($inactiveCount)->toBe(0);
});

// --- JSON format ---

test('imports openapi json file', function () {
    $user = openApiUser();

    $collection = openApiService()->importFromFile($user, fixturePath('petstore.json'));

    expect($collection->name)->toBe('Petstore API (JSON)')
        ->and($collection->endpoints()->count())->toBe(1);
});

// --- Schema-based example generation ---

test('generates example from schema when no example provided', function () {
    $user = openApiUser();

    $collection = openApiService()->importFromFile($user, fixturePath('schema-only.yaml'));
    $endpoint = $collection->endpoints()->where('slug', 'users')->where('method', 'GET')->first();

    $body = json_decode($endpoint->response_body, true);
    expect($body)->toBeArray()
        ->and($body[0])->toHaveKeys(['id', 'email', 'name', 'active'])
        ->and($body[0]['email'])->toBe('user@example.com')
        ->and($body[0]['id'])->toBe(0)
        ->and($body[0]['active'])->toBeFalse();
});

test('uses first enum value for enum properties', function () {
    $user = openApiUser();

    $collection = openApiService()->importFromFile($user, fixturePath('schema-only.yaml'));
    $endpoint = $collection->endpoints()->where('slug', 'users')->where('method', 'POST')->first();

    $body = json_decode($endpoint->response_body, true);
    expect($body['role'])->toBe('admin');
});

// --- Minimal / edge cases ---

test('imports minimal openapi spec with no paths', function () {
    $user = openApiUser();

    $collection = openApiService()->importFromFile($user, fixturePath('minimal-openapi.yaml'));

    expect($collection->name)->toBe('Minimal API')
        ->and($collection->endpoints()->count())->toBe(0);
});

test('throws exception for unsupported file extension', function () {
    $user = openApiUser();

    openApiService()->importFromFile($user, '/tmp/test.txt');
})->throws(InvalidArgumentException::class, 'Unsupported file format');

test('conditional response priorities are sequential', function () {
    $user = openApiUser();

    $collection = openApiService()->importFromFile($user, fixturePath('petstore.yaml'));
    $endpoint = $collection->endpoints()->where('slug', 'pets')->where('method', 'POST')->first();
    $conditionals = $endpoint->conditionalResponses()->orderBy('priority')->get();

    expect($conditionals)->toHaveCount(1);
    expect($conditionals->first()->priority)->toBe(0);
});
