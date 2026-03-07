<?php

use App\Rules\ValidResponseSyntax;
use Illuminate\Support\Facades\Validator;

function validate(string $body, string $contentType, string $contentTypeField = 'content_type'): bool
{
    return Validator::make(
        ['response_body' => $body, $contentTypeField => $contentType],
        ['response_body' => ['nullable', 'string', new ValidResponseSyntax($contentTypeField)]],
    )->passes();
}

// JSON validation

test('valid JSON passes', function () {
    expect(validate('{"message": "ok"}', 'application/json'))->toBeTrue();
});

test('valid JSON array passes', function () {
    expect(validate('[1, 2, 3]', 'application/json'))->toBeTrue();
});

test('invalid JSON fails', function () {
    expect(validate('{invalid}', 'application/json'))->toBeFalse();
});

test('trailing comma JSON fails', function () {
    expect(validate('{"a": 1,}', 'application/json'))->toBeFalse();
});

test('empty string passes for JSON', function () {
    expect(validate('', 'application/json'))->toBeTrue();
});

test('null body passes for JSON', function () {
    $validator = Validator::make(
        ['response_body' => null, 'content_type' => 'application/json'],
        ['response_body' => ['nullable', 'string', new ValidResponseSyntax]],
    );

    expect($validator->passes())->toBeTrue();
});

// XML validation

test('valid XML passes', function () {
    expect(validate('<root><item>hello</item></root>', 'application/xml'))->toBeTrue();
});

test('invalid XML fails', function () {
    expect(validate('<root><unclosed>', 'application/xml'))->toBeFalse();
});

test('empty string passes for XML', function () {
    expect(validate('', 'application/xml'))->toBeTrue();
});

// HTML and plain text are not validated

test('any content passes for text/html', function () {
    expect(validate('<div>not closed', 'text/html'))->toBeTrue();
});

test('any content passes for text/plain', function () {
    expect(validate('{invalid json}', 'text/plain'))->toBeTrue();
});

// Custom content type field

test('uses custom content type field', function () {
    $validator = Validator::make(
        ['response_body' => '{invalid}', 'cr_content_type' => 'application/json'],
        ['response_body' => ['nullable', 'string', new ValidResponseSyntax('cr_content_type')]],
    );

    expect($validator->passes())->toBeFalse();
});

// Error messages

test('JSON error message includes details', function () {
    $validator = Validator::make(
        ['response_body' => '{bad}', 'content_type' => 'application/json'],
        ['response_body' => ['nullable', 'string', new ValidResponseSyntax]],
    );

    expect($validator->errors()->first('response_body'))->toContain('invalid JSON');
});

test('XML error message includes details', function () {
    $validator = Validator::make(
        ['response_body' => '<unclosed>', 'content_type' => 'application/xml'],
        ['response_body' => ['nullable', 'string', new ValidResponseSyntax]],
    );

    expect($validator->errors()->first('response_body'))->toContain('invalid XML');
});
