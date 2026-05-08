<?php

namespace App\Http\Controllers\Api\PowerOffice;

use App\Enums\AddonType;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\PowerOffice\InitPowerOfficeOnboardingRequest;
use App\Http\Requests\PowerOffice\PowerOfficeOnboardingCallbackRequest;
use App\Models\Addon;
use App\Models\PowerOfficeIntegration;
use App\Models\Store;
use App\Services\PowerOffice\PowerOfficeOnboardingService;
use Illuminate\Http\JsonResponse;

class PowerOfficeOnboardingController extends BaseApiController
{
    public function init(InitPowerOfficeOnboardingRequest $request, PowerOfficeOnboardingService $onboarding): JsonResponse
    {
        $store = $this->getTenantStore($request);
        if (! $store instanceof Store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        if (! Addon::storeHasActiveAddon($store->getKey(), AddonType::PowerOfficeGo)) {
            return response()->json(['message' => 'PowerOffice add-on is not enabled for this store.'], 403);
        }

        $integration = PowerOfficeIntegration::query()->firstOrCreate(
            ['store_id' => $store->getKey()],
            [],
        );

        try {
            $url = $onboarding->initiate($store, $integration);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'onboarding_url' => $url,
        ]);
    }

    public function callback(PowerOfficeOnboardingCallbackRequest $request, PowerOfficeOnboardingService $onboarding): JsonResponse
    {
        try {
            $onboarding->completeFromCallback($request->validated());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->json(['message' => 'Onboarding completed']);
    }
}
