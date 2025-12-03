<?php

namespace App\Services;

use App\Models\Receipt;
use App\Models\PosSession;
use App\Models\PosEvent;
use App\Models\PosDevice;
use App\Models\ReceiptPrinter;
use App\Services\ReceiptGenerationService;
use Illuminate\Support\Facades\Log;

class ReceiptPrintService
{
    protected ReceiptGenerationService $receiptService;

    public function __construct(ReceiptGenerationService $receiptService)
    {
        $this->receiptService = $receiptService;
    }

    /**
     * Print a receipt
     *
     * @param Receipt $receipt
     * @param PosSession $posSession
     * @return bool
     */
    public function printReceipt(Receipt $receipt, PosSession $posSession): bool
    {
        $device = $posSession->posDevice;

        if (!$device) {
            Log::warning('No POS device found for receipt printing', [
                'receipt_id' => $receipt->id,
                'pos_session_id' => $posSession->id,
            ]);
            return false;
        }

        try {
            // Generate receipt XML/data
            $receiptData = $this->receiptService->generateReceiptXml($receipt);

            // Get the printer for this device
            $printer = $this->getPrinterForDevice($device);

            if (!$printer) {
                Log::warning('No receipt printer found for POS device', [
                    'device_id' => $device->id,
                    'receipt_id' => $receipt->id,
                ]);
                return false;
            }

            // Send to printer
            $success = $this->sendToPrinter($printer, $receiptData);

            if ($success) {
                // Mark receipt as printed
                $receipt->update([
                    'printed' => true,
                    'printed_at' => now(),
                ]);

                // Log print event
                // Note: Receipt printing is tracked separately from the sales receipt event
                // Using 'other' type since printing doesn't have a specific SAF-T event code
                PosEvent::create([
                    'store_id' => $posSession->store_id,
                    'pos_session_id' => $posSession->id,
                    'pos_device_id' => $device->id,
                    'user_id' => $posSession->user_id,
                    'related_charge_id' => $receipt->charge_id, // Link to charge if available
                    'event_code' => '13012', // Sales receipt (receipt printing is part of this flow)
                    'event_type' => 'other', // Receipt printing uses 'other' type
                    'description' => 'Receipt printed',
                    'event_data' => [
                        'receipt_id' => $receipt->id,
                        'receipt_number' => $receipt->receipt_number,
                        'device_id' => $device->id,
                        'device_name' => $device->device_name,
                        'action' => 'print',
                    ],
                    'occurred_at' => now(),
                ]);
            }

            return $success;
        } catch (\Exception $e) {
            Log::error('Failed to print receipt', [
                'error' => $e->getMessage(),
                'receipt_id' => $receipt->id,
                'device_id' => $device->id,
            ]);
            return false;
        }
    }

