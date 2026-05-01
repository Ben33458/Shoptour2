<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AdminUserController extends Controller
{
    public function index()
    {
        $users = User::orderBy('first_name')->orderBy('last_name')->get();

        $zustaendigkeitValues = \DB::table('ninox_77_regelmaessige_aufgaben')
            ->select('zustaendigkeit')
            ->distinct()
            ->whereNotNull('zustaendigkeit')
            ->orderBy('zustaendigkeit')
            ->pluck('zustaendigkeit');

        return view('admin.users.index', compact('users', 'zustaendigkeitValues'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'email'      => 'required|email|unique:users,email',
            'role'       => 'required|in:admin,mitarbeiter,kunde',
            'password'   => ['required', PasswordRule::min(8)],
        ]);

        $company = app('current_company');

        User::create([
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
            'email'      => $data['email'],
            'role'       => $data['role'],
            'password'   => Hash::make($data['password']),
            'active'     => true,
            'company_id' => $company?->id,
        ]);

        return back()->with('success', 'Benutzer angelegt.');
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'email'      => 'required|email|unique:users,email,' . $user->id,
            'role'       => 'required|in:admin,mitarbeiter,kunde',
            'active'     => 'boolean',
        ]);

        $user->update([
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
            'email'      => $data['email'],
            'role'       => $data['role'],
            'active'     => $request->boolean('active'),
        ]);

        return back()->with('success', 'Benutzer aktualisiert.');
    }

    public function resetPassword(User $user)
    {
        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('success', 'Passwort-Reset-Link an ' . $user->email . ' gesendet.');
        }

        return back()->with('error', 'E-Mail konnte nicht gesendet werden.');
    }

    public function setPassword(Request $request, User $user)
    {
        $request->validate(['password' => ['required', PasswordRule::min(8)]]);
        $user->update(['password' => Hash::make($request->password)]);

        return response()->json(['success' => true, 'message' => 'Passwort für ' . $user->name . ' gesetzt.']);
    }
}
