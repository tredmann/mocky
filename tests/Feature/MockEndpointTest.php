<?php

use App\Models\ConditionalResponse;
use App\Models\Endpoint;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

// Helpers
function endpoint(array $attributes = []): Endpoint
{
    $endpoint = Endpoint::factory()->create($attributes);
    $endpoint->load('collection');

    return $endpoint;
}

function conditionalFor(Endpoint $endpoint, array $attributes = []): ConditionalResponse
{
    return ConditionalResponse::factory()->create(array_merge(
        ['endpoint_id' => $endpoint->id],
        $attributes,
    ));
}

function mockUrl(Endpoint $endpoint): string
{
    return '/mock/'.$endpoint->collection->slug.'/'.$endpoint->slug;
}

// Default response
test('returns the default response when no conditions match', function () {
    $endpoint = endpoint(['method' => 'GET', 'status_code' => 200, 'response_body' => '{"message":"default"}']);

    $this->getJson(mockUrl($endpoint))
        ->assertStatus(200)
        ->assertJson(['message' => 'default']);
});

test('returns 405 when request method does not match', function () {
    $endpoint = endpoint(['method' => 'GET']);

    $this->postJson(mockUrl($endpoint))
        ->assertStatus(405);
});

// Body conditions
test('matches condition on json body field', function () {
    $endpoint = endpoint(['method' => 'POST']);

    conditionalFor($endpoint, [
        'condition_source' => 'body',
        'condition_field' => 'id',
        'condition_operator' => 'equals',
        'condition_value' => '1',
        'status_code' => 201,
        'response_body' => '{"message":"matched"}',
    ]);

    $this->postJson(mockUrl($endpoint), ['id' => 1])
        ->assertStatus(201)
        ->assertJson(['message' => 'matched']);
});

test('matches condition on nested json body field', function () {
    $endpoint = endpoint(['method' => 'POST']);

    conditionalFor($endpoint, [
        'condition_source' => 'body',
        'condition_field' => 'user.id',
        'condition_operator' => 'equals',
        'condition_value' => '42',
        'status_code' => 200,
        'response_body' => '{"message":"nested match"}',
    ]);

    $this->postJson(mockUrl($endpoint), ['user' => ['id' => 42]])
        ->assertStatus(200)
        ->assertJson(['message' => 'nested match']);
});

test('falls back to default when body condition does not match', function () {
    $endpoint = endpoint(['method' => 'POST', 'status_code' => 404, 'response_body' => '{"message":"default"}']);

    conditionalFor($endpoint, [
        'condition_source' => 'body',
        'condition_field' => 'id',
        'condition_operator' => 'equals',
        'condition_value' => '1',
        'status_code' => 200,
    ]);

    $this->postJson(mockUrl($endpoint), ['id' => 99])
        ->assertStatus(404)
        ->assertJson(['message' => 'default']);
});

// Query conditions
test('matches condition on query parameter', function () {
    $endpoint = endpoint(['method' => 'GET']);

    conditionalFor($endpoint, [
        'condition_source' => 'query',
        'condition_field' => 'status',
        'condition_operator' => 'equals',
        'condition_value' => 'active',
        'status_code' => 200,
        'response_body' => '{"message":"query matched"}',
    ]);

    $this->getJson(mockUrl($endpoint).'?status=active')
        ->assertStatus(200)
        ->assertJson(['message' => 'query matched']);
});

test('falls back to default when query condition does not match', function () {
    $endpoint = endpoint(['method' => 'GET', 'status_code' => 404]);

    conditionalFor($endpoint, [
        'condition_source' => 'query',
        'condition_field' => 'status',
        'condition_operator' => 'equals',
        'condition_value' => 'active',
        'status_code' => 200,
    ]);

    $this->getJson(mockUrl($endpoint).'?status=inactive')
        ->assertStatus(404);
});

// Header conditions
test('matches condition on request header', function () {
    $endpoint = endpoint(['method' => 'GET']);

    conditionalFor($endpoint, [
        'condition_source' => 'header',
        'condition_field' => 'X-Api-Key',
        'condition_operator' => 'equals',
        'condition_value' => 'secret',
        'status_code' => 200,
        'response_body' => '{"message":"header matched"}',
    ]);

    $this->getJson(mockUrl($endpoint), ['X-Api-Key' => 'secret'])
        ->assertStatus(200)
        ->assertJson(['message' => 'header matched']);
});

