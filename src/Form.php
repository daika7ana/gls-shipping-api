<?php

namespace GLS;

use Illuminate\Support\Str;
use Symfony\Component\Validator\Validation;

abstract class Form {

	public static function newInstance() {
		return new static;
	}

	public function toArray() {
		return get_object_vars($this);
	}

	public function validate()
	{
		$validator  = Validation::createValidatorBuilder()->enableAnnotationMapping(true)->addDefaultDoctrineAnnotationReader()->getValidator();
		$violations = $validator->validate($this);
		if ($violations->count()) 
			throw new Exception\Validation($violations);

		return $this;
	}
}
 
