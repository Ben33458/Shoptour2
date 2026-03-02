<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Driver\DriverApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer-token authentication for the driver API.
 *
 * Reads the Authorization header, looks up the SHA-256 hash in driver_api_tokens,
 * and injects employee_id into the request for downstream use.
 *
 * On failure returns 401 JSON (never redirects — this is an API middleware).
 */
class DriverAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization', '');

        if (! str_starts_with((string) $header, 'Bearer ')) {
            return response()->json(['error' => 'Missing Authorization header.'], 401);
        }

        $plainToken = substr((string) $header, 7);

        if ($plainToken === '') {
            return response()->json(['error' => 'Empty bearer token.'], 401);
        }

        $tokenRecord = DriverApiToken::findByPlainToken($plainToken);

        if ($tokenRecord === null) {
            return response()->json(['error' => 'Invalid or inactive token.'], 401);
        }

        // Make employee_id available to controllers via request attribute
        $request->attributes->set('driver_employee_id', $tokenRecord->employee_id);
        $request->attributes->set('driver_token_id',    $tokenRecord->id);

        return $next($request);
    }
}