test('falls back to default when header condition does not match', function () {
    $endpoint = endpoint(['method' => 'GET', 'status_code' => 401]);

    conditionalFor($endpoint, [
        'condition_source' => 'header',
        'condition_field' => 'X-Api-Key',
        'condition_operator' => 'equals',
        'condition_value' => 'secret',
        'status_code' => 200,
    ]);

    $this->getJson(mockUrl($endpoint), ['X-Api-Key' => 'wrong'])
        ->assertStatus(401);
});

// Path conditions
test('matches condition on first path segment', function () {
    $endpoint = endpoint(['method' => 'GET']);

    conditionalFor($endpoint, [
        'condition_source' => 'path',
        'condition_field' => '0',
        'condition_operator' => 'equals',
        'condition_value' => '42',
        'status_code' => 200,
        'response_body' => '{"message":"path matched"}',
    ]);

    $this->getJson(mockUrl($endpoint).'/42')
        ->assertStatus(200)
        ->assertJson(['message' => 'path matched']);
});

test('matches condition on second path segment', function () {
    $endpoint = endpoint(['method' => 'GET']);

    conditionalFor($endpoint, [
        'condition_source' => 'path',
        'condition_field' => '1',
        'condition_operator' => 'equals',
        'condition_value' => 'orders',
        'status_code' => 200,
        'response_body' => '{"message":"second segment matched"}',
    ]);

    $this->getJson(mockUrl($endpoint).'/42/orders')
        ->assertStatus(200)
        ->assertJson(['message' => 'second segment matched']);
});

test('falls back to default when path condition does not match', function () {
    $endpoint = endpoint(['method' => 'GET', 'status_code' => 404]);

    conditionalFor($endpoint, [
        'condition_source' => 'path',
        'condition_field' => '0',
        'condition_operator' => 'equals',
        'condition_value' => '1',
        'status_code' => 200,
    ]);

    $this->getJson(mockUrl($endpoint).'/99')
        ->assertStatus(404);
});

// Operators
test('not_equals operator matches when value differs', function () {
    $endpoint = endpoint(['method' => 'POST']);

    conditionalFor($endpoint, [
        'condition_source' => 'body',
        'condition_field' => 'role',
        'condition_operator' => 'not_equals',
        'condition_value' => 'admin',
        'status_code' => 403,
        'response_body' => '{"message":"forbidden"}',
    ]);

    $this->postJson(mockUrl($endpoint), ['role' => 'guest'])
        ->assertStatus(403)
        ->assertJson(['message' => 'forbidden']);
});

test('contains operator matches when value is substring', function () {
    $endpoint = endpoint(['method' => 'POST']);

    conditionalFor($endpoint, [
        'condition_source' => 'body',
        'condition_field' => 'email',
        'condition_operator' => 'contains',
        'condition_value' => '@admin.',
        'status_code' => 200,
        'response_body' => '{"message":"admin email"}',
    ]);

    $this->postJson(mockUrl($endpoint), ['email' => 'user@admin.com'])
        ->assertStatus(200)
        ->assertJson(['message' => 'admin email']);
});

// Priority
test('first matching condition by priority wins', function () {
    $endpoint = endpoint(['method' => 'POST']);

    conditionalFor($endpoint, [
        'condition_source' => 'body',
        'condition_field' => 'id',
        'condition_operator' => 'equals',
        'condition_value' => '1',
        'status_code' => 200,
        'response_body' => '{"message":"first"}',
        'priority' => 1,
    ]);

    conditionalFor($endpoint, [
        'condition_source' => 'body',
        'condition_field' => 'id',
        'condition_operator' => 'equals',
        'condition_value' => '1',
        'status_code' => 201,
        'response_body' => '{"message":"second"}',
        'priority' => 2,
    ]);

    $this->postJson(mockUrl($endpoint), ['id' => 1])
        ->assertStatus(200)
        ->assertJson(['message' => 'first']);
});
