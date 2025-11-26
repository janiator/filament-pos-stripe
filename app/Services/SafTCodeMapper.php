<?php

namespace App\Services;

use App\Models\ConnectedCharge;
use App\Models\ConnectedProduct;

class SafTCodeMapper
{
    /**
     * Map payment method to PredefinedBasicID-12 (Payment Code)
     */
    public static function mapPaymentMethodToCode(?string $paymentMethod): string
    {
        return match($paymentMethod) {
            'cash' => '12001', // Cash
            'card' => '12002', // Debit card (default for card)
            'credit_card' => '12003', // Credit card
            'bank_account' => '12004', // Bank account
            'gift_token' => '12005', // Gift token
            'customer_card' => '12006', // Customer card
            'loyalty' => '12007', // Loyalty, stamps
            'bottle_deposit' => '12008', // Bottle deposit
            'check' => '12009', // Check
            'credit_note' => '12010', // Credit note
            'mobile' => '12011', // Mobile phone apps
            default => '12999', // Other
        };
    }

    /**
     * Map payment method to PredefinedBasicID-13 (Event Code)
     */
    public static function mapPaymentMethodToEventCode(?string $paymentMethod): string
    {
        return match($paymentMethod) {
            'cash' => '13016', // Cash payment
            'card', 'credit_card' => '13017', // Card payment
            'mobile' => '13018', // Mobile payment
            default => '13019', // Other payment method
        };
    }

    /**
     * Map transaction to PredefinedBasicID-11 (Transaction Code)
     */
    public static function mapTransactionToCode(ConnectedCharge $charge): string
    {
        // Return/refund
        if ($charge->refunded || $charge->amount_refunded > 0) {
            return '11006'; // Return payment
        }

        // Cash sale
        if ($charge->payment_method === 'cash') {
            return '11001'; // Cash sale
        }

        // Credit sale (default for card/mobile)
        return '11002'; // Credit sale
    }

    /**
     * Get article group code from product
     */
    public static function getArticleGroupCode(?ConnectedProduct $product, ?string $override = null): string
    {
        // Use override if provided
        if ($override) {
            return $override;
        }

        // Use product's article_group_code if set
        if ($product && $product->article_group_code) {
            return $product->article_group_code;
        }

        // Default based on product type
        if ($product) {
            return match($product->type) {
                'service' => '04004', // Sale of treatment services
                'good' => '04003', // Sale of goods
                default => '04999', // Other
            };
        }

        // Default fallback
        return '04999'; // Other
    }

    /**
     * Get all PredefinedBasicID-04 codes (Article Group)
     */
    public static function getArticleGroupCodes(): array
    {
        return [
            '04001' => 'Uttak av behandlingstjenester',
            '04002' => 'Uttak av behandlingsvarer',
            '04003' => 'Varesalg',
            '04004' => 'Salg av behandlingstjenester',
            '04005' => 'Salg av hårklipp',
            '04006' => 'Mat',
            '04007' => 'Øl',
            '04008' => 'Vin',
            '04009' => 'Brennevin',
            '04010' => 'Rusbrus/Cider',
            '04011' => 'Mineralvann (brus)',
            '04012' => 'Annen drikke (te, kaffe etc)',
            '04013' => 'Tobakk',
            '04014' => 'Andre varer',
            '04015' => 'Inngangspenger',
            '04016' => 'Inngangspenger fri adgang',
            '04017' => 'Garderobeavgift',
            '04018' => 'Garderobeavgift fri garderobe',
            '04019' => 'Helfullpensjon',
            '04020' => 'Halvpensjon',
            '04021' => 'Overnatting med frokost',
            '04999' => 'Øvrige',
        ];
    }

    /**
     * Get all PredefinedBasicID-11 codes (Transaction)
     */
    public static function getTransactionCodes(): array
    {
        return [
            '11001' => 'Kontantsalg',
            '11002' => 'Kredittsalg',
            '11003' => 'Kjøp av varer',
            '11004' => 'Betaling',
            '11005' => 'Innbetaling fra kunde',
            '11006' => 'Utbetaling ved retur',
            '11007' => 'Inngående vekselbeholdning',
            '11008' => 'Kassedifferanse',
            '11009' => 'Korrigere kvittering',
            '11010' => 'Utbetaling (ansatte tar ut)',
            '11011' => 'Innbetaling (ansatte setter inn)',
            '11012' => 'Kjøp fra kunde + salg',
            '11013' => 'Vare i retur',
            '11014' => 'Inventar, lager',
            '11015' => 'Kontant- og kredittsalg',
            '11016' => 'Kontantsalg og retur',
            '11017' => 'Kredittsalg og retur',
            '11999' => 'Øvrige',
        ];
    }

    /**
     * Get all PredefinedBasicID-12 codes (Payment)
     */
    public static function getPaymentCodes(): array
    {
        return [
            '12001' => 'Kontant',
            '12002' => 'Bankkort (debet)',
            '12003' => 'Kredittkort',
            '12004' => 'Bankkonto',
            '12005' => 'Gavekort',
            '12006' => 'Kundekonto',
            '12007' => 'Lojalitetspoeng',
            '12008' => 'Pant',
            '12009' => 'Sjekk',
            '12010' => 'Tilgodelapp',
            '12011' => 'Mobiltelefon løsninger',
            '12999' => 'Øvrige',
        ];
    }
}
