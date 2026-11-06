<?php

namespace GLS;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\EmailValidator;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Validation;

abstract class Form
{
    public static function newInstance(): static
    {
        return new static();
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public function validate(): static
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([EmailValidator::class => new EmailValidator(Assert\Email::VALIDATION_MODE_HTML5)]))
            ->getValidator();

        $violations = $validator->validate($this);

        if ($violations->count()) {
            throw new Exception\Validation($violations);
        }

        return $this;
    }
}
