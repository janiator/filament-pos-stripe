<?php

namespace App\Http\Controllers\Api\Verifone;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\Verifone\StoreVerifonePaymentRequest;
use App\Models\Store;
use App\Models\VerifoneTerminal;
use App\Models\VerifoneTerminalPayment;
use App\Services\Verifone\VerifonePosCloudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use RuntimeException;

class VerifonePaymentsController extends BaseApiController
{
    public function __construct(
        private readonly VerifonePosCloudService $verifonePosCloudService
    ) {}

    public function store(Store $store, StoreVerifonePaymentRequest $request): JsonResponse
    {
        $this->authorizeTenant($request, $store);

        $validated = $request->validated();
        $terminalQuery = VerifoneTerminal::query()->where('store_id', $store->id);
        $terminal = null;

        if (isset($validated['terminal_id'])) {
            $terminal = (clone $terminalQuery)
                ->where('id', $validated['terminal_id'])
                ->first();
        }

        if (! $terminal && isset($validated['terminal_poiid'])) {
            $terminal = (clone $terminalQuery)
                ->where('terminal_identifier', $validated['terminal_poiid'])
                ->first();
        }

        if (! $terminal) {
            return response()->json([
                'message' => 'Verifone terminal not found for this store.',
            ], 404);
        }

        $serviceId = $validated['service_id'] ?? Str::upper(Str::random(8));

        try {
            $providerResponse = $this->verifonePosCloudService->processPayment(
                $store,
                $terminal,
                (int) $validated['amount'],
                strtoupper($validated['currency'] ?? 'NOK'),
                $serviceId,
                $validated['sale_id'] ?? null,
                $validated['operator_id'] ?? null
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => 'Failed to initiate Verifone payment.',
                'error' => $exception->getMessage(),
            ], 422);
        }

        $payment = VerifoneTerminalPayment::updateOrCreate(
            [
                'store_id' => $store->id,
                'service_id' => $serviceId,
            ],
            [
                'verifone_terminal_id' => $terminal->id,
                'pos_session_id' => $validated['pos_session_id'] ?? null,
                'pos_device_id' => $validated['pos_device_id'] ?? null,
                'sale_id' => $validated['sale_id'] ?? $terminal->sale_id ?? $store->verifone_sale_id ?? (string) $store->id,
                'poiid' => $terminal->terminal_identifier,
                'amount_minor' => (int) $validated['amount'],
                'currency' => strtoupper($validated['currency'] ?? 'NOK'),
                'status' => 'pending',
                'provider_message' => $validated['description'] ?? null,
                'request_payload' => $providerResponse['request_payload'],
                'response_payload' => $providerResponse['response_payload'],
            ]
        );

        return response()->json([
            'success' => true,
            'provider' => 'verifone',
            'status' => 'pending',
            'serviceId' => $payment->service_id,
            'saleId' => $payment->sale_id,
            'terminalId' => $payment->poiid,
            'providerPaymentReference' => null,
            'raw' => $providerResponse['response_payload'],
        ], 201);
    }
}
