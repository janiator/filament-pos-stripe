<?php

namespace App\Services;

use App\Models\ConnectedCharge;
use App\Models\ConnectedProduct;

class SafTCodeMapper
{
    /**
     * Map payment method to PredefinedBasicID-12 (Payment Code)
     * 
     * @param string|null $paymentMethodCode Internal payment method code (e.g., "cash", "card", "card_present")
     * @param string|null $providerMethod Provider-specific method (e.g., "card_present", "us_bank_account", "sepa_debit")
     * @return string SAF-T payment code
     */
    public static function mapPaymentMethodToCode(?string $paymentMethodCode, ?string $providerMethod = null): string
    {
        // First, check provider_method for Stripe-specific types
        if ($providerMethod) {
            return match($providerMethod) {
                'card_present' => '12002', // Debit card (terminal)
                'card' => '12002', // Debit card (online)
                'us_bank_account' => '12004', // Bank account
                'sepa_debit' => '12004', // Bank account (SEPA)
                'link' => '12011', // Mobile payment (Stripe Link)
                default => self::mapPaymentMethodCodeToSafT($paymentMethodCode),
            };
        }

        // Fall back to code-based mapping
        return self::mapPaymentMethodCodeToSafT($paymentMethodCode);
    }

    /**
     * Map payment method code to SAF-T payment code (internal helper)
     */
    protected static function mapPaymentMethodCodeToSafT(?string $paymentMethodCode): string
    {
        return match($paymentMethodCode) {
            'cash' => '12001', // Cash
            'card', 'card_present' => '12002', // Debit card (default for card)
            'credit_card' => '12003', // Credit card
            'bank_account' => '12004', // Bank account
            'gift_token' => '12005', // Gift token
            'customer_card' => '12006', // Customer card
            'loyalty' => '12007', // Loyalty, stamps
            'bottle_deposit' => '12008', // Bottle deposit
            'check' => '12009', // Check
            'credit_note' => '12010', // Credit note
            'mobile', 'vipps' => '12011', // Mobile phone apps (including Vipps)
            default => '12999', // Other
        };
    }

    /**
     * Map payment method to PredefinedBasicID-13 (Event Code)
     * 
     * @param string|null $paymentMethodCode Internal payment method code
     * @param string|null $providerMethod Provider-specific method
     * @return string SAF-T event code
     */
    public static function mapPaymentMethodToEventCode(?string $paymentMethodCode, ?string $providerMethod = null): string
    {
        // First, check provider_method for Stripe-specific types
        if ($providerMethod) {
            return match($providerMethod) {
                'card_present', 'card' => '13017', // Card payment
                'us_bank_account', 'sepa_debit' => '13019', // Other payment (bank transfers)
                'link' => '13018', // Mobile payment
                default => self::mapPaymentMethodCodeToEventCode($paymentMethodCode),
            };
        }

        // Fall back to code-based mapping
        return self::mapPaymentMethodCodeToEventCode($paymentMethodCode);
    }

    /**
     * Map payment method code to SAF-T event code (internal helper)
     */
    protected static function mapPaymentMethodCodeToEventCode(?string $paymentMethodCode): string
    {
        return match($paymentMethodCode) {
            'cash' => '13016', // Cash payment
            'card', 'card_present', 'credit_card' => '13017', // Card payment
            'mobile', 'vipps' => '13018', // Mobile payment (including Vipps)
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
     * Map payment method code to transaction code (PredefinedBasicID-11)
     */
    public static function mapTransactionToCodeForPayment(string $paymentMethodCode): string
    {
        return match($paymentMethodCode) {
            'cash' => '11001', // Cash sale
            'card', 'card_present', 'credit_card' => '11002', // Credit sale
            'mobile', 'vipps' => '11002', // Credit sale (mobile/Vipps is also credit)
            default => '11002', // Default to credit sale
        };
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

    /**
     * Get VAT percent from article group code
     * Maps SAF-T article group codes to VAT percentages (Norwegian rates)
     * 
     * @param string|null $articleGroupCode SAF-T article group code
     * @return float VAT percentage (e.g., 25.00 for 25%)
     */
    public static function getVatPercentFromArticleGroupCode(?string $articleGroupCode): ?float
    {
        if (!$articleGroupCode) {
            return null;
        }

        // Map article group codes to VAT percentages (Norwegian rates)
        return match($articleGroupCode) {
            '04003' => 25.00, // Varesalg - Standard VAT
            '04004' => 25.00, // Salg av behandlingstjenester - Standard VAT
            '04005' => 25.00, // Salg av hårklipp - Standard VAT
            '04006' => 15.00, // Mat - Reduced rate (food)
            '04007' => 25.00, // Øl - Standard VAT
            '04008' => 25.00, // Vin - Standard VAT
            '04009' => 25.00, // Brennevin - Standard VAT
            '04010' => 25.00, // Rusbrus/Cider - Standard VAT
            '04011' => 25.00, // Mineralvann (brus) - Standard VAT
            '04012' => 15.00, // Annen drikke (te, kaffe etc) - Reduced rate
            '04013' => 25.00, // Tobakk - Standard VAT
            '04014' => 25.00, // Andre varer - Standard VAT
            '04015' => 25.00, // Inngangspenger - Standard VAT
            '04016' => 25.00, // Inngangspenger fri adgang - Standard VAT
            '04017' => 25.00, // Garderobeavgift - Standard VAT
            '04018' => 25.00, // Garderobeavgift fri garderobe - Standard VAT
            '04019' => 15.00, // Helfullpensjon - Reduced rate (food service)
            '04020' => 15.00, // Halvpensjon - Reduced rate (food service)
            '04021' => 15.00, // Overnatting med frokost - Reduced rate (food service)
            '04001' => 0.00,  // Uttak av behandlingstjenester - Zero rate (withdrawal)
            '04002' => 0.00,  // Uttak av behandlingsvarer - Zero rate (withdrawal)
            '04999' => 25.00, // Øvrige - Default to standard VAT
            default => null,  // Unknown code - return null to use product's vat_percent or default
        };
    }
}
