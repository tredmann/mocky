<?php

use App\Models\ConditionalResponse;
use App\Models\Endpoint;
use App\Services\EndpointExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function exportService(): EndpointExportService
{
    return app(EndpointExportService::class);
}

function exportedJson(Endpoint $endpoint): array
{
    $response = exportService()->export($endpoint);

    ob_start();
    $response->sendContent();

    return json_decode(ob_get_clean(), true);
}

test('returns a streamed response', function () {
    $endpoint = Endpoint::factory()->create();

    $response = exportService()->export($endpoint);

    expect($response)->toBeInstanceOf(StreamedResponse::class);
});

test('sets the correct content type header', function () {
    $endpoint = Endpoint::factory()->create();

    $response = exportService()->export($endpoint);

    expect($response->headers->get('Content-Type'))->toBe('application/json');
});

test('sets the filename based on the endpoint name', function () {
    $endpoint = Endpoint::factory()->create(['name' => 'Get User By ID']);

    $response = exportService()->export($endpoint);

    expect($response->headers->get('Content-Disposition'))
        ->toContain('get-user-by-id.json');
});

test('exports endpoint fields', function () {
    $endpoint = Endpoint::factory()->create([
        'name' => 'Get user',
        'slug' => 'my-slug',
        'method' => 'GET',
        'status_code' => 200,
        'content_type' => 'application/json',
        'response_body' => '{"message":"ok"}',
        'is_active' => true,
    ]);

    $data = exportedJson($endpoint);

    expect($data['name'])->toBe('Get user')
        ->and($data['slug'])->toBe('my-slug')
        ->and($data['method'])->toBe('GET')
        ->and($data['status_code'])->toBe(200)
        ->and($data['content_type'])->toBe('application/json')
        ->and($data['response_body'])->toBe('{"message":"ok"}')
        ->and($data['is_active'])->toBeTrue()
        ->and($data)->toHaveKey('collection_slug');
});

test('exports with empty conditional responses when none exist', function () {
    $endpoint = Endpoint::factory()->create();

    $data = exportedJson($endpoint);

    expect($data['conditional_responses'])->toBeArray()->toBeEmpty();
});

test('exports conditional responses', function () {
    $endpoint = Endpoint::factory()->create();

    ConditionalResponse::factory()->create([
        'endpoint_id' => $endpoint->id,
        'condition_source' => 'body',
        'condition_field' => 'id',
        'condition_operator' => 'equals',
        'condition_value' => '1',
        'status_code' => 404,
        'content_type' => 'application/json',
        'response_body' => '{"message":"not found"}',
        'priority' => 0,
    ]);

    $data = exportedJson($endpoint);

    expect($data['conditional_responses'])->toHaveCount(1);

    $cr = $data['conditional_responses'][0];

    expect($cr['condition_source'])->toBe('body')
        ->and($cr['condition_field'])->toBe('id')
        ->and($cr['condition_operator'])->toBe('equals')
        ->and($cr['condition_value'])->toBe('1')
        ->and($cr['status_code'])->toBe(404)
        ->and($cr['response_body'])->toBe('{"message":"not found"}')
        ->and($cr['priority'])->toBe(0);
});

test('exports multiple conditional responses', function () {
    $endpoint = Endpoint::factory()->create();

    ConditionalResponse::factory()->count(3)->create(['endpoint_id' => $endpoint->id]);

    $data = exportedJson($endpoint);

    expect($data['conditional_responses'])->toHaveCount(3);
});

test('exported json can be reimported', function () {
    $original = Endpoint::factory()->create(['slug' => 'original-slug']);
    $original->load('collection');

    ConditionalResponse::factory()->create([
        'endpoint_id' => $original->id,
        'condition_source' => 'query',
        'condition_field' => 'status',
        'condition_operator' => 'equals',
        'condition_value' => 'active',
        'status_code' => 200,
        'content_type' => 'application/json',
        'response_body' => '{"status":"active"}',
        'priority' => 0,
    ]);

    $data = exportedJson($original);

    $imported = app(App\Services\EndpointImportService::class)->import(
        $original->user,
        $data,
        $original->collection,
    );

    expect($imported->name)->toBe($original->name)
        ->and($imported->method)->toBe($original->method)
        ->and($imported->status_code)->toBe($original->status_code)
        ->and($imported->conditionalResponses()->count())->toBe(1);
});
