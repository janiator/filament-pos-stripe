<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GuardLivewireUpdatePayload
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('livewire/update') && $request->isMethod('post')) {
            $payload = $request->isJson()
                ? $request->json()->all()
                : $request->all();
            $components = $payload['components'] ?? null;

            if (! is_array($components)) {
                return response()->json([
                    'message' => 'Malformed Livewire update payload.',
                ], 400);
            }
        }

        return $next($request);
    }
}
