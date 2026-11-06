<?php

namespace GLS\Form;

use DateTime;
use DateTimeZone;
use GLS\Exception;
use GLS\Form;
use Symfony\Component\Validator\Constraints as Assert;

class ParcelGeneration extends Form
{
    /**
     * user name – request it from GLS.
     */
    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    protected ?string $username = null;

    /**
     * password – request it from GLS.
     */
    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    protected ?string $password = null;

    /**
     * GLS client number – request it from GLS.
     */
    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    protected ?string $senderid = null;

    /**
     * sender name.
     */
    #[Assert\NotBlank]
    #[Assert\Length(max: 40)]
    protected ?string $sender_name = null;

    /**
     * sender address.
     */
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    protected ?string $sender_address = null;

    /**
     * sender city.
     */
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    protected ?string $sender_city = null;

    /**
     * sender zip code.
     */
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{4,10}$/', message: 'Zip code must be 4 to 10 digits.')]
    protected ?string $sender_zipcode = null;

    /**
     * sender country code or country name.
     */
    #[Assert\NotBlank]
    #[Assert\Length(max: 32)]
    protected ?string $sender_country = null;

    /**
     * sender contact person.
     */
    #[Assert\Length(max: 40)]
    protected ?string $sender_contact = null;

    /**
     * sender phone.
     */
    #[Assert\Length(max: 40)]
    protected ?string $sender_phone = null;

    /**
     * sender email address.
     */
    #[Assert\Email]
    protected ?string $sender_email = null;

    /**
     * consignee name.
     */
    #[Assert\NotBlank]
    #[Assert\Length(max: 40)]
    protected ?string $consig_name = null;

    /**
     * consignee address.
     */
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    protected ?string $consig_address = null;

    /**
     * consignee city.
     */
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    protected ?string $consig_city = null;

    /**
     * consignee zip code.
     */
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{4,10}$/', message: 'Zip code must be 4 to 10 digits.')]
    protected ?string $consig_zipcode = null;

    /**
     * consignee country code or country name.
     */
    #[Assert\NotBlank]
    #[Assert\Length(max: 32)]
    protected ?string $consig_country = null;

    /**
     * consignee contact person.
     */
    #[Assert\Length(max: 40)]
    protected ?string $consig_contact = null;

    /**
     * consignee phone or SMS number for services.
     */
    #[Assert\Length(max: 40)]
    protected ?string $consig_phone = null;

    /**
     * consignee email address - also used for services.
     */
    #[Assert\Email]
    protected ?string $consig_email = null;

    /**
     * count of the parcels / labels to print.
     */
    #[Assert\NotBlank]
    /**
     * 1-25: every label's PDF comes back in the same response (~1.2 MB each,
     * doubled by the SOAP parser), so a bigger batch exhausts a 128 MB process
     * after GLS already created the shipments. Send one call per 25 instead.
     */
    #[Assert\Range(min: 1, max: 25)]
    protected ?int $pcount = null;

    /**
     * pickup date in format yyyy-MM-dd.
     */
    #[Assert\NotBlank]
    #[Assert\Date]
    protected ?string $pickupdate = null;

    /**
     * content of the parcel – info printed on label.
     */
    #[Assert\Length(max: 512)]
    protected ?string $content = null;

    /**
     * client reference.
     */
    #[Assert\Length(max: 512)]
    protected ?string $clientref = null;

    /**
     * COD amount.
     */
    #[Assert\Range(min: 0, max: 99999)]
    protected int|float|string|null $codamount = null;

    /**
     * COD reference – used if COD amount is set.
     */
    #[Assert\Length(max: 512)]
    protected ?string $codref = null;

    /**
     * @var ParcelService[]
     */
    protected ?array $services = null;

