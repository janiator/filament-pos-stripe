<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetDynamicAppUrl
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Set APP_URL dynamically based on current request
        if ($request->hasHeader('Host')) {
            // Behind TLS-terminating proxies (e.g. nginx → php-fpm on HTTP), prefer the forwarded proto
            // so generated URLs match the browser and avoid http↔https redirect loops.
            $scheme = $request->getScheme();
            $forwardedProto = $request->headers->get('X-Forwarded-Proto');
            if (is_string($forwardedProto) && str_starts_with(strtolower(trim(explode(',', $forwardedProto)[0])), 'https')) {
                $scheme = 'https';
            }

            $host = $this->resolvePublicHost($request);
            $port = $this->resolvePublicPort($request, $host, $scheme);
            $baseUrl = $scheme.'://'.$host.($port && $port != 80 && $port != 443 ? ':'.$port : '');

            // Override filesystem public disk URL
            config(['filesystems.disks.public.url' => $baseUrl.'/storage']);

            // Force URL root
            \Illuminate\Support\Facades\URL::forceRootUrl($baseUrl);
        }

        return $next($request);
    }

    private function resolvePublicHost(Request $request): string
    {
        foreach (['X-Forwarded-Host', 'X-Original-Host'] as $header) {
            $value = $request->headers->get($header);

            if (! is_string($value) || blank($value)) {
                continue;
            }

            $host = trim(explode(',', $value)[0]);

            if (filled($host)) {
                return $host;
            }
        }

        return $request->getHost();
    }

    private function resolvePublicPort(Request $request, string $host, string $scheme): ?int
    {
        $forwardedPort = $request->headers->get('X-Forwarded-Port');

        if (is_string($forwardedPort) && is_numeric(trim(explode(',', $forwardedPort)[0]))) {
            return (int) trim(explode(',', $forwardedPort)[0]);
        }

        $port = $request->getPort();

        if ($host !== $request->getHost()) {
            return $scheme === 'https' ? 443 : 80;
        }

        return $port;
    }
}
