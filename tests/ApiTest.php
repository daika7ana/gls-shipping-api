<?php

namespace GLS\Tests;

use GLS\Exception\ParcelGeneration;
use GLS\Exception\Response;
use GLS\Tests\Support\FakeAPI;
use PHPUnit\Framework\TestCase;

class ApiTest extends TestCase
{
    private function fixture(string $name): string
    {
        return file_get_contents(__DIR__ . '/fixtures/' . $name);
    }

    private function fake(string $country = 'RO'): FakeAPI
    {
        return new FakeAPI($country);
    }

    public function testEveryCountryUsesHttps(): void
    {
        foreach (['HU', 'SK', 'CZ', 'RO', 'SI', 'HR'] as $country) {
            $api = $this->fake($country);

            self::assertStringStartsWith('https://', $api->apiUrl(), $country);
            self::assertStringStartsWith('https://', $api->trackingUrl('1'), $country);
        }
    }

    public function testUnknownCountryThrowsTheDocumentedException(): void
    {
        $this->expectException(ParcelGeneration::class);

        $this->fake('XX')->apiUrl();
    }

    public function testTrackingPostsTheSearchForm(): void
    {
        $api = $this->fake('RO');
        $api->responseBody = $this->fixture('tracking.html');

        $api->getParcelStatus('1234567890');

        self::assertSame('POST', $api->requests[0]['method']);
        self::assertStringEndsWith('/tt_page.php', $api->requests[0]['url']);
        self::assertSame(
            ['page' => 'tt_page.php', 'tt_value' => '1234567890'],
            $api->requests[0]['data'],
        );
    }

    public function testTrackingUsesTheConfiguredCountry(): void
    {
        $api = $this->fake('HU');
        $api->responseBody = $this->fixture('tracking.html');

        $api->getParcelStatus('1');

        self::assertStringStartsWith('https://online.gls-hungary.com/', $api->requests[0]['url']);
    }

    public function testTrackingReturnsTheFirstRow(): void
    {
        // Synthetic: the shape of a result table. Not capturable from the demo
        // account, whose labels never reach the network and so never get rows.
        $api = $this->fake();
        $api->responseBody = $this->fixture('tracking.html');

        self::assertSame([
            'date' => '06.11.2026 10:15',
            'status' => 'Livrat',
            'depot' => 'Cluj',
            'info' => 'Livrat destinatarului',
        ], $api->getParcelStatus('1'));
    }

    public function testTrackingThrowsWhenTheParcelIsUnknown(): void
    {
        $api = $this->fake();
        $api->responseBody = $this->fixture('tracking-not-found.html');

        $this->expectException(\GLS\Exception::class);
        $this->expectExceptionMessage('No tracking status for this code');

        $api->getParcelStatus('1');
    }

    public function testTrackingUrlEncodesTheParcelNumber(): void
    {
        self::assertStringEndsWith(
            'tt_value=1%26x%3D2',
            $this->fake()->trackingUrl('1&x=2'),
        );
    }

    public function testRejectedLoginIsZero(): void
    {
        $api = $this->fake();
        $api->responseBody = $this->fixture('login-rejected.html');

        self::assertSame(['success' => 0], $api->validateGLSAccount(['username' => 'u', 'password' => 'p']));
    }

    public function testSuccessfulLoginIsOne(): void
    {
        $api = $this->fake();
        $api->responseBody = $this->fixture('login-success.html');

        self::assertSame(['success' => 1], $api->validateGLSAccount(['username' => 'u', 'password' => 'p']));
    }

    public function testUnrecognisedLoginPageIsNotTreatedAsSuccess(): void
    {
        $api = $this->fake();
        $api->responseBody = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN"> ';

        $this->expectException(Response::class);

        $api->validateGLSAccount(['username' => 'u', 'password' => 'p']);
    }

    public function testGenerateParcelReportsTheSoapFault(): void
    {
        $api = $this->fake();
        $api->soapResponse = ['faultcode' => '500', 'faultstring' => 'Invalid username'];

        try {
            $api->generateParcel($this->form());
        } catch (ParcelGeneration $e) {
            self::assertStringContainsString('500: Invalid username', $e->getMessage());

            return;
        }

        self::fail('No exception thrown');
    }

    public function testGenerateParcelReportsAnUnknownError(): void
    {
        $api = $this->fake();
        $api->soapResponse = [];

        try {
            $api->generateParcel($this->form());
        } catch (ParcelGeneration $e) {
            self::assertStringContainsString('Unknown error', $e->getMessage());

            return;
        }

        self::fail('No exception thrown');
    }

