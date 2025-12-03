<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use App\Models\Store;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all stores (or create default payment methods for existing stores)
        $stores = Store::all();

        foreach ($stores as $store) {
            // Create default payment methods (only if they don't exist)
            $defaultMethods = [
                [
                    'name' => 'Kontant',
                    'code' => 'cash',
                    'provider' => 'cash',
                    'enabled' => true,
                    'pos_suitable' => true,
                    'sort_order' => 0,
                    'saf_t_payment_code' => '12001',
                    'saf_t_event_code' => '13016',
                    'description' => 'Kontantbetaling',
                    'background_color' => '#4DEE8B60',
                    'icon_color' => '#ee8b60',
                ],
                [
                    'name' => 'Kort',
                    'code' => 'card_present',
                    'provider' => 'stripe',
                    'provider_method' => 'card_present',
                    'enabled' => true,
                    'pos_suitable' => true,
                    'sort_order' => 1,
                    'saf_t_payment_code' => '12002',
                    'saf_t_event_code' => '13017',
                    'description' => 'Kortbetaling via Stripe Terminal',
                    'background_color' => '#4C4B39EF',
                    'icon_color' => '#272b3d',
                ],
                [
                    'name' => 'Kort (Online)',
                    'code' => 'card',
                    'provider' => 'stripe',
                    'provider_method' => 'card',
                    'enabled' => true,
                    'pos_suitable' => false, // Online-only, not for physical POS
                    'sort_order' => 2,
                    'saf_t_payment_code' => '12002',
                    'saf_t_event_code' => '13017',
                    'description' => 'Kortbetaling (online, ikke terminal)',
                    'background_color' => '#4C4B39EF',
                    'icon_color' => '#272b3d',
                ],
                [
                    'name' => 'Gavekort',
                    'code' => 'gift_token',
                    'provider' => 'other',
                    'enabled' => true,
                    'pos_suitable' => true,
                    'sort_order' => 3,
                    'saf_t_payment_code' => '12005',
                    'saf_t_event_code' => '13019',
                    'description' => 'Gavekortbetaling',
                    'background_color' => '#FF6B6B60',
                    'icon_color' => '#FF6B6B',
                ],
                [
                    'name' => 'Tilgodelapp',
                    'code' => 'credit_note',
                    'provider' => 'other',
                    'enabled' => true,
                    'pos_suitable' => true,
                    'sort_order' => 4,
                    'saf_t_payment_code' => '12010',
                    'saf_t_event_code' => '13019',
                    'description' => 'Tilgodelappbetaling',
                    'background_color' => '#4A90E260',
                    'icon_color' => '#4A90E2',
                ],
            ];

            foreach ($defaultMethods as $method) {
                // Check if payment method with this code already exists for this store
                $existing = PaymentMethod::where('store_id', $store->id)
                    ->where('code', $method['code'])
                    ->first();

                if ($existing) {
                    // Update existing record with new defaults (but preserve user changes to name, description, etc.)
                    $existing->update([
                        'provider' => $method['provider'],
                        'provider_method' => $method['provider_method'] ?? null,
                        'pos_suitable' => $method['pos_suitable'] ?? true,
                        'saf_t_payment_code' => $method['saf_t_payment_code'],
                        'saf_t_event_code' => $method['saf_t_event_code'],
                        'sort_order' => $method['sort_order'],
                        'background_color' => $method['background_color'] ?? null,
                        'icon_color' => $method['icon_color'] ?? null,
                    ]);
                } else {
                    PaymentMethod::create(array_merge($method, [
                        'store_id' => $store->id,
                    ]));
                }
            }
        }
    }
}
