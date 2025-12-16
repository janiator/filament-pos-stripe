<?php

namespace Database\Seeders;

use App\Models\ArticleGroupCode;
use Illuminate\Database\Seeder;

class ArticleGroupCodeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Standard SAF-T article group codes (PredefinedBasicID-04)
        $standardCodes = [
            [
                'code' => '04001',
                'name' => 'Uttak av behandlingstjenester',
                'description' => 'Withdrawal of treatment services',
                'default_vat_percent' => null,
                'is_standard' => true,
                'active' => true,
                'sort_order' => 1,
            ],
            [
                'code' => '04002',
                'name' => 'Uttak av behandlingsvarer',
                'description' => 'Withdrawal of goods used for treatment',
                'default_vat_percent' => null,
                'is_standard' => true,
                'active' => true,
                'sort_order' => 2,
            ],
            [
                'code' => '04003',
                'name' => 'Varesalg',
                'description' => 'Sale of goods',
                'default_vat_percent' => 0.25,
                'is_standard' => true,
                'active' => true,
                'sort_order' => 3,
            ],
            [
                'code' => '04004',
                'name' => 'Salg av behandlingstjenester',
                'description' => 'Sale of treatment services',
                'default_vat_percent' => 0.25,
                'is_standard' => true,
                'active' => true,
                'sort_order' => 4,
            ],
            [
                'code' => '04005',
                'name' => 'Salg av hårklipp',
                'description' => 'Sale of haircuts',
                'default_vat_percent' => 0.25,
                'is_standard' => true,
                'active' => true,
                'sort_order' => 5,
            ],
            [
                'code' => '04006',
                'name' => 'Mat',
                'description' => 'Food',
                'default_vat_percent' => 0.15,
                'is_standard' => true,
                'active' => true,
                'sort_order' => 6,
            ],
            [
                'code' => '04007',
                'name' => 'Øl',
                'description' => 'Beer',
                'default_vat_percent' => 0.25,
                'is_standard' => true,
                'active' => true,
                'sort_order' => 7,
            ],
            [
                'code' => '04008',
                'name' => 'Vin',
                'description' => 'Wine',
                'default_vat_percent' => 0.25,
                'is_standard' => true,
                'active' => true,
                'sort_order' => 8,
            ],
            [
                'code' => '04009',
                'name' => 'Brennevin',
                'description' => 'Spirits',
                'default_vat_percent' => 0.25,
                'is_standard' => true,
                'active' => true,
                'sort_order' => 9,
            ],
            [
                'code' => '04010',
                'name' => 'Rusbrus/Cider',
                'description' => 'Soft drinks/Cider',
                'default_vat_percent' => 0.25,
                'is_standard' => true,
                'active' => true,
                'sort_order' => 10,
            ],
            [
                'code' => '04011',
                'name' => 'Mineralvann (brus)',
                'description' => 'Mineral water (soft drinks)',
                'default_vat_percent' => 0.25,
                'is_standard' => true,
                'active' => true,
                'sort_order' => 11,
            ],
            [
                'code' => '04012',
                'name' => 'Annen drikke (te, kaffe etc)',
                'description' => 'Other drinks (tea, coffee etc)',
                'default_vat_percent' => 0.15,
                'is_standard' => true,
                'active' => true,
                'sort_order' => 12,
            ],
            [
                'code' => '04013',
                'name' => 'Tobakk',
                'description' => 'Tobacco',
                'default_vat_percent' => 0.25,
                'is_standard' => true,
                'active' => true,
                'sort_order' => 13,
            ],
            [
                'code' => '04014',
                'name' => 'Andre varer',
                'description' => 'Other goods',
                'default_vat_percent' => 0.25,
                'is_standard' => true,
                'active' => true,
                'sort_order' => 14,
            ],
            [
                'code' => '04015',
                'name' => 'Inngangspenger',
                'description' => 'Entrance fees',
                'default_vat_percent' => 0.25,
                'is_standard' => true,
                'active' => true,
                'sort_order' => 15,
            ],
            [
                'code' => '04016',
                'name' => 'Inngangspenger fri adgang',
                'description' => 'Entrance fees free access',
                'default_vat_percent' => 0.25,
                'is_standard' => true,
                'active' => true,
                'sort_order' => 16,
            ],
            [
                'code' => '04017',
                'name' => 'Garderobeavgift',
                'description' => 'Cloakroom fee',
                'default_vat_percent' => 0.25,
                'is_standard' => true,
                'active' => true,
                'sort_order' => 17,
            ],
            [
                'code' => '04018',
                'name' => 'Garderobeavgift fri garderobe',
                'description' => 'Cloakroom fee free cloakroom',
                'default_vat_percent' => 0.25,
                'is_standard' => true,
                'active' => true,
                'sort_order' => 18,
            ],
            [
                'code' => '04019',
                'name' => 'Helfullpensjon',
                'description' => 'Full board',
                'default_vat_percent' => 0.15,
                'is_standard' => true,
                'active' => true,
                'sort_order' => 19,
            ],
            [
                'code' => '04020',
                'name' => 'Halvpensjon',
                'description' => 'Half board',
                'default_vat_percent' => 0.15,
                'is_standard' => true,
                'active' => true,
                'sort_order' => 20,
            ],
            [
                'code' => '04021',
                'name' => 'Overnatting med frokost',
                'description' => 'Accommodation with breakfast',
                'default_vat_percent' => 0.15,
                'is_standard' => true,
                'active' => true,
                'sort_order' => 21,
            ],
            [
                'code' => '04999',
                'name' => 'Øvrige',
                'description' => 'Other',
                'default_vat_percent' => 0.25,
                'is_standard' => true,
                'active' => true,
                'sort_order' => 99,
            ],
        ];

        // Create global standard codes (without store_id/stripe_account_id)
        foreach ($standardCodes as $code) {
            ArticleGroupCode::firstOrCreate(
                [
                    'code' => $code['code'],
                    'stripe_account_id' => null,
                ],
                [
                    'store_id' => null,
                    'name' => $code['name'],
                    'description' => $code['description'],
                    'default_vat_percent' => $code['default_vat_percent'],
                    'is_standard' => $code['is_standard'],
                    'active' => $code['active'],
                    'sort_order' => $code['sort_order'],
                ]
            );
        }
    }
}
