<?php

namespace GLS\Validator;

use Symfony\Component\Validator\Constraints\Email as SymfonyEmailConstraint;

class StrictEmailConstraint extends SymfonyEmailConstraint
{
    public $mode = self::VALIDATION_MODE_STRICT;

    /**
     * @inheritdoc
     */
    public function validatedBy()
    {
        return StrictEmailValidator::class;
    }
}
