# SAF-T test submission to Skatteetaten

System suppliers can test SAF-T files by submitting them to Skatteetaten via **Altinn TT02** (test environment).

## Official information

- **Test submission (Skatteetaten):**  
  [Testinnsending av SAF-T](https://www.skatteetaten.no/bedrift-og-organisasjon/starte-og-drive/rutiner-regnskap-og-kassasystem/saf-t-regnskap/testinnsending/)
- **Reference number for test submission:** `2025/5012202`
- **Kjøreplan (PDF):** Download from the page above for step-by-step login and submission in Altinn TT02.
- **Schemas and code lists:**  
  [Skatteetaten/saf-t](https://github.com/Skatteetaten/saf-t)

## Requirements for test files

1. **Synthetic data only**  
   Files must contain **synthetic (artificial) test data**. They must **not** contain:
   - Real company name or organisation number
   - Real addresses, person names, contact info (e-mail, phone)
   - Real account numbers, IBAN, etc.

2. **Validate before sending**  
   Validate the file against the official schema (e.g. with the app’s “Validate SAF-T” action or Skatteetaten’s validator) so the format is correct before upload.

3. **Submit via Altinn TT02**  
   Use the Altinn test environment (TT02) and the reference number above. Testing should be coordinated with the system supplier; end customers do not need to participate in test submission.

## In this application

- **Validate SAF-T:** POS device → “Validate SAF-T”. Generates SAF-T for a date range and validates it against the Norwegian Cash Register XSD (from [Skatteetaten/saf-t](https://github.com/Skatteetaten/saf-t)). If the current export does not match the schema exactly, validation will report errors; the generator can be aligned with the schema in a later update.
- **Prepare for test submission:** POS device → “Prepare for test submission”. Generates SAF-T, **anonymises** company name, org number and software company name (using values from `config/saf_t.php` / `.env`), and offers a download. Use this file when submitting to Skatteetaten (reference number in config: `SAF_T_TEST_REFERENCE_NUMBER`, default `2025/5012202`).

## Configuration

See `config/saf_t.php` and optional `.env`:

- `SAF_T_SCHEMA_PATH` – Path to the XSD (default: `resources/schemas/Norwegian_SAF-T_Cash_Register_Schema_v_1.00.xsd`).
- `SAF_T_TEST_REFERENCE_NUMBER` – Reference number for test submission (default: `2025/5012202`).
- `SAF_T_SYNTHETIC_COMPANY_NAME`, `SAF_T_SYNTHETIC_ORG_NUMBER`, `SAF_T_SYNTHETIC_SOFTWARE_COMPANY` – Values used when anonymising for test submission.
