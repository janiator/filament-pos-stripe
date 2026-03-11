<?php

namespace App\Http\Controllers\Api;

use App\Enums\AddonType;
use App\Http\Requests\Api\PrintFreeTicketRequest;
use App\Http\Requests\Api\PrintTicketRequest;
use App\Http\Requests\Api\TicketXmlByReferenceRequest;
use App\Models\Addon;
use App\Models\ReceiptPrinter;
use App\Models\Store;
use App\Services\TicketPrintService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Http;
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

        if ($printer === null) {
            return response()->json([
                'message' => 'A valid printer_id is required for free ticket printing.',
            ], 422);
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

    /**
     * Return ticket XML for a Merano booking by booking reference only.
     * For use from FlutterFlow API request or reprint flows. Backend looks up the booking via Merano.
     */
    public function ticketXmlByReference(TicketXmlByReferenceRequest $request): JsonResponse|HttpResponse
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

        if (! $store->merano_base_url || ! $store->merano_pos_api_token) {
            return response()->json([
                'message' => 'Merano is not configured for this store.',
            ], 503);
        }

        $params = $this->fetchTicketPayloadFromMerano($store, $request->validated('booking_reference'));
        if ($params instanceof JsonResponse) {
            return $params;
        }

        try {
            $xml = $this->ticketPrintService->renderBookingTicket($store->id, null, $params);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response($xml, 200)->header('Content-Type', 'text/xml; charset=UTF-8');
    }

    /**
     * Fetch ticket payload (order_number, date, place, heading, amount_paid, tickets) from Merano by booking number.
     *
     * @return array<string, mixed>|JsonResponse
     */
    private function fetchTicketPayloadFromMerano(Store $store, string $bookingReference): array|JsonResponse
    {
        $url = rtrim($store->merano_base_url, '/').'/api/pos/v1/bookings/by-number/'.urlencode($bookingReference);

        try {
            $response = Http::acceptJson()
                ->withToken($store->merano_pos_api_token)
                ->timeout(15)
                ->get($url);
        } catch (ConnectionException $e) {
            return response()->json([
                'message' => 'Unable to reach Merano.',
                'error' => $e->getMessage(),
            ], 502);
        }

        if ($response->status() === 404) {
            return response()->json([
                'message' => 'Booking not found or not eligible for ticket print.',
            ], 404);
        }

        if (! $response->successful()) {
            return response()->json([
                'message' => $response->json('message', 'Merano returned an error.'),
            ], $response->status());
        }

        $data = $response->json();
        if (! is_array($data) || empty($data['order_number']) || empty($data['tickets'])) {
            return response()->json([
                'message' => 'Invalid ticket data from Merano.',
            ], 422);
        }

        return $data;
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

    private function resolvePrinter(Store $store, int $printerId): ReceiptPrinter|JsonResponse|null
    {
        if ($printerId <= 0) {
            return null;
        }

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
