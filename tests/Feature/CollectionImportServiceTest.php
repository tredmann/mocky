<?php

use App\Models\EndpointCollection;
use App\Models\User;
use App\Services\CollectionImportService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function collectionImportUser(): User
{
    return User::factory()->create();
}

function collectionImportService(): CollectionImportService
{
    return app(CollectionImportService::class);
}

function collectionBaseData(array $overrides = []): array
{
    return array_merge([
        'name' => 'Test Collection',
        'description' => 'A test collection',
        'endpoints' => [],
    ], $overrides);
}

test('imports a collection for the given user', function () {
    $user = collectionImportUser();

    $collection = collectionImportService()->import($user, collectionBaseData());

    expect($collection->user_id)->toBe($user->id)
        ->and($collection->name)->toBe('Test Collection')
        ->and($collection->description)->toBe('A test collection');
});

test('imports collection without endpoints', function () {
    $user = collectionImportUser();

    $collection = collectionImportService()->import($user, collectionBaseData(['endpoints' => []]));

    expect($collection->endpoints()->count())->toBe(0);
});

test('imports collection with endpoints', function () {
    $user = collectionImportUser();
    $data = collectionBaseData([
        'endpoints' => [
            [
                'name' => 'Get user',
                'slug' => 'get-user',
                'method' => 'GET',
                'status_code' => 200,
                'content_type' => 'application/json',
                'response_body' => '{"id":1}',
                'is_active' => true,
                'conditional_responses' => [],
            ],
            [
                'name' => 'Create user',
                'slug' => 'create-user',
                'method' => 'POST',
                'status_code' => 201,
                'content_type' => 'application/json',
                'response_body' => '{"id":2}',
                'is_active' => true,
                'conditional_responses' => [],
            ],
        ],
    ]);

    $collection = collectionImportService()->import($user, $data);

    expect($collection->endpoints()->count())->toBe(2);
});

test('imports collection with endpoints that have conditional responses', function () {
    $user = collectionImportUser();
    $data = collectionBaseData([
        'endpoints' => [
            [
                'name' => 'Get user',
                'slug' => 'get-user',
                'method' => 'GET',
                'status_code' => 200,
                'content_type' => 'application/json',
                'response_body' => '{"id":1}',
                'is_active' => true,
                'conditional_responses' => [
                    [
                        'condition_source' => 'query',
                        'condition_field' => 'id',
                        'condition_operator' => 'equals',
                        'condition_value' => '999',
                        'status_code' => 404,
                        'content_type' => 'application/json',
                        'response_body' => '{"error":"not found"}',
                        'priority' => 0,
                    ],
                ],
            ],
        ],
    ]);

    $collection = collectionImportService()->import($user, $data);
    $endpoint = $collection->endpoints()->first();

    expect($endpoint->conditionalResponses()->count())->toBe(1);
});

test('returns the created collection', function () {
    $user = collectionImportUser();

    $collection = collectionImportService()->import($user, collectionBaseData());

    expect($collection)->toBeInstanceOf(EndpointCollection::class)
        ->and($collection->exists)->toBeTrue();
});

test('defaults description to null when not provided', function () {
    $user = collectionImportUser();
    $data = collectionBaseData();
    unset($data['description']);

    $collection = collectionImportService()->import($user, $data);

    expect($collection->description)->toBeNull();
});

test('generates a unique slug for the imported collection', function () {
    $user = collectionImportUser();

    $first = collectionImportService()->import($user, collectionBaseData());
    $second = collectionImportService()->import($user, collectionBaseData());

    expect($first->slug)->not->toBe($second->slug);
});
