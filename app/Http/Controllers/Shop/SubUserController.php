<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Pricing\Customer;
use App\Models\SubUser;
use App\Models\User;
use App\Services\SubUserInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SubUserController extends Controller
{
    public function __construct(
        private readonly SubUserInvitationService $invitationService,
    ) {}

    public function index(): View
    {
        $customer = $this->requireMainCustomer();
        $customer->load('subUsers.user');

        return view('shop.account.sub-users.index', compact('customer'));
    }

    public function invite(Request $request): RedirectResponse
    {
        $customer = $this->requireMainCustomer();

        $validated = $request->validate([
            'first_name'                              => ['required', 'string', 'max:100'],
            'last_name'                               => ['required', 'string', 'max:100'],
            'email'                                   => ['required', 'email', 'max:200'],
            'permissions.order_history'               => ['required', 'in:own,all'],
            'permissions.invoices'                    => ['boolean'],
            'permissions.addresses'                   => ['boolean'],
            'permissions.assortment'                  => ['boolean'],
            'permissions.sub_users'                   => ['boolean'],
            'permissions.bestellen_all'               => ['boolean'],
            'permissions.bestellen_favoritenliste'    => ['boolean'],
            'permissions.sollbestaende_bearbeiten'    => ['boolean'],
            'permissions.preise_sehen'                => ['boolean'],
        ]);

        // E-Mail bereits als Hauptkunde oder Unterbenutzer vorhanden?
        if (User::where('email', $validated['email'])->exists()) {
            return back()->with('error', 'Diese E-Mail-Adresse ist bereits registriert.');
        }

        // Nicht sich selbst einladen
        if ($validated['email'] === Auth::user()->email) {
            return back()->with('error', 'Sie können sich nicht selbst einladen.');
        }

        $p = $validated['permissions'] ?? [];
        $permissions = array_merge(\App\Models\SubUser::defaultPermissions(), [
            'order_history'            => $p['order_history'],
            'invoices'                 => (bool) ($p['invoices'] ?? false),
            'addresses'                => (bool) ($p['addresses'] ?? false),
            'assortment'               => (bool) ($p['assortment'] ?? false),
            'sub_users'                => (bool) ($p['sub_users'] ?? false),
            'bestellen_all'            => (bool) ($p['bestellen_all'] ?? false),
            'bestellen_favoritenliste' => (bool) ($p['bestellen_favoritenliste'] ?? true),
            'sollbestaende_bearbeiten' => (bool) ($p['sollbestaende_bearbeiten'] ?? false),
            'preise_sehen'             => (bool) ($p['preise_sehen'] ?? false),
        ]);

        $this->invitationService->invite(
            $customer,
            $validated['email'],
            $validated['first_name'],
            $validated['last_name'],
            $permissions,
        );

        return back()->with('success', "Einladung an {$validated['email']} wurde gesendet.");
    }

    public function updatePermissions(Request $request, SubUser $subUser): RedirectResponse
    {
        $customer = $this->requireMainCustomer();

        if ($subUser->parent_customer_id !== $customer->id) {
            abort(403);
        }

        $validated = $request->validate([
            'permissions.order_history'            => ['required', 'in:own,all'],
            'permissions.invoices'                 => ['boolean'],
            'permissions.addresses'                => ['boolean'],
            'permissions.assortment'               => ['boolean'],
            'permissions.sub_users'                => ['boolean'],
            'permissions.bestellen_all'            => ['boolean'],
            'permissions.bestellen_favoritenliste' => ['boolean'],
            'permissions.sollbestaende_bearbeiten' => ['boolean'],
            'permissions.preise_sehen'             => ['boolean'],
        ]);

        $p = $validated['permissions'] ?? [];
        $permissions = array_merge(SubUser::defaultPermissions(), [
            'order_history'            => $p['order_history'],
            'invoices'                 => (bool) ($p['invoices'] ?? false),
            'addresses'                => (bool) ($p['addresses'] ?? false),
            'assortment'               => (bool) ($p['assortment'] ?? false),
            'sub_users'                => (bool) ($p['sub_users'] ?? false),
            'bestellen_all'            => (bool) ($p['bestellen_all'] ?? false),
            'bestellen_favoritenliste' => (bool) ($p['bestellen_favoritenliste'] ?? false),
            'sollbestaende_bearbeiten' => (bool) ($p['sollbestaende_bearbeiten'] ?? false),
            'preise_sehen'             => (bool) ($p['preise_sehen'] ?? false),
        ]);

        $subUser->update(['permissions' => $permissions]);

        return back()->with('success', 'Berechtigungen aktualisiert.');
    }

    public function toggleActive(SubUser $subUser): RedirectResponse
    {
        $customer = $this->requireMainCustomer();

        if ($subUser->parent_customer_id !== $customer->id) {
            abort(403);
        }

        $subUser->update(['active' => ! $subUser->active]);
        $status = $subUser->active ? 'aktiviert' : 'deaktiviert';

        return back()->with('success', "Unterbenutzer {$status}.");
    }

    public function destroy(SubUser $subUser): RedirectResponse
    {
        $customer = $this->requireMainCustomer();

        if ($subUser->parent_customer_id !== $customer->id) {
            abort(403);
        }

        $user = $subUser->user;
        $subUser->delete();
        // Löscht auch den User-Account, sofern keine Bestellungen vorhanden
        if ($user && $user->orders()->doesntExist()) {
            $user->delete();
        }

        return back()->with('success', 'Unterbenutzer entfernt.');
    }

    // -------------------------------------------------------------------------

    /**
     * Gibt den Customer des eingeloggten Hauptkunden zurück.
     * Unterbenutzern ist die Verwaltung nur erlaubt, wenn sie die Berechtigung sub_users haben.
     */
    private function requireMainCustomer(): Customer
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->isSubUser()) {
            $subUser = $user->subUser;
            if (! $subUser?->can('sub_users')) {
                abort(403, 'Keine Berechtigung für Unterbenutzer-Verwaltung.');
            }
            return $subUser->parentCustomer;
        }

        $customer = $user->customer;
        if (! $customer) {
            abort(403, 'Kein Kundenkonto vorhanden.');
        }

        return $customer;
    }
}
