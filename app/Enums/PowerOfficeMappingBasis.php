<?php

namespace App\Enums;

enum PowerOfficeMappingBasis: string
{
    case Vat = 'vat';
    case Category = 'category';
    case Vendor = 'vendor';
    case PaymentMethod = 'payment_method';

    public function label(): string
    {
        return match ($this) {
            self::Vat => 'VAT rate',
            self::Category => 'Product collection',
            self::Vendor => 'Vendor',
            self::PaymentMethod => 'Payment method',
        };
    }
}
