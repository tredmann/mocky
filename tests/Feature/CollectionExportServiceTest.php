<?php

use App\Models\ConditionalResponse;
use App\Models\Endpoint;
use App\Models\EndpointCollection;
use App\Services\CollectionExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function collectionExportService(): CollectionExportService
{
    return app(CollectionExportService::class);
}

function exportedCollectionJson(EndpointCollection $collection): array
{
    $response = collectionExportService()->export($collection);

    ob_start();
    $response->sendContent();

    return json_decode(ob_get_clean(), true);
}

test('returns a streamed response', function () {
    $collection = EndpointCollection::factory()->create();

    $response = collectionExportService()->export($collection);

    expect($response)->toBeInstanceOf(StreamedResponse::class);
});

test('sets the correct content type header', function () {
    $collection = EndpointCollection::factory()->create();

    $response = collectionExportService()->export($collection);

    expect($response->headers->get('Content-Type'))->toBe('application/json');
});

test('sets the filename based on the collection name', function () {
    $collection = EndpointCollection::factory()->create(['name' => 'My API Collection']);

    $response = collectionExportService()->export($collection);

    expect($response->headers->get('Content-Disposition'))
        ->toContain('my-api-collection.json');
});

test('exports collection fields', function () {
    $collection = EndpointCollection::factory()->create([
        'name' => 'Test Collection',
        'description' => 'A test collection',
    ]);

    $data = exportedCollectionJson($collection);

    expect($data['name'])->toBe('Test Collection')
        ->and($data['slug'])->toBe((string) $collection->slug)
        ->and($data['description'])->toBe('A test collection')
        ->and($data['endpoints'])->toBeArray();
});

test('exports with empty endpoints when none exist', function () {
    $collection = EndpointCollection::factory()->create();

    $data = exportedCollectionJson($collection);

    expect($data['endpoints'])->toBeArray()->toBeEmpty();
});

test('exports all endpoints in the collection', function () {
    $collection = EndpointCollection::factory()->create();
    Endpoint::factory()->count(3)->create(['collection_id' => $collection->id]);

    $data = exportedCollectionJson($collection);

    expect($data['endpoints'])->toHaveCount(3);
});

test('exports endpoint details including conditional responses', function () {
    $collection = EndpointCollection::factory()->create();
    $endpoint = Endpoint::factory()->create([
        'collection_id' => $collection->id,
        'name' => 'Get user',
        'method' => 'GET',
        'status_code' => 200,
    ]);
    ConditionalResponse::factory()->create(['endpoint_id' => $endpoint->id]);

    $data = exportedCollectionJson($collection);

    expect($data['endpoints'][0]['name'])->toBe('Get user')
        ->and($data['endpoints'][0]['method'])->toBe('GET')
        ->and($data['endpoints'][0]['conditional_responses'])->toHaveCount(1);
});

test('exported collection json can be reimported', function () {
    $collection = EndpointCollection::factory()->create(['name' => 'Original']);
    Endpoint::factory()->count(2)->create(['collection_id' => $collection->id]);

    $data = exportedCollectionJson($collection);

    $importService = app(App\Services\CollectionImportService::class);
    $imported = $importService->import($collection->user, App\Data\CollectionData::fromArray($data));

    expect($imported->name)->toBe('Original')
        ->and($imported->endpoints()->count())->toBe(2);
});
