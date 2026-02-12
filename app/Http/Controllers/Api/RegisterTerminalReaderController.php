<?php

namespace App\Http\Controllers\Api;

use App\Models\TerminalLocation;
use App\Models\TerminalReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Stripe\Exception\ApiErrorException;

class RegisterTerminalReaderController extends BaseApiController
{
    /**
     * Register a reader from registration code to the given location (Stripe + DB).
     * If the reader was registered to another store, removes it from that store.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $store = $this->getTenantStore($request);

        if (! $store) {
            return response()->json(['message' => 'Store not found.'], 404);
        }

        $this->authorizeTenant($request, $store);

        $validated = $request->validate([
            'registration_code' => ['required', 'string', 'max:255'],
            'terminal_location_id' => ['nullable', 'integer', Rule::exists('terminal_locations', 'id')],
            'stripe_location_id' => ['nullable', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        if (isset($validated['terminal_location_id'])) {
            $location = TerminalLocation::where('id', $validated['terminal_location_id'])
                ->where('store_id', $store->id)
                ->firstOrFail();
        } elseif (! empty($validated['stripe_location_id'] ?? null)) {
            $location = TerminalLocation::where('stripe_location_id', $validated['stripe_location_id'])
                ->where('store_id', $store->id)
                ->firstOrFail();
        } else {
            return response()->json([
                'message' => 'Either terminal_location_id or stripe_location_id is required.',
            ], 422);
        }

        $params = [
            'registration_code' => $validated['registration_code'],
            'location' => $location->stripe_location_id,
        ];
        if (! empty($validated['label'] ?? null)) {
            $params['label'] = $validated['label'];
        }

        try {
            $reader = $store->registerTerminalReader($params, true);
        } catch (ApiErrorException $e) {
            return response()->json([
                'message' => 'Failed to register reader in Stripe.',
                'error' => $e->getMessage(),
            ], 422);
        }

        $serialNumber = $reader->serial_number ?? null;

        // Remove from previous store(s): same serial_number, different store
        if ($serialNumber !== null && $serialNumber !== '') {
            $otherReaders = TerminalReader::where('serial_number', $serialNumber)
                ->where('store_id', '!=', $store->id)
                ->get();
            foreach ($otherReaders as $other) {
                $other->delete();
            }
        }

        $deviceType = $reader->device_type ?? '';
        $record = TerminalReader::updateOrCreate(
            ['stripe_reader_id' => $reader->id],
            [
                'store_id'             => $store->id,
                'terminal_location_id' => $location->id,
                'serial_number'        => $serialNumber,
                'label'                => $reader->label ?? $reader->id,
                'device_type'          => $reader->device_type ?? null,
                'status'               => $reader->status ?? null,
                'tap_to_pay'           => str_contains($deviceType, 'tap_to_pay'),
            ]
        );

        return response()->json([
            'reader' => [
                'id' => $record->id,
                'stripe_reader_id' => $record->stripe_reader_id,
                'serial_number' => $record->serial_number,
                'label' => $record->label,
                'device_type' => $record->device_type,
                'status' => $record->status,
                'terminal_location_id' => $record->terminal_location_id,
            ],
        ], 201);
    }
}
