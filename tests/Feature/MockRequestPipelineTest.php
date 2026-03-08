<?php

use App\Exceptions\EndpointNotFoundException;
use App\Exceptions\MethodNotAllowedException;
use App\Models\ConditionalResponse;
use App\Models\Endpoint;
use App\Services\ConditionalMatcher;
use App\Services\EndpointResolver;
use App\Services\MockRequestLogger;
use App\Services\MockRequestPipeline;
use Illuminate\Http\Request;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function pipelineWith(EndpointResolver $resolver, ConditionalMatcher $matcher, MockRequestLogger $logger): MockRequestPipeline
{
    return new MockRequestPipeline($resolver, $matcher, $logger);
}

function resolvedEndpoint(array $attributes = []): Endpoint
{
    $endpoint = Endpoint::factory()->create($attributes);
    $endpoint->load('collection');

    return $endpoint;
}

function stubMatcher(?ConditionalResponse $returns = null): ConditionalMatcher
{
    $matcher = Mockery::mock(ConditionalMatcher::class);
    $matcher->allows('match')->andReturn($returns);

    return $matcher;
}

function stubLogger(): MockRequestLogger
{
    $logger = Mockery::mock(MockRequestLogger::class);
    $logger->allows('log');

    return $logger;
}

// --- 404 / 405 ---

test('returns 404 when endpoint is not found', function () {
    $resolver = Mockery::mock(EndpointResolver::class);
    $resolver->expects('resolve')->andThrow(new EndpointNotFoundException);

    $request = Request::create('/mock/col/ep', 'GET');
    $response = pipelineWith($resolver, stubMatcher(), stubLogger())->handle($request, 'col', 'ep', '');

    expect($response->getStatusCode())->toBe(404);
});

test('returns 405 with Allow header when method is not allowed', function () {
    $resolver = Mockery::mock(EndpointResolver::class);
    $resolver->expects('resolve')->andThrow(new MethodNotAllowedException('GET, POST'));

    $request = Request::create('/mock/col/ep', 'DELETE');
    $response = pipelineWith($resolver, stubMatcher(), stubLogger())->handle($request, 'col', 'ep', '');

    expect($response->getStatusCode())->toBe(405)
        ->and($response->headers->get('Allow'))->toBe('GET, POST');
});

// --- Default response ---

test('returns endpoint default response when no conditional matches', function () {
    $endpoint = resolvedEndpoint(['status_code' => 418, 'content_type' => 'text/plain', 'response_body' => 'teapot']);

    $resolver = Mockery::mock(EndpointResolver::class);
    $resolver->allows('resolve')->andReturn($endpoint);

    $request = Request::create('/mock/col/ep', 'GET');
    $response = pipelineWith($resolver, stubMatcher(null), stubLogger())->handle($request, 'col', 'ep', '');

    expect($response->getStatusCode())->toBe(418)
        ->and($response->headers->get('Content-Type'))->toBe('text/plain')
        ->and($response->getContent())->toBe('teapot');
});

test('returns empty string body when endpoint response body is null', function () {
    $endpoint = resolvedEndpoint(['response_body' => null, 'status_code' => 204]);

    $resolver = Mockery::mock(EndpointResolver::class);
    $resolver->allows('resolve')->andReturn($endpoint);

    $request = Request::create('/mock/col/ep', 'GET');
    $response = pipelineWith($resolver, stubMatcher(null), stubLogger())->handle($request, 'col', 'ep', '');

    expect($response->getContent())->toBe('');
});

// --- Conditional response ---

test('returns matched conditional status and content type', function () {
    $endpoint = resolvedEndpoint(['status_code' => 200, 'content_type' => 'application/json', 'response_body' => 'default']);
    $matched = ConditionalResponse::factory()->create([
        'endpoint_id' => $endpoint->id,
        'status_code' => 201,
        'content_type' => 'text/plain',
        'response_body' => 'conditional',
    ]);

    $resolver = Mockery::mock(EndpointResolver::class);
    $resolver->allows('resolve')->andReturn($endpoint);

    $request = Request::create('/mock/col/ep', 'GET');
    $response = pipelineWith($resolver, stubMatcher($matched), stubLogger())->handle($request, 'col', 'ep', '');

    expect($response->getStatusCode())->toBe(201)
        ->and($response->headers->get('Content-Type'))->toBe('text/plain')
        ->and($response->getContent())->toBe('conditional');
});

