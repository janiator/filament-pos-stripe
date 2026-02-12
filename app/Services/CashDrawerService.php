<?php

namespace App\Services;

use App\Models\PosDevice;
use App\Models\PosEvent;
use App\Models\PosSession;
use Illuminate\Support\Facades\Log;

class CashDrawerService
{
    /**
     * Open cash drawer for a cash payment
     *
     * @param  int  $amount  Amount in øre
     */
    public function openCashDrawer(PosSession $posSession, int $amount): void
    {
        // Get POS device
        $device = $posSession->posDevice;

        if (! $device) {
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

    /**
     * Log a cash withdrawal from the drawer (staff taking money out).
     *
     * @param  int  $amountOre  Amount in øre
     * @param  string|null  $reason  Optional reason
     */
    public function logWithdrawal(PosSession $posSession, int $amountOre, ?string $reason = null): PosEvent
    {
        return $this->logCashMovement(
            $posSession,
            $amountOre,
            PosEvent::EVENT_CASH_WITHDRAWAL,
            'Cash withdrawal',
            $reason
        );
    }

    /**
     * Log a cash deposit into the drawer (staff putting money in).
     *
     * @param  int  $amountOre  Amount in øre
     * @param  string|null  $reason  Optional reason
     */
    public function logDeposit(PosSession $posSession, int $amountOre, ?string $reason = null): PosEvent
    {
        return $this->logCashMovement(
            $posSession,
            $amountOre,
            PosEvent::EVENT_CASH_DEPOSIT,
            'Cash deposit',
            $reason
        );
    }

    /**
     * Log a cash movement event (withdrawal or deposit).
     *
     * @param  int  $amountOre  Amount in øre
     * @param  string  $eventCode  The event code constant
     * @param  string  $description  Description of the event
     * @param  string|null  $reason  Optional reason
     */
    private function logCashMovement(
        PosSession $posSession,
        int $amountOre,
        string $eventCode,
        string $description,
        ?string $reason = null
    ): PosEvent {
        $device = $posSession->posDevice;

        return PosEvent::create([
            'store_id' => $posSession->store_id,
            'pos_session_id' => $posSession->id,
            'pos_device_id' => $device?->id,
            'user_id' => auth()->id(),
            'event_code' => $eventCode,
            'event_type' => 'drawer',
            'description' => $description,
            'event_data' => [
                'amount' => $amountOre,
                'reason' => $reason,
            ],
            'occurred_at' => now(),
        ]);
    }
}
