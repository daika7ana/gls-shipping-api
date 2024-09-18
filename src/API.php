<?php

namespace GLS;

use SoapFault;
use nusoap_client;
use SimpleXMLElement;
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

class API
{
    protected $countryCode = '';

    protected $urls = [
        'HU' => 'http://online.gls-hungary.com/webservices/soap_server.php?wsdl',
        'SK' => 'http://online.gls-slovakia.sk/webservices/soap_server.php?wsdl',
        'CZ' => 'http://online.gls-czech.com/webservices/soap_server.php?wsdl',
        'RO' => 'http://online.gls-romania.ro/webservices/soap_server.php?wsdl',
        'SI' => 'http://connect.gls-slovenia.com/webservices/soap_server.php?wsdl',
        'HR' => 'http://online.gls-croatia.com/webservices/soap_server.php?wsdl',
    ];

    /**
     * API constructor.
     *
     * @param string $countryCode HU/SK/CZ/RO/SI/HR
     */
    public function __construct($countryCode)
    {
        $this->countryCode = strtoupper($countryCode);
    }

    /**
     * Get parcel/s number/s.
     *
     * @param  Form\ParcelGeneration      $form
     * @throws Exception\ParcelGeneration
     * @return array
     */
    public function generateParcel(Form\ParcelGeneration $form)
    {
        $form->setPrintit(true)->validate();

        try {
            $data = $this->requestNuSOAP('printlabel', $form);
        } catch (SoapFault $e) {
            throw new Exception\ParcelGeneration($e->getMessage());
        }

        if (empty($data['successfull'])) {
            throw new Exception\ParcelGeneration(
                'Response with error',
                (is_array($data) && isset($data['errcode']))
                    ? "{$data['errcode']}: {$data['errdesc']}" : 'Unknown error - no errcode received'
            );
        }

        if (empty($data['pcls'])) {
            throw new Exception\ParcelGeneration('No parcels numbers received!');
        }

        return [
            'tracking_code' => 1 == count($data['pcls']) ? $data['pcls'][0] : $data['pcls'],
            'raw_pdf' => !empty($data['pdfdata']) ? $data['pdfdata'] : false,
        ];
    }

    /**
     * Delete parcel/s number/s.
     */
    public function deleteParcel(array $parcel_ids, array $requested_data)
    {
        $required_keys = ['username', 'password', 'senderid'];
        $missing_keys = array_diff_key(array_flip($required_keys), $requested_data);

        if ($missing_keys) {
            throw new Exception('The provided array has missing keys.');
        }

        $data = $requested_data;
        $data['pclids'] = $parcel_ids;

        return $this->requestNuSOAP('deletelabels', $data);
    }

    /**
     * Regenerate PDF based on the already known PclID.
     *
     * @param  array            $parcel_ids required for PDF generation
     * @param  array            $data       must contain: ['username', 'password', 'senderid', 'printertemplate']
     * @throws Exception
     * @return SimpleXMLElement
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
            $requested_parcel_pdf .= "<long>{$id}</long>";
        }
        $requested_parcel_pdf .= '</PclIDs></Shipment></Shipments></DTU>';

        $requested_data['data'] = base64_encode(gzencode($requested_parcel_pdf, 9));
        $requested_data['is_autoprint_pdfs'] = false;

        $response = $this->requestNuSOAP('getprintedlabels_gzipped_xml', $requested_data);
        $xml = gzdecode($response);

        return simplexml_load_string($xml);
    }

    /**
     * Get parcel status.
     *
     * @param $tracking_code
     * @throws Exception
     * @return mixed
     */
    public function getParcelStatus($tracking_code)
    {
        $html = $this->request($this->getTrackingUrl($tracking_code));
        $dom = new Crawler($html);
        $row = $dom->filter('table tr.colored_0, table tr.colored_1')->first();

        if (!count($row)) {
            throw new Exception('Tracking code wasn`t registered or error occured!');
        }

        $data = array_map('trim', [
            'date' => $row->filter('td')->eq(0)->text(),
            'status' => $row->filter('td')->eq(1)->text(),
            'depot' => $row->filter('td')->eq(2)->text(),
            'info' => $row->filter('td')->eq(3)->text(),
        ]);

        return $data;
    }

    public function getTrackingUrl($parcelNumber)
    {
        return "https://online.gls-romania.ro/tt_page.php?tt_value={$parcelNumber}";
    }

    /**
     * @param  array     $login_data must contain ['username', 'password']
     * @throws Exception
     * @return array
     */
    public function validateGLSAccount(array $login_data): array
    {
        $required_keys = ['username', 'password'];
        $missing_keys = array_diff_key(array_flip($required_keys), $login_data);

        if ($missing_keys) {
            throw new Exception('The provided array has missing keys.');
        }

        $login_data['page'] = 'welcome.php';

        $html = $this->request('https://online.gls-romania.ro/login.php', $login_data, 'POST');

        $dom = new Crawler($html);
        $row = $dom->filter('meta[http-equiv="Content-Type"]')->first();

        return !count($row) ? ['success' => 1] : ['success' => 0];
    }

    /**
     * @param  string     $method
     * @param  array|Form $data
     * @throws SoapFault
     * @return mixed
     */
    protected function requestNuSOAP($method, $data = [])
    {
        if ($data instanceof Form) {
            $data = $data->toArray();
        }

        $client = new nusoap_client($this->getApiUrl(), 'wsdl', false, false, false, false, 5, 5);
        $client->soap_defencoding = 'UTF-8';
        $client->decode_utf8 = false;

        ob_start();
        $result = $client->call($method, $data);
        ob_end_clean();

        return $result;
    }

    protected function request($url, $data = [], $method = 'GET')
    {
        if ($data instanceof Form) {
            $data = $data->toArray();
        }

        $client = new Client();
        $response = $client->request($method, $url, ['form_params' => $data, 'timeout' => 5, 'connect_timeout' => 2]);

        return $response->getBody()->getContents();
    }

    /**
     * Get api url based on country code.
     *
     * @throws Exception
     * @return string
     */
    protected function getApiUrl()
    {
        if (empty($this->urls[$this->countryCode])) {
            throw new Exception('Wrong country code - ' . $this->countryCode);
        }

        return $this->urls[$this->countryCode];
    }
}
