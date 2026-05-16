<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CleanApiQueryParameters
{
    /**
     * Handle an incoming request.
     *
     * Removes null, empty string, whitespace-only values, and the literal string "null"
     * from API query strings and JSON/form bodies (FlutterFlow sometimes sends `"null"` for absent IDs).
     * Passing that string into bigint columns triggers PostgreSQL errors (invalid input syntax for bigint).
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api/*')) {
            return $next($request);
        }

        // Skip cleaning for signed URL routes (signature validation requires exact query params).
        // Also skip for saf-t download route which uses signed URLs.
        if ($request->has('signature')
            || $request->has('expires')
            || $request->is('api/saf-t/download/*')) {
            return $next($request);
        }

        $request->query->replace($this->recursivelyCleanParameters($request->query->all()));

        if ($request->isJson()) {
            $request->json()->replace($this->recursivelyCleanParameters($request->json()->all()));
        } elseif (! in_array($request->getRealMethod(), ['GET', 'HEAD'], true)) {
            $request->request->replace($this->recursivelyCleanParameters($request->request->all()));
        }

        return $next($request);
    }

    /**
     * @param  array<mixed>  $parameters
     * @return array<mixed>
     */
    private function recursivelyCleanParameters(array $parameters): array
    {
        $wasList = array_is_list($parameters);
        $cleaned = [];

        foreach ($parameters as $key => $value) {
            if (is_array($value)) {
                $nested = $this->recursivelyCleanParameters($value);

                if ($nested === []) {
                    continue;
                }

                $cleaned[$key] = $nested;

                continue;
            }

            if ($this->shouldIncludeScalar($value)) {
                $cleaned[$key] = $value;
            }
        }

        return $wasList ? array_values($cleaned) : $cleaned;
    }

    private function shouldIncludeScalar(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            if ($trimmed === '' || strtolower($trimmed) === 'null') {
                return false;
            }
        }

        return is_scalar($value);
    }
}
