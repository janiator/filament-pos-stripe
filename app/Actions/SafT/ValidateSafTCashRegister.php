<?php

namespace App\Actions\SafT;

use DOMDocument;
use LibXMLError;

/**
 * Validate SAF-T Cash Register XML against the official Norwegian XSD schema.
 *
 * @see https://github.com/Skatteetaten/saf-t
 * @see https://www.skatteetaten.no/bedrift-og-organisasjon/starte-og-drive/rutiner-regnskap-og-kassasystem/saf-t-regnskap/testinnsending/
 */
class ValidateSafTCashRegister
{
    public function __construct(
        protected ?string $schemaPath = null
    ) {
        $this->schemaPath = $schemaPath ?? config('saf_t.schema_path');
    }

    /**
     * Validate XML string against the configured XSD.
     *
     * @return array{valid: bool, errors: array<int, array{message: string, line?: int, column?: int}>}
     */
    public function __invoke(string $xmlContent): array
    {
        $errors = [];
        $doc = new DOMDocument('1.0', 'UTF-8');

        if (! $this->schemaPath || ! is_readable($this->schemaPath)) {
            return [
                'valid' => false,
                'errors' => [
                    ['message' => 'XSD schema file not found or not readable: '.($this->schemaPath ?? 'null').'. Set SAF_T_SCHEMA_PATH or ensure resources/schemas/Norwegian_SAF-T_Cash_Register_Schema_v_1.00.xsd exists.'],
                ],
            ];
        }

        $prev = libxml_use_internal_errors(true);
        libxml_clear_errors();

        if (! @$doc->loadXML($xmlContent)) {
            foreach (libxml_get_errors() as $err) {
                $errors[] = $this->formatLibXmlError($err);
            }
            libxml_clear_errors();
            libxml_use_internal_errors($prev);

            return ['valid' => false, 'errors' => $errors];
        }

        if (! @$doc->schemaValidate($this->schemaPath)) {
            foreach (libxml_get_errors() as $err) {
                $errors[] = $this->formatLibXmlError($err);
            }
            libxml_clear_errors();
            libxml_use_internal_errors($prev);

            return ['valid' => false, 'errors' => $errors];
        }

        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        return ['valid' => true, 'errors' => []];
    }

    /**
     * Validate an XML file from path or storage.
     */
    public function validateFile(string $path): array
    {
        if (! is_readable($path)) {
            return ['valid' => false, 'errors' => [['message' => "File not readable: {$path}"]]];
        }
        $content = file_get_contents($path);

        return $this->__invoke($content);
    }

    private function formatLibXmlError(LibXMLError $err): array
    {
        $out = ['message' => trim($err->message)];
        if ($err->line) {
            $out['line'] = $err->line;
        }
        if ($err->column) {
            $out['column'] = $err->column;
        }

        return $out;
    }
}
