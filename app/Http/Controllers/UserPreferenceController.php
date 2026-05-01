<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserPreferenceController extends Controller
{
    /**
     * Toggle or set the dark-mode preference.
     * - Authenticated users: persisted in users.dark_mode
     * - Guests: stored in a 1-year cookie
     */
    public function darkMode(Request $request): JsonResponse
    {
        $dark = (bool) $request->input('dark', false);

        if (Auth::check()) {
            Auth::user()->update(['dark_mode' => $dark]);
        }

        return response()
            ->json(['dark' => $dark])
            ->cookie('dark_mode', $dark ? '1' : '0', 60 * 24 * 365);
    }
}
