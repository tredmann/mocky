<?php

use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function wsdlContent(): string
{
    return <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <wsdl:definitions
            name="TestService"
            xmlns:wsdl="http://schemas.xmlsoap.org/wsdl/"
            xmlns:soap="http://schemas.xmlsoap.org/wsdl/soap/">
          <wsdl:binding name="TestServiceBinding" type="tns:TestServicePortType">
            <soap:binding style="document" transport="http://schemas.xmlsoap.org/soap/http"/>
            <wsdl:operation name="Ping">
              <soap:operation soapAction="urn:Ping"/>
            </wsdl:operation>
          </wsdl:binding>
          <wsdl:service name="TestService">
            <wsdl:port name="TestServicePort" binding="tns:TestServiceBinding">
              <soap:address location="http://example.com/service"/>
            </wsdl:port>
          </wsdl:service>
        </wsdl:definitions>
        XML;
}

test('imports WSDL file and outputs success message', function () {
    $user = User::factory()->create();
    $file = tempnam(sys_get_temp_dir(), 'wsdl_').'.wsdl';
    file_put_contents($file, wsdlContent());

    $this->artisan('wsdl:import', ['file' => $file, '--user' => $user->email])
        ->assertExitCode(0)
        ->expectsOutputToContain('TestService');

    unlink($file);
});

test('fails when --user is missing', function () {
    $file = tempnam(sys_get_temp_dir(), 'wsdl_').'.wsdl';
    file_put_contents($file, wsdlContent());

    $this->artisan('wsdl:import', ['file' => $file])
        ->assertExitCode(1);

    unlink($file);
});

test('fails when file does not exist', function () {
    $user = User::factory()->create();

    $this->artisan('wsdl:import', ['file' => '/nonexistent/path.wsdl', '--user' => $user->email])
        ->assertExitCode(1);
});

test('fails when user is not found', function () {
    $file = tempnam(sys_get_temp_dir(), 'wsdl_').'.wsdl';
    file_put_contents($file, wsdlContent());

    $this->artisan('wsdl:import', ['file' => $file, '--user' => 'nonexistent@example.com'])
        ->assertExitCode(1);

    unlink($file);
});
