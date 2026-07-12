<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GuardLivewireUpdatePayload
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('livewire/update') && $request->isMethod('post')) {
            $rejection = $this->rejectMalformedUpdateRequest($request->all());

            if ($rejection instanceof Response) {
                return $rejection;
            }
        }

        return $next($request);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function rejectMalformedUpdateRequest(array $payload): ?Response
    {
        $components = $payload['components'] ?? null;

        if (! is_array($components)) {
            return $this->livewireMalformedResponse();
        }

        foreach ($components as $componentPayload) {
            if (! $this->isWellFormedWireComponentEnvelope($componentPayload)) {
                return $this->livewireMalformedResponse();
            }

            /** @var array{snapshot: string, updates: array, calls: array} $componentPayload */
            $snapshot = json_decode($componentPayload['snapshot'], true);

            if (! is_array($snapshot)) {
                return $this->livewireMalformedResponse();
            }

            if (! $this->hasValidDecodedSnapshotShape($snapshot)) {
                return $this->livewireMalformedResponse();
            }

            if ($this->filamentNotificationsHasInvalidBooleanStamp($snapshot)) {
                return $this->livewireMalformedResponse();
            }
        }

        return null;
    }

    private function livewireMalformedResponse(): Response
    {
        return response()->json([
            'message' => 'Malformed Livewire update payload.',
        ], 400);
    }

    private function isWellFormedWireComponentEnvelope(mixed $componentPayload): bool
    {
        if (! is_array($componentPayload)) {
            return false;
        }

        if (! isset($componentPayload['snapshot'], $componentPayload['updates'], $componentPayload['calls'])) {
            return false;
        }

        if (! is_string($componentPayload['snapshot'])) {
            return false;
        }

        if (! is_array($componentPayload['updates'])) {
            return false;
        }

        return is_array($componentPayload['calls']);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function hasValidDecodedSnapshotShape(array $snapshot): bool
    {
        return is_array($snapshot['data'] ?? null)
            && is_array($snapshot['memo'] ?? null)
            && is_string($snapshot['checksum'] ?? null)
            && is_string($snapshot['memo']['id'] ?? null)
            && is_scalar($snapshot['memo']['name'] ?? null);
    }

    /**
     * Livewire exposes Filament Notifications as the `notifications` component.
     *
     * @param  array<string, mixed>  $snapshot
     */
    private function filamentNotificationsHasInvalidBooleanStamp(array $snapshot): bool
    {
        $name = $snapshot['memo']['name'] ?? null;

        if ($name !== 'notifications') {
            return false;
        }

        if (! array_key_exists('isFilamentNotificationsComponent', $snapshot['data'])) {
            return false;
        }

        $value = $snapshot['data']['isFilamentNotificationsComponent'];

        return ! is_bool($value) && $value !== null;
    }
}
