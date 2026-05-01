<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\SubUserInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InvitationController extends Controller
{
    public function __construct(
        private readonly SubUserInvitationService $service,
    ) {}

    public function show(string $token): View|RedirectResponse
    {
        $invitation = $this->service->findValid($token);

        if (! $invitation) {
            return redirect()->route('login')
                ->with('error', 'Dieser Einladungslink ist ungültig oder abgelaufen.');
        }

        return view('auth.invitation', compact('invitation', 'token'));
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $request->validate([
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required'],
        ]);

        try {
            $user = $this->service->accept($token, $request->password);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        Auth::login($user);

        return redirect()->route('account')
            ->with('success', 'Willkommen! Ihr Konto wurde eingerichtet.');
    }
}
