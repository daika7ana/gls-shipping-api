<?php

namespace GLS;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use nusoap_client;
use SimpleXMLElement;
use Symfony\Component\DomCrawler\Crawler;

class API
{
    protected string $countryCode = '';

    /**
     * Base URL per country. HTTPS only: the credentials travel in the body.
     */
    protected array $hosts = [
        'HU' => 'https://online.gls-hungary.com',
        'SK' => 'https://online.gls-slovakia.sk',
        'CZ' => 'https://online.gls-czech.com',
        'RO' => 'https://online.gls-romania.ro',
        'SI' => 'https://connect.gls-slovenia.com',
        'HR' => 'https://online.gls-croatia.com',
    ];

    protected ?ClientInterface $http = null;

    protected ?nusoap_client $soap = null;

    /**
     * @param string $countryCode HU/SK/CZ/RO/SI/HR
     */
    public function __construct(string $countryCode)
    {
        $this->countryCode = strtoupper(trim($countryCode));
    }

    /**
     * Point the client at another host, e.g. a stub server in tests.
     */
    public function setHost(string $host): static
    {
        $this->hosts[$this->countryCode] = rtrim($host, '/');

        return $this;
    }

    /**
     * Replace the HTTP client used for tracking and login calls.
     */
    public function setHttpClient(ClientInterface $client): static
    {
        $this->http = $client;

        return $this;
    }

    /**
     * Get parcel/s number/s.
     *
     * @throws Exception\ParcelGeneration
     */
    public function generateParcel(Form\ParcelGeneration $form): array
    {
        $form->validate();

        $data = $this->requestNuSOAP('printlabel', $form);

        if (empty($data['successfull'])) {
            throw new Exception\ParcelGeneration('Response with error', $this->describeResponse($data));
        }

        if (empty($data['pcls'])) {
            throw new Exception\ParcelGeneration('No parcels numbers received!');
        }

        $trackingCodes = array_values((array) $data['pcls']);

        return [
            'tracking_code' => 1 == count($trackingCodes) ? $trackingCodes[0] : $trackingCodes,
            'raw_pdf' => !empty($data['pdfdata']) ? $data['pdfdata'] : false,
        ];
    }

    /**
     * Delete parcel/s number/s.
     *
     * @throws Exception\ParcelGeneration
     */
    public function deleteParcel(array $parcel_ids, array $requested_data): array
    {
        $required_keys = ['username', 'password', 'senderid'];
        $missing_keys = array_diff_key(array_flip($required_keys), $requested_data);

        if ($missing_keys) {
            throw new Exception('The provided array has missing keys.');
        }

        $data = $requested_data;
        $data['pclids'] = $parcel_ids;

        $result = $this->requestNuSOAP('deletelabels', $data);

        if (empty($result['successfull'])) {
            throw new Exception\ParcelGeneration('Response with error', $this->describeResponse($result));
        }

        return $result;
    }

    /**
     * Change the cash-on-delivery amount after a label was created, so a wrong
     * amount does not cost a new tracking number.
     *
     * @param array $requested_data must contain ['username', 'password', 'senderid', 'pclid', 'codamount', 'email']
     *
     * @throws Exception\ParcelGeneration
     */
    public function modifyCod(array $requested_data): array
    {
        // email is mandatory: GLS answers 45 "Invalid e-mail address" without it.
        $required_keys = ['username', 'password', 'senderid', 'pclid', 'codamount', 'email'];
        $missing_keys = array_diff_key(array_flip($required_keys), $requested_data);

        if ($missing_keys) {
            throw new Exception('The provided array has missing keys.');
        }

        $result = $this->requestNuSOAP('modifycod', $requested_data);

        if (empty($result['successfull'])) {
            throw new Exception\ParcelGeneration('Response with error', $this->describeResponse($result));
        }

        return $result;
    }

