<?php

use App\Services\SoapBodyParser;

function parser(): SoapBodyParser
{
    return new SoapBodyParser;
}

function soapEnvelope(string $body): string
{
    return <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            {$body}
          </soap:Body>
        </soap:Envelope>
        XML;
}

test('extracts simple element value', function () {
    $xml = soapEnvelope('<GetUser><userId>42</userId></GetUser>');

    expect(parser()->extract($xml, 'GetUser.userId'))->toBe('42');
});

test('extracts operation-level match (single segment)', function () {
    $xml = soapEnvelope('<Ping/>');

    // Single-segment path matching the operation element with no text returns null (empty)
    expect(parser()->extract($xml, 'Ping'))->toBeNull();
});

test('returns null when path does not exist', function () {
    $xml = soapEnvelope('<GetUser><userId>1</userId></GetUser>');

    expect(parser()->extract($xml, 'GetUser.nonExistent'))->toBeNull();
});

test('returns null when operation name does not match', function () {
    $xml = soapEnvelope('<GetUser><userId>1</userId></GetUser>');

    expect(parser()->extract($xml, 'GetOrder.id'))->toBeNull();
});

test('returns null on malformed XML', function () {
    expect(parser()->extract('<not valid xml', 'GetUser.userId'))->toBeNull();
});

test('returns null on empty input', function () {
    expect(parser()->extract('', 'GetUser.userId'))->toBeNull();
});

test('handles namespace prefix on envelope', function () {
    $xml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <env:Envelope xmlns:env="http://www.w3.org/2003/05/soap-envelope">
          <env:Body>
            <GetUser>
              <userId>99</userId>
            </GetUser>
          </env:Body>
        </env:Envelope>
        XML;

    expect(parser()->extract($xml, 'GetUser.userId'))->toBe('99');
});

test('handles namespace prefix on operation element', function () {
    $xml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
                       xmlns:tns="urn:example">
          <soap:Body>
            <tns:GetUser>
              <tns:userId>7</tns:userId>
            </tns:GetUser>
          </soap:Body>
        </soap:Envelope>
        XML;

    expect(parser()->extract($xml, 'GetUser.userId'))->toBe('7');
});
