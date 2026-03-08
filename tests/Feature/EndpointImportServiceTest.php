<?php

use App\Data\EndpointData;
use App\Models\EndpointCollection;
use App\Models\User;
use App\Services\EndpointImportService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function user(): User
{
    return User::factory()->create();
}

function collection(User $user): EndpointCollection
{
    return EndpointCollection::factory()->create(['user_id' => $user->id]);
}

function service(): EndpointImportService
{
    return app(EndpointImportService::class);
}

function baseData(array $overrides = []): array
{
    return array_merge([
        'name' => 'Get user',
        'slug' => 'test-slug',
        'method' => 'GET',
        'status_code' => 200,
        'content_type' => 'application/json',
        'response_body' => '{"message":"ok"}',
        'is_active' => true,
        'conditional_responses' => [],
    ], $overrides);
}

function endpointData(array $overrides = []): EndpointData
{
    return EndpointData::fromArray(baseData($overrides));
}

test('imports an endpoint for the given user', function () {
    $user = user();
    $col = collection($user);

    $endpoint = service()->import($user, endpointData(), $col);

    expect($endpoint->user_id)->toBe($user->id)
        ->and($endpoint->collection_id)->toBe($col->id)
        ->and($endpoint->name)->toBe('Get user')
        ->and($endpoint->method)->toBe('GET')
        ->and($endpoint->status_code)->toBe(200)
        ->and($endpoint->content_type)->toBe('application/json')
        ->and($endpoint->response_body)->toBe("{\n    \"message\": \"ok\"\n}")
        ->and($endpoint->is_active)->toBeTrue();
});

test('uses slug from data when it does not exist yet', function () {
    $user = user();
    $col = collection($user);

    $endpoint = service()->import($user, endpointData(['slug' => 'my-custom-slug']), $col);

    expect($endpoint->slug)->toBe('my-custom-slug');
});

test('generates a new slug when the slug already exists in the collection', function () {
    $user = user();
    $col = collection($user);

    $existing = service()->import($user, endpointData(['slug' => 'taken-slug']), $col);
    $imported = service()->import($user, endpointData(['slug' => 'taken-slug']), $col);

    expect($imported->slug)->not->toBe($existing->slug);
});

test('generates a new slug when no slug is provided', function () {
    $user = user();
    $col = collection($user);

    $endpoint = service()->import($user, endpointData(['slug' => null]), $col);

    expect($endpoint->slug)->not->toBeEmpty();
});

test('defaults is_active to true when not provided', function () {
    $user = user();
    $col = collection($user);
    $data = baseData();
    unset($data['is_active']);

    $endpoint = service()->import($user, EndpointData::fromArray($data), $col);

    expect($endpoint->is_active)->toBeTrue();
});

test('imports without conditional responses', function () {
    $user = user();
    $col = collection($user);

    $endpoint = service()->import($user, endpointData(['conditional_responses' => []]), $col);

    expect($endpoint->conditionalResponses()->count())->toBe(0);
});

test('imports conditional responses', function () {
    $user = user();
    $col = collection($user);

    $endpoint = service()->import($user, endpointData([
        'conditional_responses' => [
            [
                'condition_source' => 'body',
                'condition_field' => 'id',
                'condition_operator' => 'equals',
                'condition_value' => '1',
                'status_code' => 404,
                'content_type' => 'application/json',
                'response_body' => '{"message":"not found"}',
                'priority' => 0,
            ],
        ],
    ]), $col);

    expect($endpoint->conditionalResponses()->count())->toBe(1);

    $cr = $endpoint->conditionalResponses()->first();

    expect($cr->condition_source)->toBe(App\Enums\ConditionSource::Body)
        ->and($cr->condition_field)->toBe('id')
        ->and($cr->condition_operator)->toBe(App\Enums\ConditionOperator::Equals)
        ->and($cr->condition_value)->toBe('1')
        ->and($cr->status_code)->toBe(404)
        ->and(json_decode($cr->response_body, true))->toBe(['message' => 'not found'])
        ->and($cr->priority)->toBe(0);
});

test('imports multiple conditional responses', function () {
    $user = user();
    $col = collection($user);

    $endpoint = service()->import($user, endpointData([
        'conditional_responses' => [
            [
                'condition_source' => 'body', 'condition_field' => 'id',
                'condition_operator' => 'equals', 'condition_value' => '1',
                'status_code' => 200, 'content_type' => 'application/json',
                'response_body' => '{"id":1}', 'priority' => 0,
            ],
            [
                'condition_source' => 'query', 'condition_field' => 'status',
                'condition_operator' => 'equals', 'condition_value' => 'active',
                'status_code' => 200, 'content_type' => 'application/json',
                'response_body' => '{"status":"active"}', 'priority' => 1,
            ],
        ],
    ]), $col);

    expect($endpoint->conditionalResponses()->count())->toBe(2);
});

test('returns the created endpoint', function () {
    $user = user();
    $col = collection($user);

    $endpoint = service()->import($user, endpointData(), $col);

    expect($endpoint)->toBeInstanceOf(App\Models\Endpoint::class)
        ->and($endpoint->exists)->toBeTrue();
});
