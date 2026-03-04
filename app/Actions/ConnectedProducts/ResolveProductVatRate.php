<?php

namespace App\Actions\ConnectedProducts;

use App\Models\ArticleGroupCode;
use App\Models\ConnectedProduct;

class ResolveProductVatRate
{
    /**
     * Resolve the effective VAT rate for a product (0–1, e.g. 0.25 for 25%).
     * Order: product vat_percent → article group default_vat_percent → tax_code mapping → 0.25 default.
     */
    public function __invoke(ConnectedProduct $product): float
    {
        if ($product->vat_percent !== null) {
            return (float) $product->vat_percent / 100;
        }

        if ($product->article_group_code) {
            $articleGroupCode = $this->resolveArticleGroupCode(
                $product->article_group_code,
                $product->stripe_account_id
            );
            if ($articleGroupCode && $articleGroupCode->default_vat_percent !== null) {
                return (float) $articleGroupCode->default_vat_percent;
            }
        }

        return $this->getTaxPercentFromCode($product->tax_code);
    }

    protected function resolveArticleGroupCode(string $code, ?string $stripeAccountId): ?ArticleGroupCode
    {
        $query = ArticleGroupCode::where('code', $code)->where('active', true);

        if ($stripeAccountId) {
            $query->where(function ($q) use ($stripeAccountId) {
                $q->where('stripe_account_id', $stripeAccountId)
                    ->orWhereNull('stripe_account_id');
            })->orderByRaw('CASE WHEN stripe_account_id IS NOT NULL THEN 0 ELSE 1 END');
        } else {
            $query->whereNull('stripe_account_id');
        }

        return $query->first();
    }

    /**
     * Get VAT percentage (0–100) for an article group code, or null if not found or no default.
     * Used when bulk-setting article group code so VAT can be synced without a product instance.
     */
    public function vatPercentFromArticleGroupCode(string $code, ?string $stripeAccountId): ?float
    {
        $articleGroupCode = $this->resolveArticleGroupCode($code, $stripeAccountId);
        if (! $articleGroupCode || $articleGroupCode->default_vat_percent === null) {
            return null;
        }

        return round((float) $articleGroupCode->default_vat_percent * 100, 2);
    }

    protected function getTaxPercentFromCode(?string $taxCode): float
    {
        if (! $taxCode) {
            return 0.25;
        }

        $taxCodeLower = strtolower($taxCode);

        return match ($taxCodeLower) {
            'txcd_99999999', 'standard', '1' => 0.25,
            'txcd_99999998', 'reduced', 'food' => 0.15,
            'txcd_99999997', 'lower', 'service' => 0.10,
            'txcd_99999996', 'zero', 'exempt', '0' => 0.00,
            default => 0.25,
        };
    }
}
