<?php

namespace App\Actions\SafT;

/**
 * Replace identifiable data in SAF-T XML with synthetic values
 * so the file is safe for test submission to Skatteetaten.
 *
 * Test files must not contain real company names, addresses, person names,
 * contact info, or account/IBAN. See:
 * https://www.skatteetaten.no/bedrift-og-organisasjon/starte-og-drive/rutiner-regnskap-og-kassasystem/saf-t-regnskap/testinnsending/
 */
class AnonymizeSafTForTestSubmission
{
    public function __invoke(string $xmlContent): string
    {
        $synthetic = config('saf_t.synthetic', []);
        $companyName = $synthetic['company_name'] ?? 'Test Bedrift AS';
        $orgNumber = $synthetic['company_registration_number'] ?? '123456789';
        $softwareCompany = $synthetic['software_company_name'] ?? 'POSitiv Test';

        // Replace common element values that may identify the sender.
        // Our generator uses PascalCase (AuditFile, Header, CompanyName, etc.)
        $replacements = [
            // Header / company identifiers
            '/<CompanyName>[^<]*<\/CompanyName>/' => '<CompanyName>'.htmlspecialchars($companyName, ENT_XML1, 'UTF-8').'</CompanyName>',
            '/<TaxEntity>[^<]*<\/TaxEntity>/' => '<TaxEntity>'.htmlspecialchars($companyName, ENT_XML1, 'UTF-8').'</TaxEntity>',
            '/<CompanyRegistrationNumber>[^<]*<\/CompanyRegistrationNumber>/' => '<CompanyRegistrationNumber>'.htmlspecialchars($orgNumber, ENT_XML1, 'UTF-8').'</CompanyRegistrationNumber>',
            '/<SoftwareCompanyName>[^<]*<\/SoftwareCompanyName>/' => '<SoftwareCompanyName>'.htmlspecialchars($softwareCompany, ENT_XML1, 'UTF-8').'</SoftwareCompanyName>',
            '/<CashRegisterDescription>[^<]*<\/CashRegisterDescription>/' => '<CashRegisterDescription>'.htmlspecialchars($companyName, ENT_XML1, 'UTF-8').'</CashRegisterDescription>',
        ];

        $out = $xmlContent;
        foreach ($replacements as $pattern => $replacement) {
            $out = preg_replace($pattern, $replacement, $out);
        }

        return $out;
    }
}
