<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\CustomerPermissions;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * PROJ-20 — Block sub-users who only have bestellen_favoritenliste from
 * placing general shop orders (POST /warenkorb, POST /kasse).
 *
 * Main customers and sub-users with bestellen_all pass through.
 */
class EnforceShopOrderPermission
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->isSubUser()) {
            $perms = new CustomerPermissions($user);

            if (! $perms->canOrderAll()) {
                abort(403, 'Sie haben keine Berechtigung, allgemeine Bestellungen aufzugeben.');
            }
        }

        return $next($request);
    }
}