    /**
     * Get the printer for a POS device
     * Priority: default_printer_id > first active printer > first printer
     *
     * @param PosDevice $device
     * @return ReceiptPrinter|null
     */
    protected function getPrinterForDevice(PosDevice $device): ?ReceiptPrinter
    {
        // Load the default printer if set
        if ($device->default_printer_id) {
            $printer = ReceiptPrinter::where('id', $device->default_printer_id)
                ->where('store_id', $device->store_id)
                ->where('is_active', true)
                ->first();
            
            if ($printer) {
                return $printer;
            }
        }

        // Fallback to first active printer attached to this device
        $printer = ReceiptPrinter::where('pos_device_id', $device->id)
            ->where('store_id', $device->store_id)
            ->where('is_active', true)
            ->first();

        if ($printer) {
            return $printer;
        }

        // Last resort: any active printer for this store
        return ReceiptPrinter::where('store_id', $device->store_id)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Send receipt data to printer
     *
     * @param ReceiptPrinter $printer
     * @param string $receiptData
     * @return bool
     */
    protected function sendToPrinter(ReceiptPrinter $printer, string $receiptData): bool
    {
        return match ($printer->printer_type) {
            'epson' => $this->sendToEpsonPrinter($printer, $receiptData),
            'star' => $this->sendToStarPrinter($printer, $receiptData),
            default => $this->sendToGenericPrinter($printer, $receiptData),
        };
    }

    /**
     * Send to Epson printer via network
     *
     * @param ReceiptPrinter $printer
     * @param string $receiptData
     * @return bool
     */
    protected function sendToEpsonPrinter(ReceiptPrinter $printer, string $receiptData): bool
    {
        if (!$printer->isNetworkPrinter()) {
            Log::warning('Epson printer is not configured for network connection', [
                'printer_id' => $printer->id,
                'connection_type' => $printer->connection_type,
            ]);
            return false;
        }

        $ipAddress = $printer->ip_address;
        $port = $printer->port ?? 9100;

        if (!$ipAddress) {
            Log::warning('No IP address configured for Epson printer', [
                'printer_id' => $printer->id,
            ]);
            return false;
        }

        try {
            // Try ePOS-Print API first (for Epson printers)
            if ($printer->printer_type === 'epson' && $printer->isNetworkPrinter()) {
                $url = $printer->epos_url;
                
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $receiptData);
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
                    $printer->update(['last_used_at' => now()]);
                    return true;
                } else {
                    Log::warning('ePOS-Print API failed, trying direct socket connection', [
                        'printer_id' => $printer->id,
                        'http_code' => $httpCode,
                        'error' => $curlError,
                    ]);
                    // Fall through to socket connection
                }
            }

            // Fallback to direct socket connection
            $socket = @fsockopen($ipAddress, $port, $errno, $errstr, 5);
            if ($socket) {
                fwrite($socket, $receiptData);
                fclose($socket);
                $printer->update(['last_used_at' => now()]);
                return true;
            } else {
                Log::error('Failed to connect to Epson printer', [
                    'error' => "$errstr ($errno)",
                    'printer_id' => $printer->id,
                    'ip_address' => $ipAddress,
                    'port' => $port,
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Exception sending to Epson printer', [
                'error' => $e->getMessage(),
                'printer_id' => $printer->id,
            ]);
            return false;
        }
    }

    /**
     * Send to Star printer
     *
     * @param ReceiptPrinter $printer
     * @param string $receiptData
     * @return bool
     */
    protected function sendToStarPrinter(ReceiptPrinter $printer, string $receiptData): bool
    {
        if (!$printer->isNetworkPrinter()) {
            Log::warning('Star printer is not configured for network connection', [
                'printer_id' => $printer->id,
                'connection_type' => $printer->connection_type,
            ]);
            return false;
        }

        $ipAddress = $printer->ip_address;
        $port = $printer->port ?? 9100;

        if (!$ipAddress) {
            Log::warning('No IP address configured for Star printer', [
                'printer_id' => $printer->id,
            ]);
            return false;
        }

        try {
            $socket = @fsockopen($ipAddress, $port, $errno, $errstr, 5);
            if ($socket) {
                fwrite($socket, $receiptData);
                fclose($socket);
                $printer->update(['last_used_at' => now()]);
                return true;
            } else {
                Log::error('Failed to connect to Star printer', [
                    'error' => "$errstr ($errno)",
                    'printer_id' => $printer->id,
                    'ip_address' => $ipAddress,
                    'port' => $port,
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Exception sending to Star printer', [
                'error' => $e->getMessage(),
                'printer_id' => $printer->id,
            ]);
            return false;
        }
    }

    /**
     * Generic printer handler
     *
     * @param ReceiptPrinter $printer
     * @param string $receiptData
     * @return bool
     */
    protected function sendToGenericPrinter(ReceiptPrinter $printer, string $receiptData): bool
    {
        // Try network connection for generic printers
        if ($printer->isNetworkPrinter()) {
            $ipAddress = $printer->ip_address;
            $port = $printer->port ?? 9100;

            try {
                $socket = @fsockopen($ipAddress, $port, $errno, $errstr, 5);
                if ($socket) {
                    fwrite($socket, $receiptData);
                    fclose($socket);
                    $printer->update(['last_used_at' => now()]);
                    return true;
                }
            } catch (\Exception $e) {
                Log::error('Exception sending to generic printer', [
                    'error' => $e->getMessage(),
                    'printer_id' => $printer->id,
                ]);
            }
        }

        Log::warning('Generic printer handler - connection not available', [
            'printer_id' => $printer->id,
            'printer_type' => $printer->printer_type,
            'connection_type' => $printer->connection_type,
        ]);
        return false;
    }
}


