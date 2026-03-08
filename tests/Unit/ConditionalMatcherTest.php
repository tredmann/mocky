<?php

use App\Enums\ConditionOperator;
use App\Enums\ConditionSource;
use App\Models\ConditionalResponse;
use App\Services\ConditionalMatcher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

function matcher(): ConditionalMatcher
{
    return new ConditionalMatcher;
}

function makeConditional(array $attributes): ConditionalResponse
{
    $cr = new ConditionalResponse;
    $cr->condition_source = $attributes['condition_source'];
    $cr->condition_field = $attributes['condition_field'];
    $cr->condition_operator = $attributes['condition_operator'];
    $cr->condition_value = $attributes['condition_value'];
    $cr->status_code = $attributes['status_code'] ?? 200;
    $cr->response_body = $attributes['response_body'] ?? null;
    $cr->content_type = $attributes['content_type'] ?? 'application/json';

    return $cr;
}

function makeCollection(array $conditionals): Collection
{
    return new Collection($conditionals);
}

test('returns null when no conditionals match', function () {
    $request = Request::create('/mock/col/ep', 'GET', ['foo' => 'bar']);
    $conditionals = makeCollection([
        makeConditional([
            'condition_source' => ConditionSource::Query,
            'condition_field' => 'foo',
            'condition_operator' => ConditionOperator::Equals,
            'condition_value' => 'baz',
            'status_code' => 200,
        ]),
    ]);

    expect(matcher()->match($conditionals, $request, []))->toBeNull();
});

test('returns null for an empty collection', function () {
    $request = Request::create('/mock/col/ep', 'GET');

    expect(matcher()->match(makeCollection([]), $request, []))->toBeNull();
});

test('returns the first matching conditional', function () {
    $request = Request::create('/mock/col/ep', 'GET', ['status' => 'active']);

    $first = makeConditional([
        'condition_source' => ConditionSource::Query,
        'condition_field' => 'status',
        'condition_operator' => ConditionOperator::Equals,
        'condition_value' => 'active',
        'status_code' => 200,
        'response_body' => '{"match":"first"}',
    ]);

    $second = makeConditional([
        'condition_source' => ConditionSource::Query,
        'condition_field' => 'status',
        'condition_operator' => ConditionOperator::Equals,
        'condition_value' => 'active',
        'status_code' => 201,
        'response_body' => '{"match":"second"}',
    ]);

    $matched = matcher()->match(makeCollection([$first, $second]), $request, []);

    expect($matched)->toBe($first);
});

test('matches on query parameter', function () {
    $request = Request::create('/mock/col/ep', 'GET', ['key' => 'val']);
    $conditional = makeConditional([
        'condition_source' => ConditionSource::Query,
        'condition_field' => 'key',
        'condition_operator' => ConditionOperator::Equals,
        'condition_value' => 'val',
        'status_code' => 200,
    ]);

    expect(matcher()->match(makeCollection([$conditional]), $request, []))->toBe($conditional);
});

test('matches on path segment', function () {
    $request = Request::create('/mock/col/ep/42', 'GET');
    $conditional = makeConditional([
        'condition_source' => ConditionSource::Path,
        'condition_field' => '0',
        'condition_operator' => ConditionOperator::Equals,
        'condition_value' => '42',
        'status_code' => 200,
    ]);

    expect(matcher()->match(makeCollection([$conditional]), $request, ['42']))->toBe($conditional);
});
