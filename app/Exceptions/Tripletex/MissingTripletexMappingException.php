<?php

namespace App\Exceptions\Tripletex;

use RuntimeException;

class MissingTripletexMappingException extends RuntimeException
{
    /**
     * @param  list<string>  $missingBasisKeys
     */
    public function __construct(
        public array $missingBasisKeys,
        string $message = 'Missing Tripletex account mapping.',
    ) {
        parent::__construct($message);
    }
}
