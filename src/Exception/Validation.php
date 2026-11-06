<?php

namespace GLS\Exception;

use GLS\Exception;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class Validation extends Exception
{
    private ?ConstraintViolationListInterface $violations = null;

    public function __construct(ConstraintViolationListInterface|string $violations)
    {
        if ($violations instanceof ConstraintViolationListInterface) {
            $this->violations = $violations;
            parent::__construct((string) $violations);
        } else {
            parent::__construct($violations);
        }
    }

    /**
     * Structured violations, so a form can show errors per field.
     */
    public function getViolations(): ?ConstraintViolationListInterface
    {
        return $this->violations;
    }
}
