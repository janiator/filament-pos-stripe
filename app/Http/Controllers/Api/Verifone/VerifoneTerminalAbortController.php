<?php

namespace App\Http\Controllers\Api\Verifone;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\Verifone\StoreVerifoneTerminalAbortRequest;
use App\Models\Store;
use App\Models\VerifoneTerminal;
use App\Models\VerifoneTerminalPayment;
use App\Services\Verifone\VerifonePosCloudService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class VerifoneTerminalAbortController extends BaseApiController
{
    public function __construct(
        private readonly VerifonePosCloudService $verifonePosCloudService
    ) {}

    public function store(
        Store $store,
        VerifoneTerminal $terminal,
        StoreVerifoneTerminalAbortRequest $request
    ): JsonResponse {
        $this->authorizeTenant($request, $store);

        if ($terminal->store_id !== $store->id) {
            return response()->json([
                'message' => 'Verifone terminal not found for this store.',
            ], 404);
        }

        $validated = $request->validated();
        $serviceId = $validated['service_id'];
        $saleId = $validated['sale_id'];

        try {
            $providerResponse = $this->verifonePosCloudService->abort(
                $store,
                $serviceId,
                $saleId,
                $terminal->terminal_identifier
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => 'Failed to abort Verifone terminal request.',
                'error' => $exception->getMessage(),
            ], 422);
        }

        VerifoneTerminalPayment::query()
            ->where('store_id', $store->id)
            ->where('service_id', $serviceId)
            ->update([
                'status' => 'canceled',
                'failed_at' => now(),
                'status_payload' => $providerResponse['response_payload'],
            ]);

        return response()->json([
            'success' => true,
            'provider' => 'verifone',
            'status' => 'canceled',
            'serviceId' => $serviceId,
            'terminalId' => $terminal->terminal_identifier,
            'raw' => $providerResponse['response_payload'],
        ]);
    }
}