test('falls back to endpoint body when matched conditional body is null', function () {
    $endpoint = resolvedEndpoint(['response_body' => 'default-body', 'status_code' => 200]);
    $matched = ConditionalResponse::factory()->create([
        'endpoint_id' => $endpoint->id,
        'status_code' => 202,
        'response_body' => null,
    ]);

    $resolver = Mockery::mock(EndpointResolver::class);
    $resolver->allows('resolve')->andReturn($endpoint);

    $request = Request::create('/mock/col/ep', 'GET');
    $response = pipelineWith($resolver, stubMatcher($matched), stubLogger())->handle($request, 'col', 'ep', '');

    expect($response->getContent())->toBe('default-body')
        ->and($response->getStatusCode())->toBe(202);
});

// --- Path segments ---

test('passes split path segments to the matcher', function () {
    $endpoint = resolvedEndpoint();

    $resolver = Mockery::mock(EndpointResolver::class);
    $resolver->allows('resolve')->andReturn($endpoint);

    $matcher = Mockery::mock(ConditionalMatcher::class);
    $matcher->expects('match')
        ->withArgs(fn ($coll, $req, $segments) => $segments === ['users', '42'])
        ->andReturn(null);

    $request = Request::create('/mock/col/ep/users/42', 'GET');
    pipelineWith($resolver, $matcher, stubLogger())->handle($request, 'col', 'ep', 'users/42');
});

test('passes empty array when path is empty', function () {
    $endpoint = resolvedEndpoint();

    $resolver = Mockery::mock(EndpointResolver::class);
    $resolver->allows('resolve')->andReturn($endpoint);

    $matcher = Mockery::mock(ConditionalMatcher::class);
    $matcher->expects('match')
        ->withArgs(fn ($coll, $req, $segments) => $segments === [])
        ->andReturn(null);

    $request = Request::create('/mock/col/ep', 'GET');
    pipelineWith($resolver, $matcher, stubLogger())->handle($request, 'col', 'ep', '');
});

// --- Logging ---

test('logs every resolved request', function () {
    $endpoint = resolvedEndpoint();

    $resolver = Mockery::mock(EndpointResolver::class);
    $resolver->allows('resolve')->andReturn($endpoint);

    $logger = Mockery::mock(MockRequestLogger::class);
    $logger->expects('log')->once();

    $request = Request::create('/mock/col/ep', 'GET');
    pipelineWith($resolver, stubMatcher(null), $logger)->handle($request, 'col', 'ep', '');
});

test('passes matched conditional to logger', function () {
    $endpoint = resolvedEndpoint();
    $matched = ConditionalResponse::factory()->create(['endpoint_id' => $endpoint->id]);

    $resolver = Mockery::mock(EndpointResolver::class);
    $resolver->allows('resolve')->andReturn($endpoint);

    $logger = Mockery::mock(MockRequestLogger::class);
    $logger->expects('log')
        ->withArgs(fn ($req, $ep, $cr) => $cr === $matched)
        ->once();

    $request = Request::create('/mock/col/ep', 'GET');
    pipelineWith($resolver, stubMatcher($matched), $logger)->handle($request, 'col', 'ep', '');
});

test('does not log when endpoint resolution fails', function () {
    $resolver = Mockery::mock(EndpointResolver::class);
    $resolver->expects('resolve')->andThrow(new EndpointNotFoundException);

    $logger = Mockery::mock(MockRequestLogger::class);
    $logger->expects('log')->never();

    $request = Request::create('/mock/col/ep', 'GET');
    pipelineWith($resolver, stubMatcher(), $logger)->handle($request, 'col', 'ep', '');
});
