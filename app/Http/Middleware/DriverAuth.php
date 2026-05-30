<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Driver\DriverApiToken;
use App\Models\Employee\Employee;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        if (str_starts_with((string) $header, 'Bearer ')) {
            // Bearer token path (PWA offline tokens)
            $plainToken = substr((string) $header, 7);

            if ($plainToken === '') {
                return response()->json(['error' => 'Empty bearer token.'], 401);
            }

            $tokenRecord = DriverApiToken::findByPlainToken($plainToken);

            if ($tokenRecord === null) {
                return response()->json(['error' => 'Invalid or inactive token.'], 401);
            }

            $request->attributes->set('driver_employee_id', $tokenRecord->employee_id);
            $request->attributes->set('driver_token_id',    $tokenRecord->id);

            return $next($request);
        }

        // Session auth path — check Laravel web guard (email/password login)
        $user = Auth::guard('web')->user();
        if ($user !== null) {
            if (! $user->hasAdminAccess()) {
                return response()->json(['error' => 'Forbidden.'], 403);
            }
            $employee = Employee::where('user_id', $user->id)->first();
            $request->attributes->set('driver_employee_id', $employee?->id);
            return $next($request);
        }

        // PIN session path (employee logged in via /mein timeclock)
        $employeeId = $request->session()->get('employee_id');
        if ($employeeId) {
            $request->attributes->set('driver_employee_id', (int) $employeeId);
            return $next($request);
        }

        return response()->json(['error' => 'Unauthenticated.'], 401);
    }
}
