<?php

namespace Database\Seeders;

use App\Models\QuantityUnit;
use Illuminate\Database\Seeder;
use Illuminate\Database\UniqueConstraintViolationException;

class QuantityUnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates a single global set of 10 standard quantity units (store_id and
     * stripe_account_id null). All stores use these via the API/Filament scope.
     * Uses firstOrCreate so existing units are left unchanged. If the seeder
     * runs concurrently, the unique index prevents duplicates; we catch and continue.
     */
    public function run(): void
    {
        $standardUnits = [
            ['name' => 'Piece', 'symbol' => 'stk', 'description' => 'Per item/piece'],
            ['name' => 'Kilogram', 'symbol' => 'kg', 'description' => 'Per kilogram (weight)'],
            ['name' => 'Gram', 'symbol' => 'g', 'description' => 'Per gram (weight)'],
            ['name' => 'Meter', 'symbol' => 'm', 'description' => 'Per meter (length)'],
            ['name' => 'Centimeter', 'symbol' => 'cm', 'description' => 'Per centimeter (length)'],
            ['name' => 'Liter', 'symbol' => 'l', 'description' => 'Per liter (volume)'],
            ['name' => 'Milliliter', 'symbol' => 'ml', 'description' => 'Per milliliter (volume)'],
            ['name' => 'Hour', 'symbol' => 't', 'description' => 'Per hour (time)'],
            ['name' => 'Square Meter', 'symbol' => 'm²', 'description' => 'Per square meter (area)'],
            ['name' => 'Cubic Meter', 'symbol' => 'm³', 'description' => 'Per cubic meter (volume)'],
        ];

        foreach ($standardUnits as $unit) {
            $this->firstOrCreateUnit(
                [
                    'store_id' => null,
                    'stripe_account_id' => null,
                    'name' => $unit['name'],
                    'symbol' => $unit['symbol'],
                ],
                [
                    'description' => $unit['description'],
                    'is_standard' => true,
                    'active' => true,
                ]
            );
        }
    }

    /**
     * Create or find a quantity unit. Catches duplicate key from concurrent seeder runs.
     *
     * @param  array<string, mixed>  $search
     * @param  array<string, mixed>  $attributes
     */
    private function firstOrCreateUnit(array $search, array $attributes): void
    {
        try {
            QuantityUnit::firstOrCreate($search, $attributes);
        } catch (UniqueConstraintViolationException) {
            // Concurrent run already inserted this row; ignore.
        }
    }
}
