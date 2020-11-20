<?php

namespace steroids\payment\exceptions;

use Throwable;

class SignatureMismatchRequestException extends PaymentProcessException
{
    public function __construct($params = [], $message = "", $code = 0, Throwable $previous = null)
    {
        $message = 'Signature is not valid. Request params: ' . print_r($params, true);

        parent::__construct($message, $code, $previous);
    }

}