    public function testGenerateParcelWrapsAScalarParcelNumber(): void
    {
        $api = $this->fake();
        $api->soapResponse = ['successfull' => 1, 'pcls' => '12345678901'];

        self::assertSame('12345678901', $api->generateParcel($this->form())['tracking_code']);
    }

    public function testDeleteParcelThrowsWhenGlsRefuses(): void
    {
        $api = $this->fake();
        $api->soapResponse = ['errcode' => 41, 'errdesc' => 'Invalid parcel number'];

        try {
            $api->deleteParcel([1], ['username' => 'u', 'password' => 'p', 'senderid' => '1']);
        } catch (ParcelGeneration $e) {
            self::assertStringContainsString('41: Invalid parcel number', $e->getMessage());

            return;
        }

        self::fail('No exception thrown');
    }

    public function testDeleteParcelReturnsTheResult(): void
    {
        $api = $this->fake();
        $api->soapResponse = ['pcls' => ['1'], 'successfull' => true, 'errcode' => 0];

        self::assertSame(
            ['pcls' => ['1'], 'successfull' => true, 'errcode' => 0],
            $api->deleteParcel([1], ['username' => 'u', 'password' => 'p', 'senderid' => '1']),
        );
    }

    public function testModifyCodRequiresAnEmail(): void
    {
        $this->expectException(\GLS\Exception::class);

        $this->fake()->modifyCod([
            'username' => 'u', 'password' => 'p', 'senderid' => '1',
            'pclid' => '1', 'codamount' => 10,
        ]);
    }

    public function testModifyCodSurfacesTheServerError(): void
    {
        $api = $this->fake();
        $api->soapResponse = ['errcode' => 46, 'errdesc' => 'Process failed:Invalid client number!'];

        try {
            $api->modifyCod([
                'username' => 'u', 'password' => 'p', 'senderid' => '1',
                'pclid' => '1', 'codamount' => 10, 'email' => 'test@example.com',
            ]);
        } catch (ParcelGeneration $e) {
            self::assertStringContainsString('46: Process failed:Invalid client number!', $e->getMessage());

            return;
        }

        self::fail('No exception thrown');
    }

    public function testSetHostChangesEveryEndpoint(): void
    {
        $api = $this->fake('RO')->setHost('https://stub.test/');

        self::assertSame('https://stub.test/webservices/soap_server.php?wsdl', $api->apiUrl());
        self::assertStringStartsWith('https://stub.test/', $api->trackingUrl('1'));
    }

    public function testInjectedHttpClientIsUsed(): void
    {
        $api = (new \GLS\API('RO'))->setHttpClient(new \GuzzleHttp\Client([
            'handler' => \GuzzleHttp\HandlerStack::create(new \GuzzleHttp\Handler\MockHandler([
                new \GuzzleHttp\Psr7\Response(200, [], $this->fixture('login-success.html')),
            ])),
        ]));

        self::assertSame(['success' => 1], $api->validateGLSAccount(['username' => 'demo', 'password' => 'x']));
    }

    public function testParcelPdfEscapesParcelIds(): void
    {
        $api = $this->fake();
        $api->soapResponse = gzencode('<DTU/>');

        $api->getParcelPdf(['1</long><long>999'], [
            'username' => 'u',
            'password' => 'p',
            'senderid' => '1',
            'printertemplate' => 'A6',
        ]);

        $sent = gzdecode(base64_decode($api->requests[0]['data']['data']));

        self::assertStringContainsString('<long>1&lt;/long&gt;&lt;long&gt;999</long>', $sent);
    }

    public function testParcelPdfThrowsOnAnUndecodableResponse(): void
    {
        $api = $this->fake();
        $api->soapResponse = false;

        $this->expectException(ParcelGeneration::class);

        $api->getParcelPdf([1], [
            'username' => 'u',
            'password' => 'p',
            'senderid' => '1',
            'printertemplate' => 'A6',
        ]);
    }

    private function form(): \GLS\Form\ParcelGeneration
    {
        return (new \GLS\Form\ParcelGeneration())
            ->setUsername('u')
            ->setPassword('p')
            ->setSenderid('1')
            ->setSenderName('n')
            ->setSenderAddress('a')
            ->setSenderCity('c')
            ->setSenderZipcode('400001')
            ->setSenderCountry('RO')
            ->setConsigName('n2')
            ->setConsigAddress('a2')
            ->setConsigCity('c2')
            ->setConsigZipcode('400002')
            ->setConsigCountry('RO')
            ->setPcount(1)
            ->setPickupdate('2026-11-07');
    }
}
