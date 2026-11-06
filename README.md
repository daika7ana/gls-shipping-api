# GLS Shipping & Tracking API

PHP client for the GLS parcel API: create labels, delete them, regenerate the
label PDF, check a GLS account and read a tracking page. Six countries:
Hungary (`HU`), Slovakia (`SK`), Czechia (`CZ`), Romania (`RO`), Slovenia
(`SI`) and Croatia (`HR`).

## Install

```bash
composer require daika7ana/gls-shipping-api
```

PHP 8.1+. Symfony 8 packages need PHP 8.4+, so on older PHP Composer picks
Symfony 6.2/7.

## Create a parcel

```php
use GLS\API;
use GLS\Form\ParcelGeneration;

$form = (new ParcelGeneration())
    ->setUsername('your-gls-user')
    ->setPassword('your-gls-password')
    ->setSenderid('123456789')
    ->setSenderName('Shop SRL')
    ->setSenderAddress('Str. Exemplu 1')
    ->setSenderCity('Cluj-Napoca')
    ->setSenderZipcode('400001')
    ->setSenderCountry('RO')
    ->setConsigName('Ion Popescu')
    ->setConsigAddress('Str. Victoriei 2')
    ->setConsigCity('Bucuresti')
    ->setConsigZipcode('010001')
    ->setConsigCountry('RO')
    ->setPcount(1)
    ->setPickupdate('2026-11-07');

$result = (new API('RO'))->generateParcel($form);

// string for one parcel, array for several
$result['tracking_code'];
// base64 PDF, or false when GLS returned none
$result['raw_pdf'];
```

Optional fields include `content`, `clientref`, `codamount`, `codref`,
`sender_email`, `consig_email`, `sender_phone`, `consig_phone` and
`printertemplate` (`A6`, `A6_PP`, `A6_ONA4`, `A4_2x2`, `A4_4x1`, `T_85x85`).
Zip codes must be 4-10 digits. Extra services go through
`setServices([(new GLS\Form\ParcelService())->setCode('T12')])`; `INS`
(insurance) requires the insured value in `info`.

`pcount` is capped at 25: one call returns every label's PDF in the response
(~1.2 MB each), so a larger batch exhausts the process after GLS has already
created the shipments. For more, send one call per 25 with a stable
`clientref` per parcel.

Several fields can be set at once, for example from a database row:
`$form->setParcelAttributes(['username' => '...', 'content' => '...'])`.
Unknown keys are ignored; a mistyped required field stays null and fails
validation.

Validation errors throw `GLS\Exception\Validation`; use `getViolations()` to
show them per field. GLS and transport errors throw
`GLS\Exception\ParcelGeneration`.

**A timeout does not mean nothing happened.** `printlabel` creates the
shipments before it renders the PDF, so a failed call may still have booked
them. Do not retry blindly — pass a stable `clientref` and look the parcel up
first.

## Other calls

```php
$api = new API('RO');

$api->getParcelPdf([123456789], [
    'username' => '...', 'password' => '...',
    'senderid' => '...', 'printertemplate' => 'A6',
]);

$api->deleteParcel([123456789], [
    'username' => '...', 'password' => '...', 'senderid' => '...',
]);

// change a cash-on-delivery amount without burning a new tracking number
$api->modifyCod([
    'username' => '...', 'password' => '...', 'senderid' => '...',
    'pclid' => 123456789, 'codamount' => 49.99, 'email' => 'shop@example.com',
]);

// ['date' => ..., 'status' => ..., 'depot' => ..., 'info' => ...]
// the first row the tracking page shows
$api->getParcelStatus('123456789');

// ['success' => 1|0]
$api->validateGLSAccount(['username' => '...', 'password' => '...']);
```

`getParcelStatus()` posts the tracking page's own search form and returns the
first row of the result table. GLS renders no rows until it has scanned the
parcel, so a freshly created label throws instead of returning a status.

`deleteParcel()` and `modifyCod()` throw on a GLS refusal instead of returning
`successfull: false`. Some accounts are not allowed to change COD after
creation — the GLS demo account answers `46: Invalid client number`.

## Testing your own integration

```php
$api->setHost('https://gls-stub.test');         // point every call at a stub
$api->setHttpClient($guzzleClientWithMockHandler); // tracking and login only
```

## Tests

```bash
composer test
composer lint
```
