<?php

use App\Services\ImportPathResolver;

function resolver(): ImportPathResolver
{
    return new ImportPathResolver;
}

// --- splitPath ---

test('splits simple path into slug with no variant', function () {
    expect(resolver()->splitPath('/users'))->toBe(['users', null]);
});

test('splits multi-segment path into slug', function () {
    expect(resolver()->splitPath('/api/users'))->toBe(['api-users', null]);
});

test('detects numeric trailing segment as variant', function () {
    expect(resolver()->splitPath('/api/users/1'))->toBe(['api-users', '1']);
});

test('detects colon-param trailing segment as any variant', function () {
    expect(resolver()->splitPath('/api/users/:id'))->toBe(['api-users', '__any__']);
});

test('detects brace-param trailing segment as any variant', function () {
    expect(resolver()->splitPath('/api/users/{id}'))->toBe(['api-users', '__any__']);
});

test('strips double-brace postman variables', function () {
    expect(resolver()->splitPath('/{{base_url}}/users'))->toBe(['users', null]);
});

test('strips double-brace variables and detects trailing variant', function () {
    expect(resolver()->splitPath('/{{base_url}}/users/1'))->toBe(['users', '1']);
});

test('returns endpoint for empty path', function () {
    expect(resolver()->splitPath('/'))->toBe(['endpoint', null]);
});

test('returns endpoint for path with only template vars', function () {
    expect(resolver()->splitPath('/{{base_url}}'))->toBe(['endpoint', null]);
});

test('single segment is never treated as variant', function () {
    expect(resolver()->splitPath('/123'))->toBe(['123', null]);
});

test('strips brace params from middle segments for slug', function () {
    expect(resolver()->splitPath('/pets/{petId}/vaccinations'))->toBe(['pets-vaccinations', null]);
});

// --- isVariableSegment ---

test('numeric segment is variable', function () {
    expect(resolver()->isVariableSegment('42'))->toBeTrue();
});

test('colon param is variable', function () {
    expect(resolver()->isVariableSegment(':id'))->toBeTrue();
});

test('brace param is variable', function () {
    expect(resolver()->isVariableSegment('{petId}'))->toBeTrue();
});

test('plain text is not variable', function () {
    expect(resolver()->isVariableSegment('users'))->toBeFalse();
});

// --- segmentsToSlug ---

test('joins segments into kebab-case slug', function () {
    expect(resolver()->segmentsToSlug(['api', 'users']))->toBe('api-users');
});

test('strips brace params from segments', function () {
    expect(resolver()->segmentsToSlug(['pets', '{petId}', 'vaccinations']))->toBe('pets-vaccinations');
});

test('returns endpoint for empty segments', function () {
    expect(resolver()->segmentsToSlug([]))->toBe('endpoint');
});

// --- groupBySlugAndMethod ---

test('groups items by slug and method', function () {
    $items = [
        ['base_slug' => 'users', 'method' => 'GET', 'id' => 1],
        ['base_slug' => 'users', 'method' => 'POST', 'id' => 2],
        ['base_slug' => 'users', 'method' => 'GET', 'id' => 3],
    ];

    $groups = resolver()->groupBySlugAndMethod($items);

    expect($groups)->toHaveCount(2)
        ->and($groups['users|GET'])->toHaveCount(2)
        ->and($groups['users|POST'])->toHaveCount(1);
});

// --- separateBaseAndVariants ---

test('separates base item from variants', function () {
    $group = [
        ['path_segment' => null, 'id' => 'base'],
        ['path_segment' => '1', 'id' => 'variant1'],
        ['path_segment' => '__any__', 'id' => 'variant2'],
    ];

    [$base, $variants] = resolver()->separateBaseAndVariants($group);

    expect($base['id'])->toBe('base')
        ->and($variants)->toHaveCount(2);
});

test('promotes first item when all have path segments', function () {
    $group = [
        ['path_segment' => '1', 'id' => 'first'],
        ['path_segment' => '2', 'id' => 'second'],
    ];

    [$base, $variants] = resolver()->separateBaseAndVariants($group);

    expect($base['id'])->toBe('first')
        ->and($variants)->toHaveCount(1)
        ->and($variants[0]['id'])->toBe('second');
});

// --- buildPathConditionals ---

test('builds path conditional for numeric variant', function () {
    $variants = [
        ['path_segment' => '1', 'data' => 'v1'],
    ];

    $conditionals = resolver()->buildPathConditionals(
        $variants,
        fn ($item) => ['status_code' => 200, 'content_type' => 'application/json', 'response_body' => '{"id": 1}'],
    );

    expect($conditionals)->toHaveCount(1)
        ->and($conditionals[0]['condition_operator'])->toBe('equals')
        ->and($conditionals[0]['condition_value'])->toBe('1')
        ->and($conditionals[0]['priority'])->toBe(0);
});

test('builds path conditional for template variant', function () {
    $variants = [
        ['path_segment' => '__any__', 'data' => 'v1'],
    ];

    $conditionals = resolver()->buildPathConditionals(
        $variants,
        fn ($item) => ['status_code' => 200, 'content_type' => 'application/json', 'response_body' => null],
    );

    expect($conditionals)->toHaveCount(1)
        ->and($conditionals[0]['condition_operator'])->toBe('not_equals')
        ->and($conditionals[0]['condition_value'])->toBe('');
});

test('respects start priority for path conditionals', function () {
    $variants = [
        ['path_segment' => '1', 'data' => 'a'],
        ['path_segment' => '2', 'data' => 'b'],
    ];

    $conditionals = resolver()->buildPathConditionals(
        $variants,
        fn ($item) => ['status_code' => 200, 'content_type' => 'application/json', 'response_body' => null],
        startPriority: 3,
    );

    expect($conditionals[0]['priority'])->toBe(3)
        ->and($conditionals[1]['priority'])->toBe(4);
});
