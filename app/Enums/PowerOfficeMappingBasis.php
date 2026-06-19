<?php

namespace App\Enums;

enum PowerOfficeMappingBasis: string
{
    case Vat = 'vat';
    case Category = 'category';
    case ArticleGroup = 'article_group';
    case Vendor = 'vendor';
    case PaymentMethod = 'payment_method';

    public function label(): string
    {
        return match ($this) {
            self::Vat => 'VAT rate',
            self::Category => 'Product collection',
            self::ArticleGroup => 'Article group code',
            self::Vendor => 'Vendor',
            self::PaymentMethod => 'Payment method',
        };
    }

    /** @return list<self> */
    public static function integrationMappingBases(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $basis): bool => $basis !== self::ArticleGroup,
        ));
    }
}
