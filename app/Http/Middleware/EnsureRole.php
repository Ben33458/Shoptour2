<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Generic role-checking middleware.
 *
 * Usage in routes:
 *   ->middleware('role:admin')
 *   ->middleware('role:admin,mitarbeiter')
 *
 * Parameters are comma-separated allowed role values.
 * The user must be authenticated AND have one of the listed roles.
 * Unauthenticated users are redirected to /login (web) or receive 401 (API).
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Unauthenticated.'], 401);
            }
            return redirect()->guest(route('login'));
        }

        $user = Auth::user();
        $role = $user->role ?? '';

        if (! in_array($role, $roles, true)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Forbidden.'], 403);
            }
            abort(403, 'Zugriff verweigert.');
        }

        return $next($request);
    }
}
