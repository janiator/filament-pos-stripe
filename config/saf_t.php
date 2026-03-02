<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SAF-T XSD schema path (Norwegian Cash Register)
    |--------------------------------------------------------------------------
    | Used for validation before test submission to Skatteetaten.
    | Schema: https://github.com/Skatteetaten/saf-t
    | Test submission: https://www.skatteetaten.no/bedrift-og-organisasjon/starte-og-drive/rutiner-regnskap-og-kassasystem/saf-t-regnskap/testinnsending/
    */
    'schema_path' => env('SAF_T_SCHEMA_PATH', resource_path('schemas/Norwegian_SAF-T_Cash_Register_Schema_v_1.00.xsd')),

    /*
    |--------------------------------------------------------------------------
    | Test submission (Skatteetaten Altinn TT02)
    |--------------------------------------------------------------------------
    | Reference number to use when submitting test files via Altinn.
    | Test files must contain synthetic data only (no real company/person data).
    */
    'test_reference_number' => env('SAF_T_TEST_REFERENCE_NUMBER', '2025/5012202'),

    'test_submission_url' => 'https://www.skatteetaten.no/bedrift-og-organisasjon/starte-og-drive/rutiner-regnskap-og-kassasystem/saf-t-regnskap/testinnsending/',

    /*
    |--------------------------------------------------------------------------
    | Synthetic data for anonymized test files
    |--------------------------------------------------------------------------
    | Values to replace real data when preparing a file for test submission.
    */
    'synthetic' => [
        'company_name' => env('SAF_T_SYNTHETIC_COMPANY_NAME', 'Test Bedrift AS'),
        'company_registration_number' => env('SAF_T_SYNTHETIC_ORG_NUMBER', '123456789'),
        'software_company_name' => env('SAF_T_SYNTHETIC_SOFTWARE_COMPANY', 'POSitiv Test'),
    ],
];
