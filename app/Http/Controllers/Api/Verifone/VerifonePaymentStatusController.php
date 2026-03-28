<?php

namespace App\Http\Controllers\Api\Verifone;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\Verifone\StoreVerifonePaymentStatusRequest;
use App\Models\Store;
use App\Models\VerifoneTerminalPayment;
use App\Services\Verifone\VerifonePosCloudService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class VerifonePaymentStatusController extends BaseApiController
{
    public function __construct(
        private readonly VerifonePosCloudService $verifonePosCloudService
    ) {}

    public function store(Store $store, string $serviceId, StoreVerifonePaymentStatusRequest $request): JsonResponse
    {
        $this->authorizeTenant($request, $store);

        $payment = VerifoneTerminalPayment::query()
            ->where('store_id', $store->id)
            ->where('service_id', $serviceId)
            ->first();

        if (! $payment) {
            return response()->json([
                'message' => 'Verifone payment not found for this store.',
            ], 404);
        }

        $validated = $request->validated();

        try {
            $providerResponse = $this->verifonePosCloudService->transactionStatus(
                $store,
                $payment->service_id,
                $payment->sale_id,
                $payment->poiid,
                $validated['message_reference_service_id'] ?? null,
                $validated['message_reference_sale_id'] ?? null,
                $validated['message_reference_poiid'] ?? null
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => 'Failed to fetch Verifone payment status.',
                'error' => $exception->getMessage(),
            ], 422);
        }

        $normalized = $providerResponse['normalized_status'];
        $status = $normalized['status'];

        $payment->update([
            'status' => $status,
            'status_payload' => $providerResponse['response_payload'],
            'provider_payment_reference' => $normalized['providerPaymentReference'],
            'provider_transaction_id' => $normalized['providerTransactionId'],
            'completed_at' => $status === 'succeeded' ? now() : $payment->completed_at,
            'failed_at' => in_array($status, ['failed', 'canceled'], true) ? now() : $payment->failed_at,
        ]);

        return response()->json([
            'success' => true,
            'provider' => 'verifone',
            'status' => $status,
            'providerStatus' => $normalized['providerStatus'],
            'providerPaymentReference' => $normalized['providerPaymentReference'],
            'providerTransactionId' => $normalized['providerTransactionId'],
            'serviceId' => $payment->service_id,
            'terminalId' => $payment->poiid,
            'receipt' => $normalized['receipt'],
            'raw' => $providerResponse['response_payload'],
        ]);
    }
}
