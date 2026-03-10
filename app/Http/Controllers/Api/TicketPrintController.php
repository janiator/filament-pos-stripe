<?php

namespace App\Http\Controllers\Api;

use App\Enums\AddonType;
use App\Http\Requests\Api\PrintFreeTicketRequest;
use App\Http\Requests\Api\PrintTicketRequest;
use App\Models\Addon;
use App\Models\ReceiptPrinter;
use App\Models\Store;
use App\Services\TicketPrintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use RuntimeException;

class TicketPrintController extends BaseApiController
{
    public function __construct(protected TicketPrintService $ticketPrintService) {}

    public function printFreeTicket(PrintFreeTicketRequest $request): JsonResponse|HttpResponse
    {
        $store = $this->getAuthorizedStore($request);
        if ($store instanceof JsonResponse) {
            return $store;
        }

        $printer = $this->resolvePrinter($store, (int) $request->integer('printer_id'));
        if ($printer instanceof JsonResponse) {
            return $printer;
        }

        try {
            $xml = $this->ticketPrintService->renderFreeTicket($store->id, $printer, $request->validated());
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response($xml, 200)->header('Content-Type', 'text/xml; charset=UTF-8');
    }

    public function printTicket(PrintTicketRequest $request): JsonResponse|HttpResponse
    {
        $store = $this->getAuthorizedStore($request);
        if ($store instanceof JsonResponse) {
            return $store;
        }

        if (! Addon::storeHasActiveAddon($store->id, AddonType::MeranoBooking)) {
            return response()->json([
                'message' => 'Merano booking is not enabled for this store.',
            ], 403);
        }

        $printer = $this->resolvePrinter($store, (int) $request->integer('printer_id'));
        if ($printer instanceof JsonResponse) {
            return $printer;
        }

        try {
            $xml = $this->ticketPrintService->renderBookingTicket($store->id, $printer, $request->validated());
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response($xml, 200)->header('Content-Type', 'text/xml; charset=UTF-8');
    }

    private function getAuthorizedStore(Request $request): Store|JsonResponse
    {
        $store = $this->getTenantStore($request);

        if (! $store) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $this->authorizeTenant($request, $store);

        return $store;
    }

    private function resolvePrinter(Store $store, int $printerId): ReceiptPrinter|JsonResponse
    {
        $printer = ReceiptPrinter::query()
            ->where('store_id', $store->id)
            ->find($printerId);

        if (! $printer) {
            return response()->json([
                'message' => 'Receipt printer not found for this store.',
            ], 422);
        }

        return $printer;
    }
}
