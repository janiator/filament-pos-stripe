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
        if (! $request->is('livewire/update') || ! $request->isMethod('post')) {
            return $next($request);
        }

        if (! $request->isJson()) {
            return $this->malformedPayloadResponse();
        }

        $payload = $request->json()->all();
        $components = $payload['components'] ?? null;

        if (! is_array($components) || $components === []) {
            return $this->malformedPayloadResponse();
        }

        foreach ($components as $component) {
            if (! is_array($component)
                || ! is_string($component['snapshot'] ?? null)
                || ! is_array($component['updates'] ?? null)
                || ! is_array($component['calls'] ?? null)
            ) {
                return $this->malformedPayloadResponse();
            }
        }

        return $next($request);
    }

    private function malformedPayloadResponse(): Response
    {
        return response()->json([
            'message' => 'Malformed Livewire update payload.',
        ], 400);
    }
}
