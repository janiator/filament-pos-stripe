<?php

namespace App\Exceptions\PowerOffice;

use RuntimeException;

class PowerOfficeUnresolvedGlAccountsException extends RuntimeException
{
    /**
     * @param  list<string>  $missingAccountNos
     */
    public function __construct(
        public readonly array $missingAccountNos,
        string $message = 'One or more general ledger account numbers were not found in PowerOffice Go.',
    ) {
        parent::__construct($message);
    }
}
