<?php

namespace App\Exceptions;

use Exception;

class CashDrawerDisabledException extends Exception
{
    public function __construct(string $message = 'Cash payments are not allowed on this device (cash drawer disabled).')
    {
        parent::__construct($message);
    }
}
