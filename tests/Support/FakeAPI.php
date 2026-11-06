<?php

namespace GLS\Tests\Support;

use GLS\API;
use GLS\Form;

/**
 * Replaces the two network boundaries so the API logic can be tested offline.
 */
class FakeAPI extends API
{
    /** @var array<int, array<string, mixed>> */
    public array $requests = [];

    public string $responseBody = '';

    public mixed $soapResponse = null;

    public function host(): string
    {
        return $this->getHost();
    }

    public function apiUrl(): string
    {
        return $this->getApiUrl();
    }

    public function trackingUrl(string $parcelNumber): string
    {
        return $this->getTrackingUrl($parcelNumber);
    }

    protected function request(string $url, array|Form $data = [], string $method = 'GET'): string
    {
        $this->requests[] = ['url' => $url, 'data' => $data, 'method' => $method];

        return $this->responseBody;
    }

    protected function requestNuSOAP(string $method, array|Form $data = []): mixed
    {
        $this->requests[] = ['method' => $method, 'data' => $data];

        return $this->soapResponse;
    }
}
