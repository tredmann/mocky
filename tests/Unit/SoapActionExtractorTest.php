<?php

use App\Services\SoapActionExtractor;
use Illuminate\Http\Request;

function extractor(): SoapActionExtractor
{
    return new SoapActionExtractor;
}

test('extracts SOAPAction header (SOAP 1.1) with quotes', function () {
    $request = Request::create('/soap/col/ep', 'POST', [], [], [], [
        'HTTP_SOAPACTION' => '"urn:example#GetUser"',
    ]);

    expect(extractor()->extract($request))->toBe('urn:example#GetUser');
});

test('extracts SOAPAction header without quotes', function () {
    $request = Request::create('/soap/col/ep', 'POST', [], [], [], [
        'HTTP_SOAPACTION' => 'urn:example#GetUser',
    ]);

    expect(extractor()->extract($request))->toBe('urn:example#GetUser');
});

test('extracts action from Content-Type (SOAP 1.2)', function () {
    $request = Request::create('/soap/col/ep', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/soap+xml; charset=utf-8; action="urn:example#GetOrder"',
    ]);

    expect(extractor()->extract($request))->toBe('urn:example#GetOrder');
});

test('returns null when neither SOAPAction nor action param present', function () {
    $request = Request::create('/soap/col/ep', 'POST', [], [], [], [
        'HTTP_CONTENT_TYPE' => 'text/xml',
    ]);

    expect(extractor()->extract($request))->toBeNull();
});

test('returns null for empty SOAPAction header', function () {
    $request = Request::create('/soap/col/ep', 'POST', [], [], [], [
        'HTTP_SOAPACTION' => '',
    ]);

    expect(extractor()->extract($request))->toBeNull();
});

test('prefers SOAPAction header over Content-Type action param', function () {
    $request = Request::create('/soap/col/ep', 'POST', [], [], [], [
        'HTTP_SOAPACTION' => '"urn:header#Action"',
        'CONTENT_TYPE' => 'application/soap+xml; action="urn:contenttype#Action"',
    ]);

    expect(extractor()->extract($request))->toBe('urn:header#Action');
});
