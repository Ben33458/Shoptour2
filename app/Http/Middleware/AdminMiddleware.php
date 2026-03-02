<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures the authenticated user has admin or mitarbeiter role.
 * Unauthenticated requests are redirected to /login.
 * Authenticated non-admin users receive a 403 Forbidden response.
 */
class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect()->guest(route('login'));
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! $user->hasAdminAccess()) {
            abort(403, 'Kein Zugriff. Admin-Berechtigungen erforderlich.');
        }

        return $next($request);
    }
}
