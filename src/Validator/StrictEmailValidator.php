<?php

namespace GLS\Validator;

use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\EmailValidator as SymfonyEmailValidator;

/**
 * @Annotation
 *
 * Email strict validation constraint.
 *
 * Overrides the symfony validator to use the strict setting.
 *
 * @internal Exists only to override the constructor to avoid a deprecation
 */
final class StrictEmailValidator extends SymfonyEmailValidator
{
    private string $defaultMode = Email::VALIDATION_MODE_STRICT;

    /**
     * @inheritdoc
     */
    public function __construct()
    {
        parent::__construct($this->defaultMode);
    }
}
