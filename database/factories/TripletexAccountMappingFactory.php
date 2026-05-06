<?php

namespace Database\Factories;

use App\Enums\PowerOfficeMappingBasis;
use App\Models\TripletexAccountMapping;
use App\Models\TripletexIntegration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TripletexAccountMapping>
 */
class TripletexAccountMappingFactory extends Factory
{
    protected $model = TripletexAccountMapping::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tripletex_integration_id' => TripletexIntegration::factory(),
            'basis_type' => PowerOfficeMappingBasis::Vat,
            'basis_key' => '25',
            'basis_label' => '25% VAT',
            'sales_account_no' => '3001',
            'vat_account_no' => '2700',
            'fees_account_no' => null,
            'tips_account_no' => '3002',
            'cash_account_no' => '1900',
            'card_clearing_account_no' => '1901',
            'rounding_account_no' => '9999',
            'is_active' => true,
        ];
    }
}
