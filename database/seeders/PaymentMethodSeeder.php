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
                    'name' => 'Vipps',
                    'code' => 'vipps',
                    'provider' => 'other',
                    'enabled' => true,
                    'pos_suitable' => true,
                    'sort_order' => 2,
                    'saf_t_payment_code' => '12011',
                    'saf_t_event_code' => '13018',
                    'description' => 'Vippsbetaling (manuell registrering)',
                    'background_color' => '#FFD70060',
                    'icon_color' => '#FFD700',
                ],
                [
                    'name' => 'Kort (Online)',
                    'code' => 'card',
                    'provider' => 'stripe',
                    'provider_method' => 'card',
                    'enabled' => true,
                    'pos_suitable' => false, // Online-only, not for physical POS
                    'sort_order' => 3,
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
                    'sort_order' => 4,
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
                    'sort_order' => 5,
                    'saf_t_payment_code' => '12010',
                    'saf_t_event_code' => '13019',
                    'description' => 'Tilgodelappbetaling',
                    'background_color' => '#4A90E260',
                    'icon_color' => '#4A90E2',
                ],
                [
                    'name' => 'Betaling ved henting',
                    'code' => 'deferred',
                    'provider' => 'other',
                    'enabled' => true,
                    'pos_suitable' => true,
                    'sort_order' => 6,
                    'saf_t_payment_code' => '12010', // Other payment code
                    'saf_t_event_code' => '13019', // Other payment event
                    'description' => 'Kredittsalg - betaling ved henting (f.eks. renseri). Genererer utleveringskvittering per Kassasystemforskriften § 2-8-7.',
                    'background_color' => '#FFA50060',
                    'icon_color' => '#FFA500',
                ],
            ];

            foreach ($defaultMethods as $method) {
                // Check if payment method with this code already exists for this store
                $existing = PaymentMethod::where('store_id', $store->id)
                    ->where('code', $method['code'])
                    ->first();

                if ($existing) {
                    // Update existing record only with missing/null values (preserve user customizations)
                    $updates = [];
                    
                    // Only update provider if not set
                    if (!$existing->provider || $existing->provider === 'other') {
                        $updates['provider'] = $method['provider'];
                    }
                    
                    // Only update provider_method if not set
                    if (!$existing->provider_method && isset($method['provider_method'])) {
                        $updates['provider_method'] = $method['provider_method'];
                    }
                    
                    // Only update pos_suitable if not explicitly set (defaults to true)
                    if ($existing->pos_suitable === null) {
                        $updates['pos_suitable'] = $method['pos_suitable'] ?? true;
                    }
                    
                    // Only update SAF-T codes if not set
                    if (!$existing->saf_t_payment_code) {
                        $updates['saf_t_payment_code'] = $method['saf_t_payment_code'];
                    }
                    if (!$existing->saf_t_event_code) {
                        $updates['saf_t_event_code'] = $method['saf_t_event_code'];
                    }
                    
                    // Only update sort_order if it's the default (0) and we have a different default
                    // This preserves intentional sort_order = 0 settings
                    if ($existing->sort_order === 0 && isset($method['sort_order']) && $method['sort_order'] !== 0) {
                        $updates['sort_order'] = $method['sort_order'];
                    }
                    
                    // Only update colors if not set
                    if (!$existing->background_color && isset($method['background_color'])) {
                        $updates['background_color'] = $method['background_color'];
                    }
                    if (!$existing->icon_color && isset($method['icon_color'])) {
                        $updates['icon_color'] = $method['icon_color'];
                    }
                    
                    // Apply updates only if there are any
                    if (!empty($updates)) {
                        $existing->update($updates);
                    }
                } else {
                    PaymentMethod::create(array_merge($method, [
                        'store_id' => $store->id,
                    ]));
                }
            }
        }
    }
}
