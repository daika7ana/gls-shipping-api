<?php

namespace GLS\Form;

use GLS\Form;
use Symfony\Component\Validator\Constraints as Assert;

class ParcelStatus extends Form
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 30)]
    protected ?string $secret = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 14)]
    protected ?string $parcel_number = null;

    public function setParcelNumber(?string $parcel_number): static
    {
        $this->parcel_number = $parcel_number;

        return $this;
    }

    public function setSecret(?string $secret): static
    {
        $this->secret = $secret;

        return $this;
    }
}