    /**
     * Regenerate PDF based on the already known PclID.
     *
     * @param array $parcel_ids     required for PDF generation
     * @param array $requested_data must contain: ['username', 'password', 'senderid', 'printertemplate']
     *
     * @throws Exception\ParcelGeneration
     */
    public function getParcelPdf(array $parcel_ids, array $requested_data): SimpleXMLElement
    {
        $required_keys = ['username', 'password', 'senderid', 'printertemplate'];
        $missing_keys = array_diff_key(array_flip($required_keys), $requested_data);

        if ($missing_keys) {
            throw new Exception('The provided array has missing keys.');
        }

        $requested_parcel_pdf = "<?xml version='1.0' encoding = 'UTF-8'?><DTU RequestType = 'GlsApiRequest' MethodName='printLabels'><Shipments><Shipment><PclIDs>";
        foreach ($parcel_ids as $id) {
            $requested_parcel_pdf .= '<long>' . htmlspecialchars((string) $id, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</long>';
        }
        $requested_parcel_pdf .= '</PclIDs></Shipment></Shipments></DTU>';

        $requested_data['data'] = base64_encode(gzencode($requested_parcel_pdf, 9));
        $requested_data['is_autoprint_pdfs'] = false;

        $response = $this->requestNuSOAP('getprintedlabels_gzipped_xml', $requested_data);

        $xml = is_string($response) ? @gzdecode($response) : false;
        $element = is_string($xml) ? @simplexml_load_string($xml) : false;

        if ($element === false) {
            throw new Exception\ParcelGeneration('Could not decode the PDF response from GLS.');
        }

        return $element;
    }

    /**
     * Get the first tracking row shown for a parcel.
     *
     * @throws Exception
     */
    public function getParcelStatus(string $tracking_code): array
    {
        // The tracking page only answers a POST of its own search form; a GET
        // with tt_value in the URL returns the empty form.
        $html = $this->request(
            $this->getHost() . '/tt_page.php',
            ['page' => 'tt_page.php', 'tt_value' => $tracking_code],
            'POST',
        );

        $dom = new Crawler($html);
        $row = $dom->filter('table tr.colored_0, table tr.colored_1')->first();

        if (!count($row)) {
            // GLS returns this same page for an unknown code and for a parcel
            // it has not scanned yet, so the message must cover both.
            throw new Exception('No tracking status for this code - GLS returns no rows until the parcel is scanned, or the code is unknown.');
        }

        return array_map('trim', [
            'date' => $row->filter('td')->eq(0)->text(),
            'status' => $row->filter('td')->eq(1)->text(),
            'depot' => $row->filter('td')->eq(2)->text(),
            'info' => $row->filter('td')->eq(3)->text(),
        ]);
    }

    /**
     * URL of the tracking page for a parcel, for display or manual checks.
     * getParcelStatus() does not use it: it POSTs the search form instead.
     */
    public function getTrackingUrl(string $parcelNumber): string
    {
        return $this->getHost() . '/tt_page.php?tt_value=' . rawurlencode($parcelNumber);
    }

    /**
     * @param array $login_data must contain ['username', 'password']
     *
     * @throws Exception
     */
    public function validateGLSAccount(array $login_data): array
    {
        $required_keys = ['username', 'password'];
        $missing_keys = array_diff_key(array_flip($required_keys), $login_data);

        if ($missing_keys) {
            throw new Exception('The provided array has missing keys.');
        }

        $login_data['page'] = 'welcome.php';

        $html = $this->request($this->getHost() . '/login.php', $login_data, 'POST');

        // A rejected login comes back as an inline error page carrying a
        // Content-Type meta tag and an alert(); a successful one answers with
        // a redirect script. Anything else is unknown, and guessing "success"
        // there would accept a wrong password.
        $dom = new Crawler($html);
        $rejected = str_contains($html, 'alert(') || count($dom->filter('meta[http-equiv="Content-Type"]'));

        if ($rejected) {
            return ['success' => 0];
        }

        if (str_contains($html, 'location=') || str_contains($html, '<html')) {
            return ['success' => 1];
        }

        throw new Exception\Response('Unexpected response from the GLS login page.');
    }

    /**
     * @throws Exception\ParcelGeneration
     */
    protected function requestNuSOAP(string $method, array|Form $data = []): mixed
    {
        if ($data instanceof Form) {
            $data = $data->toArray();
        }

        if ($this->soap === null) {
            // Connect timeout 5s; response 30s because printlabel renders a PDF.
            $this->soap = new nusoap_client($this->getApiUrl(), 'wsdl', false, false, false, false, 5, 30);
            $this->soap->soap_defencoding = 'UTF-8';
            $this->soap->decode_utf8 = false;
        }

        ob_start();
        try {
            $result = $this->soap->call($method, $data);
        } finally {
            ob_end_clean();
        }

        // nusoap never throws: transport and WSDL errors end up in getError().
        if ($error = $this->soap->getError()) {
            throw new Exception\ParcelGeneration("GLS request failed: {$error}");
        }

        if ($result === false) {
            throw new Exception\ParcelGeneration('GLS returned no response.');
        }

        return $result;
    }

    /**
     * @throws Exception
     */
    protected function request(string $url, array|Form $data = [], string $method = 'GET'): string
    {
        if ($data instanceof Form) {
            $data = $data->toArray();
        }

        $this->http ??= new Client();

        try {
            $response = $this->http->request($method, $url, [
                'form_params' => $data,
                'timeout' => 10,
                'connect_timeout' => 5,
            ]);
        } catch (GuzzleException $e) {
            throw new Exception\Response("GLS request to {$url} failed: {$e->getMessage()}");
        }

        return $response->getBody()->getContents();
    }

    protected function describeResponse(mixed $data): string
    {
        if (is_array($data)) {
            $code = $data['errcode'] ?? $data['faultcode'] ?? null;
            $desc = $data['errdesc'] ?? $data['faultstring'] ?? null;

            if ($code !== null || $desc !== null) {
                return trim("{$code}: {$desc}", ': ');
            }
        }

        return 'Unknown error - no errcode received';
    }

    /**
     * Get the base URL of the configured country.
     *
     * @throws Exception\ParcelGeneration
     */
    protected function getApiUrl(): string
    {
        return $this->getHost() . '/webservices/soap_server.php?wsdl';
    }

    /**
     * @throws Exception\ParcelGeneration
     */
    protected function getHost(): string
    {
        if (empty($this->hosts[$this->countryCode])) {
            throw new Exception\ParcelGeneration('Wrong country code - ' . $this->countryCode);
        }

        return $this->hosts[$this->countryCode];
    }
}
