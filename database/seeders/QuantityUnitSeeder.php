<?php

namespace Database\Seeders;

use App\Models\QuantityUnit;
use App\Models\Store;
use Illuminate\Database\Seeder;

class QuantityUnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Standard quantity units that should be available for all stores
        $standardUnits = [
            [
                'name' => 'Piece',
                'symbol' => 'stk',
                'description' => 'Per item/piece',
                'is_standard' => true,
                'active' => true,
            ],
            [
                'name' => 'Kilogram',
                'symbol' => 'kg',
                'description' => 'Per kilogram (weight)',
                'is_standard' => true,
                'active' => true,
            ],
            [
                'name' => 'Gram',
                'symbol' => 'g',
                'description' => 'Per gram (weight)',
                'is_standard' => true,
                'active' => true,
            ],
            [
                'name' => 'Meter',
                'symbol' => 'm',
                'description' => 'Per meter (length)',
                'is_standard' => true,
                'active' => true,
            ],
            [
                'name' => 'Centimeter',
                'symbol' => 'cm',
                'description' => 'Per centimeter (length)',
                'is_standard' => true,
                'active' => true,
            ],
            [
                'name' => 'Liter',
                'symbol' => 'l',
                'description' => 'Per liter (volume)',
                'is_standard' => true,
                'active' => true,
            ],
            [
                'name' => 'Milliliter',
                'symbol' => 'ml',
                'description' => 'Per milliliter (volume)',
                'is_standard' => true,
                'active' => true,
            ],
            [
                'name' => 'Hour',
                'symbol' => 't',
                'description' => 'Per hour (time)',
                'is_standard' => true,
                'active' => true,
            ],
            [
                'name' => 'Square Meter',
                'symbol' => 'm²',
                'description' => 'Per square meter (area)',
                'is_standard' => true,
                'active' => true,
            ],
            [
                'name' => 'Cubic Meter',
                'symbol' => 'm³',
                'description' => 'Per cubic meter (volume)',
                'is_standard' => true,
                'active' => true,
            ],
        ];

        // Get all stores or create for null store_id (global standard units)
        $stores = Store::all();

        foreach ($stores as $store) {
            foreach ($standardUnits as $unit) {
                QuantityUnit::firstOrCreate(
                    [
                        'store_id' => $store->id,
                        'stripe_account_id' => $store->stripe_account_id,
                        'name' => $unit['name'],
                        'symbol' => $unit['symbol'],
                    ],
                    [
                        'description' => $unit['description'],
                        'is_standard' => $unit['is_standard'],
                        'active' => $unit['active'],
                    ]
                );
            }
        }

        // Also create global standard units (without store_id) for stores that don't have them yet
        foreach ($standardUnits as $unit) {
            QuantityUnit::firstOrCreate(
                [
                    'store_id' => null,
                    'stripe_account_id' => null,
                    'name' => $unit['name'],
                    'symbol' => $unit['symbol'],
                ],
                [
                    'description' => $unit['description'],
                    'is_standard' => $unit['is_standard'],
                    'active' => $unit['active'],
                ]
            );
        }
    }
}
