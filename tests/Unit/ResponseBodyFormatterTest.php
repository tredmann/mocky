<?php

use App\Services\ResponseBodyFormatter;

function formatter(): ResponseBodyFormatter
{
    return new ResponseBodyFormatter;
}

test('returns null unchanged', function () {
    expect(formatter()->format('application/json', null))->toBeNull();
});

test('returns empty string unchanged', function () {
    expect(formatter()->format('application/json', ''))->toBe('');
});

test('pretty-prints valid json for json content type', function () {
    $input = '{"a":1,"b":2}';
    $output = formatter()->format('application/json', $input);

    expect($output)->toBe(json_encode(json_decode($input), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
});

test('does not escape forward slashes in json', function () {
    $input = '{"url":"http://example.com/path"}';
    $output = formatter()->format('application/json', $input);

    expect($output)->toContain('http://example.com/path')
        ->not->toContain('http:\/\/');
});

test('does not escape unicode in json', function () {
    $input = '{"emoji":"\u00e9"}';
    $output = formatter()->format('application/json', $input);

    expect($output)->toContain('é');
});

test('returns invalid json as-is for json content type', function () {
    $input = 'not valid json {';
    $output = formatter()->format('application/json', $input);

    expect($output)->toBe($input);
});

test('returns body unchanged for non-json content type', function () {
    $input = '<html><body>hello</body></html>';
    $output = formatter()->format('text/html', $input);

    expect($output)->toBe($input);
});

test('returns body unchanged for plain text content type', function () {
    $input = 'just some text';
    $output = formatter()->format('text/plain', $input);

    expect($output)->toBe($input);
});

test('formats json when content type contains json as substring', function () {
    $input = '{"key":"value"}';
    $output = formatter()->format('application/vnd.api+json', $input);

    expect($output)->toBe(json_encode(json_decode($input), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
});
