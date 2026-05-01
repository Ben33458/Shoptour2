<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prüft, ob ein eingeloggter Unterbenutzer die nötige Berechtigung hat.
 * Hauptkunden (role=kunde) und Admins passieren immer.
 *
 * Verwendung in Routen: ->middleware('sub_user:invoices')
 */
class SubUserPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== 'sub_user') {
            return $next($request);
        }

        $subUser = $user->subUser;

        if (! $subUser || ! $subUser->active) {
            abort(403, 'Zugang gesperrt.');
        }

        if (! $subUser->can($permission)) {
            abort(403, 'Keine Berechtigung für diesen Bereich.');
        }

        return $next($request);
    }
}
