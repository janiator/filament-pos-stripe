<?php

namespace App\Exceptions;

use Exception;

class InsufficientStockException extends Exception
{
    /**
     * @param  array<int, array{variant_id: int, product_id: ?int, requested: int, available: int, sku: ?string}>  $lines
     */
    public function __construct(
        string $message = 'Insufficient stock for one or more items.',
        public readonly array $lines = []
    ) {
        parent::__construct($message);
    }
}
