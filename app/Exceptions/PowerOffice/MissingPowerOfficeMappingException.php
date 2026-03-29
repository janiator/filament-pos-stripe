<?php

namespace App\Exceptions\PowerOffice;

use RuntimeException;

class MissingPowerOfficeMappingException extends RuntimeException
{
    /**
     * @param  list<string>  $missingBasisKeys
     */
    public function __construct(
        public readonly array $missingBasisKeys,
        string $message = 'Missing PowerOffice account mapping for one or more basis keys.',
    ) {
        parent::__construct($message);
    }
}
