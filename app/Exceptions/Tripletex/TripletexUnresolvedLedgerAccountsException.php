<?php

namespace App\Exceptions\Tripletex;

use RuntimeException;

class TripletexUnresolvedLedgerAccountsException extends RuntimeException
{
    /**
     * @param  list<string>  $missingAccountNos
     */
    public function __construct(
        public readonly array $missingAccountNos,
        string $message = 'One or more ledger account numbers were not found in Tripletex.',
    ) {
        parent::__construct($message);
    }
}
