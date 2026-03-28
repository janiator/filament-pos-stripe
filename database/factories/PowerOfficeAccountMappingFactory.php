<?php

namespace Database\Factories;

use App\Enums\PowerOfficeMappingBasis;
use App\Models\PowerOfficeAccountMapping;
use App\Models\PowerOfficeIntegration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PowerOfficeAccountMapping>
 */
class PowerOfficeAccountMappingFactory extends Factory
{
    protected $model = PowerOfficeAccountMapping::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'power_office_integration_id' => PowerOfficeIntegration::factory(),
            'basis_type' => PowerOfficeMappingBasis::Vat,
            'basis_key' => '25',
            'basis_label' => '25% VAT',
            'sales_account_no' => '3000',
            'vat_account_no' => '2700',
            'fees_account_no' => null,
            'tips_account_no' => '3001',
            'cash_account_no' => '1920',
            'card_clearing_account_no' => '1921',
            'rounding_account_no' => null,
            'is_active' => true,
        ];
    }
}
