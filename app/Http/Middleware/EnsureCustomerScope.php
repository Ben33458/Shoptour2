<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Pricing\Customer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures that an authenticated "kunde" user can only access their own customer record.
 *
 * Resolution:
 *   1. User must be authenticated.
 *   2. A Customer row must exist with user_id = Auth::id().
 *   3. The resolved customer is bound to app('current_customer') for downstream use.
 *   4. If the route contains a {customer} or {customerId} parameter, it must match
 *      the authenticated customer's ID.
 *
 * Applied to /customer/** routes (not yet built; scaffolded for future use).
 */
class EnsureCustomerScope
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Unauthenticated.'], 401);
            }
            return redirect()->guest(route('login'));
        }

        $customer = Customer::where('user_id', Auth::id())->first();

        if (! $customer) {
            abort(403, 'Kein Kundenkonto mit diesem Benutzer verknüpft.');
        }

        if (! $customer->active) {
            abort(403, 'Kundenkonto ist deaktiviert.');
        }

        // Bind so controllers can use app('current_customer')
        app()->instance('current_customer', $customer);

        // If the route carries a {customer_id} or {customerId} parameter,
        // it MUST match the authenticated customer's ID.
        $paramId = $request->route('customer_id')
            ?? $request->route('customerId')
            ?? $request->route('customer');

        if ($paramId !== null && (int) $paramId !== $customer->id) {
            abort(403, 'Zugriff auf fremde Kundendaten verweigert.');
        }

        return $next($request);
    }
}
