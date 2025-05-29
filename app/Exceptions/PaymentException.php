<?php

namespace App\Exceptions;
use Exception;

class PaymentException extends Exception
{

    /**
     * @param string $string
     */
    public function __construct(string $string)
    {
        return parent::__construct($string);
    }
}
