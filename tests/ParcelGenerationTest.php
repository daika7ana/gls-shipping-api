<?php

namespace GLS\Tests;

use GLS\Form\ParcelGeneration;
use GLS\Form\ParcelService;
use PHPUnit\Framework\TestCase;

class ParcelGenerationTest extends TestCase
{
    private function minimal(): ParcelGeneration
    {
        return (new ParcelGeneration())
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

    public function testPayloadKeepsTheGlsFieldOrder(): void
    {
        self::assertSame([
            'username', 'password', 'senderid', 'sender_name', 'sender_address',
            'sender_city', 'sender_zipcode', 'sender_country', 'sender_contact',
            'sender_phone', 'sender_email', 'consig_name', 'consig_address',
            'consig_city', 'consig_zipcode', 'consig_country', 'consig_contact',
            'consig_phone', 'consig_email', 'pcount', 'pickupdate', 'content',
            'clientref', 'codamount', 'codref', 'printertemplate', 'printit',
            'timestamp', 'hash',
        ], array_keys($this->minimal()->toArray()));
    }

    /**
     * The hash is the concatenation of the payload in that order. Pinned here
     * because any change to the field order or the skip list silently breaks
     * every label request.
     */
    public function testHashIsStable(): void
    {
        self::assertSame(
            '2e85f7508cb0837670b1d855d8c88a622262468b',
            $this->minimal()->toArray()['hash'],
        );
    }

    public function testHashIgnoresServices(): void
    {
        $withServices = $this->minimal()->setServices([
            (new ParcelService())->setCode(ParcelService::T12)->setInfo(''),
        ]);

        self::assertSame(
            $this->minimal()->toArray()['hash'],
            $withServices->toArray()['hash'],
        );
    }

    public function testServicesAreOmittedWhenEmpty(): void
    {
        $payload = $this->minimal()->toArray();

        self::assertArrayNotHasKey('services', $payload);
    }

    public function testServicesAreSentWhenSet(): void
    {
        $payload = $this->minimal()
            ->setServices([(new ParcelService())->setCode(ParcelService::T12)->setInfo('')])
            ->toArray();

        self::assertSame([['code' => 'T12', 'info' => '']], $payload['services']);
    }

    public function testPrintitIsAlwaysTrue(): void
    {
        self::assertTrue($this->minimal()->toArray()['printit']);
    }

    public function testSetParcelAttributesSetsSeveralFields(): void
    {
        $payload = (new ParcelGeneration())
            ->setParcelAttributes([
                'username' => 'u',
                'content' => 'bulk',
                'not_a_field' => 'ignored',
            ])
            ->toArray();

        self::assertSame('u', $payload['username']);
        self::assertSame('bulk', $payload['content']);
        self::assertArrayNotHasKey('not_a_field', $payload);
    }

    public function testSetParcelAttributesLeavesATypoNamesNullSoValidationFails(): void
    {
        $form = (new ParcelGeneration())->setParcelAttributes([
            'username' => 'u',
            'consig_nmae' => 'Ion Popescu',
        ]);

        $this->expectException(\GLS\Exception\Validation::class);

        $form->validate();
    }

    public function testValidationRejectsMissingFields(): void
    {
        $this->expectException(\GLS\Exception\Validation::class);

        (new ParcelGeneration())->validate();
    }

    public function testValidationRejectsOutOfRangeCodAmount(): void
    {
        $this->expectException(\GLS\Exception\Validation::class);

        $this->minimal()->setCodamount(100000)->validate();
    }

    public function testValidationAcceptsCodAmountAtTheLimit(): void
    {
        $this->minimal()->setCodamount(99999)->validate();

        self::assertTrue(true);
    }

    public function testZipcodeMustBeDigits(): void
    {
        $this->expectException(\GLS\Exception\Validation::class);

        $this->minimal()->setConsigZipcode('40 0001')->validate();
    }

    public function testPcountIsCappedAt25(): void
    {
        $this->expectException(\GLS\Exception\Validation::class);

        $this->minimal()->setPcount(26)->validate();
    }

    public function testInsServiceRequiresInfo(): void
    {
        $this->expectException(\GLS\Exception\Validation::class);
        $this->expectExceptionMessage('Service INS requires the insured value in info.');

        $this->minimal()->setServices([(new ParcelService())->setCode(ParcelService::INS)])->validate();
    }

    public function testInsServiceAcceptsInfo(): void
    {
        $this->minimal()
            ->setServices([(new ParcelService())->setCode(ParcelService::INS)->setInfo('1000')])
            ->validate();

        self::assertTrue(true);
    }

    public function testValidationExposesStructuredViolations(): void
    {
        try {
            (new ParcelGeneration())->validate();
        } catch (\GLS\Exception\Validation $e) {
            self::assertNotNull($e->getViolations());
            self::assertGreaterThan(0, $e->getViolations()->count());

            return;
        }

        self::fail('No exception thrown');
    }
}
