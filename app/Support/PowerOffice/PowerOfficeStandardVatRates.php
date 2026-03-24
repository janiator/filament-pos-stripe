<?php

namespace App\Support\PowerOffice;

/**
 * VAT rate keys used when splitting Z-report net sales by {@see \App\Enums\PowerOfficeMappingBasis::Vat}.
 * Keys must match {@see \App\Services\PowerOffice\PowerOfficeLedgerPayloadBuilder::bucketsForVat()} (stringified integer rates).
 */
final class PowerOfficeStandardVatRates
{
    /**
     * @return list<string>
     */
    public static function basisKeys(): array
    {
        return array_keys(self::options());
    }

    /**
     * @return array<string, string> basis_key => label
     */
    public static function options(): array
    {
        return [
            '0' => '0%',
            '15' => '15%',
            '25' => '25%',
        ];
    }
}
