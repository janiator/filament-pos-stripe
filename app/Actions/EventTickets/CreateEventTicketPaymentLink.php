<?php

namespace App\Actions\EventTickets;

use App\Actions\ConnectedPaymentLinks\CreateConnectedPaymentLinkOnStripe;
use App\Actions\ConnectedPrices\CreateConnectedPriceInStripe;
use App\Actions\ConnectedProducts\CreateConnectedProductInStripe;
use App\Models\ConnectedPaymentLink;
use App\Models\ConnectedProduct;
use App\Models\Store;
use Illuminate\Support\Facades\Log;

class CreateEventTicketPaymentLink
{
    /**
     * Create a Stripe product, one-time price, and payment link for an event ticket.
     * application_fee_amount = (2 * ticket_price_cents) * (store commission_rate / 100).
     *
     * @param  array{label: string, price_cents: int, max_quantity: int, image_url?: string|null}  $data
     */
    public function __invoke(Store $store, array $data): ?ConnectedPaymentLink
    {
        if (! $store->hasStripeAccount()) {
            Log::warning('CreateEventTicketPaymentLink: store has no Stripe account', ['store_id' => $store->id]);

            return null;
        }

        $label = $data['label'] ?? 'Event ticket';
        $priceCents = (int) ($data['price_cents'] ?? 0);
        if ($priceCents <= 0) {
            Log::warning('CreateEventTicketPaymentLink: invalid price_cents', ['price_cents' => $priceCents]);

            return null;
        }

        $imageUrl = $data['image_url'] ?? null;
        $maxQuantity = (int) ($data['max_quantity'] ?? 0);

        $product = new ConnectedProduct;
        $product->stripe_account_id = $store->stripe_account_id;
        $product->name = $label;
        $product->active = true;
        $product->type = 'service';
        if (filled($imageUrl) && filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            $product->images = [$imageUrl];
        }
        $product->save();

        $stripeProductId = app(CreateConnectedProductInStripe::class)($product);
        if (! $stripeProductId) {
            Log::error('CreateEventTicketPaymentLink: failed to create product in Stripe');
            $product->delete();

            return null;
        }

        $product->stripe_product_id = $stripeProductId;
        $product->save();

        $currency = 'nok';
        $stripePriceId = app(CreateConnectedPriceInStripe::class)(
            $stripeProductId,
            $store->stripe_account_id,
            $priceCents,
            $currency
        );
        if (! $stripePriceId) {
            Log::error('CreateEventTicketPaymentLink: failed to create price in Stripe');
            $product->delete();

            return null;
        }

        $commissionRate = (int) ($store->commission_rate ?? 0);
        $applicationFeeAmount = (int) round((2 * $priceCents) * ($commissionRate / 100));

        $lineItem = [
            'price' => $stripePriceId,
            'quantity' => 1,
            'adjustable_quantity' => [
                'enabled' => true,
                'minimum' => 1,
                'maximum' => $maxQuantity > 0 ? min($maxQuantity, 999999) : 99,
            ],
        ];

        $linkData = [
            'line_items' => [$lineItem],
            'name' => $label,
            'link_type' => 'direct',
            'application_fee_amount' => $applicationFeeAmount > 0 ? $applicationFeeAmount : null,
        ];

        $paymentLink = app(CreateConnectedPaymentLinkOnStripe::class)($store, $linkData, false);
        if (! $paymentLink) {
            Log::error('CreateEventTicketPaymentLink: failed to create payment link in Stripe');
        }

        return $paymentLink;
    }
}
