<?php

namespace App\Services;

use App\Models\PosSession;
use App\Models\PosEvent;
use App\Models\PosDevice;
use Illuminate\Support\Facades\Log;

class CashDrawerService
{
    /**
     * Open cash drawer for a cash payment
     *
     * @param PosSession $posSession
     * @param int $amount Amount in øre
     * @return void
     */
    public function openCashDrawer(PosSession $posSession, int $amount): void
    {
        // Get POS device
        $device = $posSession->posDevice;

        if (!$device) {
            Log::warning('No POS device found for cash drawer opening', [
                'pos_session_id' => $posSession->id,
            ]);
            return;
        }

        // Log cash drawer open event (13005)
        PosEvent::create([
            'store_id' => $posSession->store_id,
            'pos_session_id' => $posSession->id,
            'pos_device_id' => $device->id,
            'user_id' => $posSession->user_id,
            'event_code' => PosEvent::EVENT_CASH_DRAWER_OPEN, // 13005 - Cash drawer open
            'event_type' => 'drawer', // Drawer events use 'drawer' type
            'description' => 'Cash drawer opened',
            'event_data' => [
                'amount' => $amount,
                'trigger' => 'cash_payment',
                'device_id' => $device->id,
                'device_name' => $device->device_name,
            ],
            'occurred_at' => now(),
        ]);

        // Send command to device (implementation depends on device type)
        try {
            $this->sendOpenDrawerCommand($device, $amount);
        } catch (\Exception $e) {
            Log::error('Failed to send cash drawer open command', [
                'error' => $e->getMessage(),
                'device_id' => $device->id,
                'pos_session_id' => $posSession->id,
            ]);
            // Don't throw - cash drawer failure shouldn't block payment
        }
    }

    /**
     * Send open drawer command to device
     *
     * @param PosDevice $device
     * @param int $amount
     * @return void
     */
    protected function sendOpenDrawerCommand(PosDevice $device, int $amount): void
    {
        // Implementation depends on device type
        // For Epson printers: ESC/POS command
        // For other devices: API call or webhook

        $deviceType = $device->device_type ?? 'epson_printer';

        match ($deviceType) {
            'epson_printer' => $this->sendEpsonDrawerCommand($device),
            'network_printer' => $this->sendNetworkDrawerCommand($device),
            default => Log::warning('Unsupported device type for cash drawer', [
                'device_type' => $deviceType,
                'device_id' => $device->id,
            ]),
        };
    }

    /**
     * Send ESC/POS command to Epson printer
     *
     * @param PosDevice $device
     * @return void
     */
    protected function sendEpsonDrawerCommand(PosDevice $device): void
    {
        // ESC/POS command to open drawer: ESC p 0 25 250
        // This opens drawer pin 0 for 250ms
        $command = "\x1B\x70\x00\x19\xFA";

        // Get printer connection details from device config
        $config = $device->config ?? [];
        $ipAddress = $config['ip_address'] ?? null;
        $port = $config['port'] ?? 9100;

        if ($ipAddress) {
            // Send via network socket
            $socket = @fsockopen($ipAddress, $port, $errno, $errstr, 2);
            if ($socket) {
                fwrite($socket, $command);
                fclose($socket);
            } else {
                throw new \Exception("Failed to connect to printer: $errstr ($errno)");
            }
        } else {
            // For USB printers, would need different approach
            // This is a placeholder - actual implementation depends on printer model
            Log::info('USB printer cash drawer command (not implemented)', [
                'device_id' => $device->id,
            ]);
        }
    }

    /**
     * Send command to network printer
     *
     * @param PosDevice $device
     * @return void
     */
    protected function sendNetworkDrawerCommand(PosDevice $device): void
    {
        // Generic network printer command
        // Implementation depends on printer protocol
        $config = $device->config ?? [];
        $endpoint = $config['endpoint'] ?? null;

        if ($endpoint) {
            // Send HTTP request to printer API
            // This is a placeholder - actual implementation depends on printer API
            Log::info('Network printer cash drawer command (not implemented)', [
                'device_id' => $device->id,
                'endpoint' => $endpoint,
            ]);
        }
    }
}


