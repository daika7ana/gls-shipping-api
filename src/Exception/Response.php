<?php

namespace GLS\Exception;

use GLS\Exception;

abstract class Response extends Exception
{
    public function __construct(string $message, mixed $response = null)
    {
        $string = $message;
        if ($response) {
            $string .= " ({$response})";
        }
        parent::__construct($string);
    }
}