    /**
     * type of the printer – list in Appendix B.
     */
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['A6', 'A6_PP', 'A6_ONA4', 'A4_2x2', 'A4_4x1', 'T_85x85'])]
    protected string $printertemplate = 'A6';

    /**
     * Always true: GLS prints the label as part of printlabel. Not configurable.
     */
    protected bool $printit = true;

    protected ?string $timestamp = null;

    protected ?string $hash = null;

    public function setConsigName(?string $consig_name): static
    {
        $this->consig_name = $consig_name;

        return $this;
    }

    public function setClientref(?string $clientref): static
    {
        $this->clientref = $clientref;

        return $this;
    }

    public function setCodamount(int|float|string|null $codamount): static
    {
        $this->codamount = $codamount;

        return $this;
    }

    public function setCodref(?string $codref): static
    {
        $this->codref = $codref;

        return $this;
    }

    public function setConsigAddress(?string $consig_address): static
    {
        $this->consig_address = $consig_address;

        return $this;
    }

    public function setConsigCity(?string $consig_city): static
    {
        $this->consig_city = $consig_city;

        return $this;
    }

    public function setConsigContact(?string $consig_contact): static
    {
        $this->consig_contact = $consig_contact;

        return $this;
    }

    public function setConsigCountry(?string $consig_country): static
    {
        $this->consig_country = $consig_country;

        return $this;
    }

    public function setConsigEmail(?string $consig_email): static
    {
        $this->consig_email = $consig_email;

        return $this;
    }

    public function setConsigPhone(?string $consig_phone): static
    {
        $this->consig_phone = $consig_phone;

        return $this;
    }

    public function setConsigZipcode(?string $consig_zipcode): static
    {
        $this->consig_zipcode = $consig_zipcode;

        return $this;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function setPassword(?string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function setPcount(?int $pcount): static
    {
        $this->pcount = $pcount;

        return $this;
    }

    public function setPickupdate(?string $pickupdate): static
    {
        $this->pickupdate = $pickupdate;

        return $this;
    }

    public function setPrintertemplate(string $printertemplate): static
    {
        $this->printertemplate = $printertemplate;

        return $this;
    }

    public function setSenderAddress(?string $sender_address): static
    {
        $this->sender_address = $sender_address;

        return $this;
    }

    public function setSenderCity(?string $sender_city): static
    {
        $this->sender_city = $sender_city;

        return $this;
    }

    public function setSenderContact(?string $sender_contact): static
    {
        $this->sender_contact = $sender_contact;

        return $this;
    }

    public function setSenderCountry(?string $sender_country): static
    {
        $this->sender_country = $sender_country;

        return $this;
    }

    public function setSenderEmail(?string $sender_email): static
    {
        $this->sender_email = $sender_email;

        return $this;
    }

    public function setSenderName(?string $sender_name): static
    {
        $this->sender_name = $sender_name;

        return $this;
    }

    public function setSenderPhone(?string $sender_phone): static
    {
        $this->sender_phone = $sender_phone;

        return $this;
    }

    public function setSenderZipcode(?string $sender_zipcode): static
    {
        $this->sender_zipcode = $sender_zipcode;

        return $this;
    }

    public function setSenderid(?string $senderid): static
    {
        $this->senderid = $senderid;

        return $this;
    }

    /**
     * @param ParcelService[] $services
     */
    public function setServices(array $services): static
    {
        $this->services = $services;

        return $this;
    }

    public function setUsername(?string $username): static
    {
        $this->username = $username;

        return $this;
    }

    /**
     * Set several fields at once, e.g. from a database row.
     * Unknown keys are ignored, so a mistyped required field stays null and
     * fails validate() instead of shipping a broken parcel.
     */
    public function setParcelAttributes(array $array): static
    {
        foreach ($array as $attribute => $value) {
            // Unknown keys would create deprecated dynamic properties.
            if (property_exists($this, $attribute)) {
                $this->{$attribute} = $value;
            }
        }

        return $this;
    }

    public function toArray(): array
    {
        $this->timestamp = (new DateTime('now', new DateTimeZone('Europe/Bucharest')))->format('YmdHis');
        $data = parent::toArray();

        if ($this->services) {
            $data['services'] = array_map(
                function (ParcelService $srv) {
                    return $srv->toArray();
                },
                $this->services,
            );
        } else {
            // The WSDL expects a list; sending null makes GLS guess.
            unset($data['services']);
        }

        $this->generateHash($data);

        return $data;
    }

    /**
     * @throws Exception\Validation
     */
    public function validate(): static
    {
        parent::validate();

        // GLS answers 22 "Invalid / missing parameters for service(s): INS"
        // when the insured value is missing, so fail before the round trip.
        foreach ($this->services ?? [] as $service) {
            $attributes = $service->toArray();

            if (($attributes['code'] ?? null) === ParcelService::INS && trim((string) ($attributes['info'] ?? '')) === '') {
                throw new Exception\Validation('Service INS requires the insured value in info.');
            }
        }

        return $this;
    }

    protected function generateHash(array &$data): void
    {
        $hash = '';
        foreach ($data as $key => $value) {
            if (in_array($key, ['services', 'hash', 'timestamp', 'printit', 'printertemplate'])) {
                continue;
            }

            $hash .= $value;
        }

        $data['hash'] = sha1($hash);
    }
}
