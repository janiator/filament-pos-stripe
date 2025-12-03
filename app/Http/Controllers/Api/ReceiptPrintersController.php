<?php

namespace App\Http\Controllers\Api;

use App\Models\ReceiptPrinter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReceiptPrintersController extends BaseApiController
{
    /**
     * Get all receipt printers for the current store
     */
    public function index(Request $request): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json([
                'message' => 'Store not found',
            ], 404);
        }

        $this->authorizeTenant($request, $store);

        $printers = ReceiptPrinter::where('store_id', $store->id)
            ->with(['posDevice'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'printers' => $printers->map(function ($printer) {
                return $this->formatPrinterResponse($printer);
            }),
        ]);
    }

    /**
     * Create a new receipt printer
     */
    public function store(Request $request): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json([
                'message' => 'Store not found',
            ], 404);
        }

        $this->authorizeTenant($request, $store);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'printer_type' => 'required|string|in:epson,star,generic',
            'printer_model' => 'nullable|string|max:255',
            'paper_width' => 'nullable|string|in:80,58',
            'connection_type' => 'required|string|in:network,usb,bluetooth',
            'ip_address' => 'nullable|string|ip',
            'port' => 'nullable|integer|min:1|max:65535',
            'device_id' => 'nullable|string|max:255',
            'use_https' => 'nullable|boolean',
            'timeout' => 'nullable|integer|min:1000|max:300000',
            'is_active' => 'nullable|boolean',
            'monitor_status' => 'nullable|boolean',
            'drawer_open_level' => 'nullable|string|in:low,high',
            'use_job_id' => 'nullable|boolean',
            'pos_device_id' => 'nullable|exists:pos_devices,id',
            'printer_metadata' => 'nullable|array',
        ]);

        // Validate network connection requirements
        if ($validated['connection_type'] === 'network') {
            if (empty($validated['ip_address'])) {
                return response()->json([
                    'message' => 'IP address is required for network printers',
                ], 422);
            }
        }

        // Set defaults
        $validated['store_id'] = $store->id;
        $validated['paper_width'] = $validated['paper_width'] ?? '80';
        $validated['port'] = $validated['port'] ?? 9100;
        $validated['device_id'] = $validated['device_id'] ?? 'local_printer';
        $validated['use_https'] = $validated['use_https'] ?? false;
        $validated['timeout'] = $validated['timeout'] ?? 60000;
        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['monitor_status'] = $validated['monitor_status'] ?? false;
        $validated['drawer_open_level'] = $validated['drawer_open_level'] ?? 'low';
        $validated['use_job_id'] = $validated['use_job_id'] ?? false;

        // Validate pos_device_id belongs to the store
        if (isset($validated['pos_device_id'])) {
            $posDevice = \App\Models\PosDevice::where('id', $validated['pos_device_id'])
                ->where('store_id', $store->id)
                ->first();
            
            if (!$posDevice) {
                return response()->json([
                    'message' => 'POS device not found or does not belong to this store',
                ], 422);
            }
        }

        $printer = ReceiptPrinter::create($validated);

        return response()->json([
            'message' => 'Receipt printer created successfully',
            'printer' => $this->formatPrinterResponse($printer->load('posDevice')),
        ], 201);
    }

    /**
     * Get a specific receipt printer
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json([
                'message' => 'Store not found',
            ], 404);
        }

        $this->authorizeTenant($request, $store);

        $printer = ReceiptPrinter::where('id', $id)
            ->where('store_id', $store->id)
            ->with(['posDevice'])
            ->firstOrFail();

        return response()->json([
            'printer' => $this->formatPrinterResponse($printer),
        ]);
    }

    /**
     * Update receipt printer information
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json([
                'message' => 'Store not found',
            ], 404);
        }

        $this->authorizeTenant($request, $store);

        $printer = ReceiptPrinter::where('id', $id)
            ->where('store_id', $store->id)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'printer_type' => 'sometimes|string|in:epson,star,generic',
            'printer_model' => 'nullable|string|max:255',
            'paper_width' => 'nullable|string|in:80,58',
            'connection_type' => 'sometimes|string|in:network,usb,bluetooth',
            'ip_address' => 'nullable|string|ip',
            'port' => 'nullable|integer|min:1|max:65535',
            'device_id' => 'nullable|string|max:255',
            'use_https' => 'nullable|boolean',
            'timeout' => 'nullable|integer|min:1000|max:300000',
            'is_active' => 'nullable|boolean',
            'monitor_status' => 'nullable|boolean',
            'drawer_open_level' => 'nullable|string|in:low,high',
            'use_job_id' => 'nullable|boolean',
            'pos_device_id' => 'nullable|exists:pos_devices,id',
            'printer_metadata' => 'nullable|array',
        ]);

        // Validate network connection requirements
        $connectionType = $validated['connection_type'] ?? $printer->connection_type;
        if ($connectionType === 'network' && empty($validated['ip_address'] ?? $printer->ip_address)) {
            return response()->json([
                'message' => 'IP address is required for network printers',
            ], 422);
        }

        // Validate pos_device_id belongs to the store
        if (isset($validated['pos_device_id'])) {
            $posDevice = \App\Models\PosDevice::where('id', $validated['pos_device_id'])
                ->where('store_id', $store->id)
                ->first();
            
            if (!$posDevice) {
                return response()->json([
                    'message' => 'POS device not found or does not belong to this store',
                ], 422);
            }
        }

        $printer->update($validated);

        return response()->json([
            'message' => 'Receipt printer updated successfully',
            'printer' => $this->formatPrinterResponse($printer->load('posDevice')),
        ]);
    }

    /**
     * Delete a receipt printer
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json([
                'message' => 'Store not found',
            ], 404);
        }

        $this->authorizeTenant($request, $store);

        $printer = ReceiptPrinter::where('id', $id)
            ->where('store_id', $store->id)
            ->firstOrFail();

        $printer->delete();

        return response()->json([
            'message' => 'Receipt printer deleted successfully',
        ]);
    }

    /**
     * Test printer connection
     */
    public function testConnection(Request $request, string $id): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json([
                'message' => 'Store not found',
            ], 404);
        }

        $this->authorizeTenant($request, $store);

        $printer = ReceiptPrinter::where('id', $id)
            ->where('store_id', $store->id)
            ->firstOrFail();

        if (!$printer->is_active) {
            return response()->json([
                'message' => 'Printer is not active',
                'connected' => false,
            ], 400);
        }

        if (!$printer->isNetworkPrinter()) {
            return response()->json([
                'message' => 'Connection testing is only available for network printers',
                'connected' => false,
            ], 400);
        }

        // Test network connection
        $connected = false;
        $error = null;

        try {
            $socket = @fsockopen($printer->ip_address, $printer->port, $errno, $errstr, 5);
            if ($socket) {
                fclose($socket);
                $connected = true;
            } else {
                $error = "$errstr ($errno)";
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        return response()->json([
            'connected' => $connected,
            'printer_id' => $printer->id,
            'ip_address' => $printer->ip_address,
            'port' => $printer->port,
            'error' => $error,
        ]);
    }

    /**
     * Send test print to printer
     */
    public function testPrint(Request $request, string $id): JsonResponse
    {
        $store = $this->getTenantStore($request);
        
        if (!$store) {
            return response()->json([
                'message' => 'Store not found',
            ], 404);
        }

        $this->authorizeTenant($request, $store);

        $printer = ReceiptPrinter::where('id', $id)
            ->where('store_id', $store->id)
            ->firstOrFail();

        if (!$printer->is_active) {
            return response()->json([
                'message' => 'Printer is not active',
                'success' => false,
            ], 400);
        }

        if (!$printer->isNetworkPrinter()) {
            return response()->json([
                'message' => 'Test printing is only available for network printers',
                'success' => false,
            ], 400);
        }

        // Generate simple test print XML (Epson ePOS format)
        $testXml = $this->generateTestPrintXml($printer);

        // Send to printer
        $success = false;
        $error = null;

        try {
            if ($printer->printer_type === 'epson') {
                // Use ePOS-Print API for Epson printers
                $url = $printer->epos_url;
                
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $testXml);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/xml',
                ]);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);

                if ($httpCode >= 200 && $httpCode < 300 && empty($curlError)) {
                    $success = true;
                    $printer->update(['last_used_at' => now()]);
                } else {
                    $error = $curlError ?: "HTTP {$httpCode}";
                }
            } else {
                // For other printer types, try direct socket connection
                $socket = @fsockopen($printer->ip_address, $printer->port, $errno, $errstr, 5);
                if ($socket) {
                    fwrite($socket, $testXml);
                    fclose($socket);
                    $success = true;
                    $printer->update(['last_used_at' => now()]);
                } else {
                    $error = "$errstr ($errno)";
                }
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
            Log::error('Test print failed', [
                'printer_id' => $printer->id,
                'error' => $error,
            ]);
        }

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Test print sent successfully' : 'Test print failed',
            'error' => $error,
        ]);
    }

    /**
     * Generate test print XML for Epson printers
     */
    protected function generateTestPrintXml(ReceiptPrinter $printer): string
    {
        $store = $printer->store;
        $storeName = $store->name ?? 'Test Store';
        
        return '<?xml version="1.0" encoding="UTF-8"?>
<epos-print xmlns="http://www.epson-pos.com/schemas/2011/03/epos-print">
    <text lang="en" />
    <text smooth="true" />
    <text align="center" />
    <text width="2" height="2" />
    <text>' . htmlspecialchars($storeName) . '</text>
    <text />
    <text align="center" />
    <text width="1" height="1" />
    <text>TEST PRINT</text>
    <text />
    <text align="left" />
    <text>Printer: ' . htmlspecialchars($printer->name) . '</text>
    <text>Type: ' . htmlspecialchars($printer->printer_type) . '</text>
    <text>Model: ' . htmlspecialchars($printer->printer_model ?? 'N/A') . '</text>
    <text>IP: ' . htmlspecialchars($printer->ip_address) . '</text>
    <text>Date: ' . now()->setTimezone('Europe/Oslo')->format('Y-m-d H:i:s') . '</text>
    <text />
    <text align="center" />
    <text>This is a test print.</text>
    <text>If you can read this,</text>
    <text>your printer is working!</text>
    <text />
    <text />
    <cut />
</epos-print>';
    }

    /**
     * Format printer response with all information
     */
    protected function formatPrinterResponse(ReceiptPrinter $printer): array
    {
        return [
            'id' => $printer->id,
            'name' => $printer->name,
            'printer_type' => $printer->printer_type,
            'printer_model' => $printer->printer_model,
            'paper_width' => $printer->paper_width,
            'connection_type' => $printer->connection_type,
            'ip_address' => $printer->ip_address,
            'port' => $printer->port,
            'device_id' => $printer->device_id,
            'use_https' => $printer->use_https,
            'timeout' => $printer->timeout,
            'is_active' => $printer->is_active,
            'monitor_status' => $printer->monitor_status,
            'drawer_open_level' => $printer->drawer_open_level,
            'use_job_id' => $printer->use_job_id,
            'printer_metadata' => $printer->printer_metadata,
            'last_used_at' => $this->formatDateTimeOslo($printer->last_used_at),
            'pos_device' => $printer->posDevice ? [
                'id' => $printer->posDevice->id,
                'device_name' => $printer->posDevice->device_name,
            ] : null,
            'epos_url' => $printer->isNetworkPrinter() ? $printer->epos_url : null,
            'created_at' => $this->formatDateTimeOslo($printer->created_at),
            'updated_at' => $this->formatDateTimeOslo($printer->updated_at),
        ];
    }
}

