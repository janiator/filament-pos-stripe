<?php

namespace App\Services\Verifone;

use App\Models\Store;
use App\Models\VerifoneTerminal;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class VerifonePosCloudService
{
    public function processPayment(
        Store $store,
        VerifoneTerminal $terminal,
        int $amountMinor,
        string $currency,
        string $serviceId,
        ?string $saleId = null,
        ?string $operatorId = null
    ): array {
        $saleIdentifier = $saleId ?? $terminal->sale_id ?? $store->verifone_sale_id ?? (string) $store->id;
        $operatorIdentifier = $operatorId ?? $terminal->operator_id ?? $store->verifone_operator_id ?? (string) $store->id;
        $timestamp = now()->utc()->format('Y-m-d\TH:i:s\Z');

        $payload = [
            'MessageHeader' => [
                'MessageClass' => 'SERVICE',
                'MessageCategory' => 'PAYMENT',
                'MessageType' => 'REQUEST',
                'ServiceID' => $serviceId,
                'SaleID' => $saleIdentifier,
                'POIID' => $terminal->terminal_identifier,
            ],
            'PaymentRequest' => [
                'SaleData' => [
                    'OperatorID' => $operatorIdentifier,
                    'SaleTransactionID' => [
                        'TransactionID' => $serviceId,
                        'TimeStamp' => $timestamp,
                    ],
                ],
                'PaymentTransaction' => [
                    'AmountsReq' => [
                        'Currency' => strtoupper($currency),
                        'RequestedAmount' => round($amountMinor / 100, 2),
                    ],
                ],
                'PaymentData' => [
                    'PaymentType' => 'NORMAL',
                    'SplitPaymentFlag' => false,
                ],
            ],
        ];

        $response = $this->post($store, '/oidc/poscloud/nexo/payment', $payload, $terminal->site_entity_id);

        return [
            'request_payload' => $payload,
            'response_payload' => $response->json(),
        ];
    }

    public function transactionStatus(
        Store $store,
        string $serviceId,
        string $saleId,
        string $poiid,
        ?string $messageReferenceServiceId = null,
        ?string $messageReferenceSaleId = null,
        ?string $messageReferencePoiid = null
    ): array {
        $payload = [
            'MessageHeader' => [
                'MessageClass' => 'SERVICE',
                'MessageCategory' => 'TRANSACTIONSTATUS',
                'MessageType' => 'REQUEST',
                'ServiceID' => $serviceId,
                'SaleID' => $saleId,
                'POIID' => $poiid,
            ],
            'TransactionStatusRequest' => [
                'MessageReference' => [
                    'messageCategory' => 'PAYMENT',
                    'serviceID' => $messageReferenceServiceId ?? $serviceId,
                    'deviceID' => $messageReferencePoiid ?? $poiid,
                    'saleID' => $messageReferenceSaleId ?? $saleId,
                    'poiid' => $messageReferencePoiid ?? $poiid,
                ],
                'ReceiptReprintFlag' => true,
                'DocumentQualifier' => 'SALERECEIPT',
            ],
        ];

        $response = $this->post($store, '/oidc/poscloud/nexo/transactionstatus', $payload, $store->verifone_site_entity_id);
        $json = $response->json();

        return [
            'request_payload' => $payload,
            'response_payload' => $json,
            'normalized_status' => $this->normalizeStatus($json),
        ];
    }

    public function abort(
        Store $store,
        string $serviceId,
        string $saleId,
        string $poiid
    ): array {
        $payload = [
            'MessageHeader' => [
                'MessageClass' => 'SERVICE',
                'MessageCategory' => 'ABORT',
                'MessageType' => 'REQUEST',
                'ServiceID' => $serviceId,
                'SaleID' => $saleId,
                'POIID' => $poiid,
            ],
            'AbortRequest' => [
                'AbortReason' => 'MerchantAbort',
                'MessageReference' => [
                    'MessageCategory' => 'PAYMENT',
                    'ServiceID' => $serviceId,
                    'SaleID' => $saleId,
                    'POIID' => $poiid,
                ],
            ],
        ];

        $response = $this->post($store, '/oidc/poscloud/nexo/v2/abort', $payload, $store->verifone_site_entity_id);

        return [
            'request_payload' => $payload,
            'response_payload' => $response->json(),
        ];
    }

    public function normalizeStatus(array $payload): array
    {
        $statusResponse = $payload['TransactionStatusResponse']['Response'] ?? [];
        $result = strtoupper((string) ($statusResponse['Result'] ?? ''));
        $errorCondition = $statusResponse['ErrorCondition'] ?? null;
        $additionalResponse = $statusResponse['AdditionalResponse'] ?? null;

        $paymentResponse = $payload['TransactionStatusResponse']['RepeatedMessageResponse']['RepeatedResponseMessageBody']['PaymentResponse'] ?? null;
        $paymentResult = strtoupper((string) ($paymentResponse['Response']['Result'] ?? ''));
        $saleToPoiDataRaw = $paymentResponse['SaleData']['SaleToPOIData'] ?? null;
        $saleToPoiData = null;

        if (is_string($saleToPoiDataRaw) && $saleToPoiDataRaw !== '') {
            $decoded = json_decode($saleToPoiDataRaw, true);
            if (is_array($decoded)) {
                $saleToPoiData = $decoded;
            }
        }

        $status = 'unknown';

        if ($result === 'FAILURE' || $paymentResult === 'FAILURE') {
            $status = 'failed';
        } elseif ($result === 'SUCCESS' && $paymentResponse === null) {
            $status = 'in_progress';
        } elseif ($result === 'SUCCESS' && $paymentResult === 'SUCCESS') {
            $capturedState = strtoupper((string) ($saleToPoiData['r'] ?? $saleToPoiData['rc'] ?? ''));
            $status = in_array($capturedState, ['CANCELED', 'CANCELLED', 'DECLINED', 'FAILURE', 'FAILED'], true)
                ? 'failed'
                : 'succeeded';
        } elseif ($result === 'PARTIAL') {
            $status = 'pending';
        }

        if (is_string($errorCondition) && str_contains(strtolower($errorCondition), 'cancel')) {
            $status = 'canceled';
        }

        return [
            'status' => $status,
            'providerStatus' => [
                'result' => $result ?: null,
                'paymentResult' => $paymentResult ?: null,
                'errorCondition' => $errorCondition,
                'additionalResponse' => $additionalResponse,
            ],
            'providerPaymentReference' => $saleToPoiData['i']
                ?? $paymentResponse['PaymentResult']['PaymentAcquirerData']['AcquirerTransactionID']['TransactionID']
                ?? $saleToPoiData['m']
                ?? null,
            'providerTransactionId' => $saleToPoiData['c']
                ?? $saleToPoiData['td']
                ?? $paymentResponse['POIData']['POITransactionID']['TransactionID']
                ?? null,
            'receipt' => $paymentResponse['PaymentReceipt'] ?? null,
        ];
    }

    protected function post(Store $store, string $path, array $payload, ?string $siteEntityId = null): Response
    {
        $baseUrl = rtrim((string) ($store->verifone_api_base_url ?: config('services.verifone.base_url')), '/');
        $encodedBasicAuthOverride = trim((string) $store->verifone_encoded_basic_auth);
        $userUid = (string) ($store->verifone_user_uid ?: config('services.verifone.user_uid'));
        $apiKey = (string) ($store->verifone_api_key ?: config('services.verifone.api_key'));

        if ($baseUrl === '') {
            throw new RuntimeException('Verifone credentials are not configured for this store.');
        }

        if ($encodedBasicAuthOverride === '' && ($userUid === '' || $apiKey === '')) {
            throw new RuntimeException('Verifone credentials are not configured for this store.');
        }

        $authorizationHeader = 'Basic '.base64_encode($userUid.':'.$apiKey);
        if ($encodedBasicAuthOverride !== '') {
            $authorizationHeader = str_starts_with(strtolower($encodedBasicAuthOverride), 'basic ')
                ? $encodedBasicAuthOverride
                : 'Basic '.$encodedBasicAuthOverride;
        }

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => $authorizationHeader,
        ];

        $siteEntity = $siteEntityId ?: $store->verifone_site_entity_id;
        if (! empty($siteEntity)) {
            $headers['x-site-entity-id'] = $siteEntity;
        }

        if ((bool) $store->verifone_terminal_simulator) {
            $headers['x-terminal-simulator'] = 'true';
        }

        $response = Http::withHeaders($headers)
            ->timeout((int) config('services.verifone.timeout', 15))
            ->post($baseUrl.$path, $payload);

        if ($response->failed()) {
            throw new RuntimeException('Verifone API call failed: '.$response->status().' '.$response->body());
        }

        return $response;
    }
}
