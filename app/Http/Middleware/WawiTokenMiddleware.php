<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WawiTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization', '');

        if (! str_starts_with((string) $header, 'Bearer ')) {
            return response()->json(['error' => 'Missing Authorization header.'], 401);
        }

        $token = substr((string) $header, 7);
        $expected = config('services.wawi.sync_token');

        if ($token === '' || ! hash_equals((string) $expected, $token)) {
            return response()->json(['error' => 'Invalid token.'], 401);
        }

        // Force JSON responses regardless of Accept header —
        // this endpoint is always called by machine clients.
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
