<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Driver\DriverApiToken;
use App\Services\Admin\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin UI for managing driver API bearer tokens.
 *
 * GET  /admin/driver-tokens           → index  (list all tokens)
 * GET  /admin/driver-tokens/create    → create (form)
 * POST /admin/driver-tokens           → store  (issue new token – shows plain token once)
 * DELETE /admin/driver-tokens/{token} → revoke (soft-revoke token)
 */
class AdminDriverTokenController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    // =========================================================================
    // index
    // =========================================================================

    /**
     * GET /admin/driver-tokens
     * List all driver API tokens for the current company (or all if no company).
     */
    public function index(): View
    {
        $tokens = DriverApiToken::orderByDesc('created_at')->paginate(25);

        return view('admin.driver-tokens.index', compact('tokens'));
    }

    // =========================================================================
    // create
    // =========================================================================

    /**
     * GET /admin/driver-tokens/create
     * Show the "issue new token" form.
     */
    public function create(): View
    {
        return view('admin.driver-tokens.create');
    }

    // =========================================================================
    // store
    // =========================================================================

    /**
     * POST /admin/driver-tokens
     * Issue a new token and flash the plain token once.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label'      => ['required', 'string', 'max:120'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        ['token' => $token, 'plain' => $plain] = DriverApiToken::issue([
            'label'      => $data['label'],
            'expires_at' => ! empty($data['expires_at']) ? $data['expires_at'] : null,
        ]);

        $this->audit->log('driver_token.created', $token, [
            'label'      => $token->label,
            'expires_at' => $token->expires_at?->toDateString(),
        ]);

        // Flash the plain token once — never stored or shown again
        session()->flash('plain_token', $plain);
        session()->flash('plain_token_label', $token->label);

        return redirect()->route('admin.driver-tokens.index');
    }

    // =========================================================================
    // revoke
    // =========================================================================

    /**
     * DELETE /admin/driver-tokens/{token}
     * Revoke (soft-delete) a driver token.
     */
    public function revoke(Request $request, DriverApiToken $token): RedirectResponse
    {
        if ($token->revoked_at !== null) {
            return redirect()->route('admin.driver-tokens.index')
                ->with('warning', 'Token ist bereits widerrufen.');
        }

        $token->revoke();

        $this->audit->log('driver_token.revoked', $token, [
            'label' => $token->label,
        ]);

        return redirect()->route('admin.driver-tokens.index')
            ->with('success', 'Token „' . $token->label . '" wurde widerrufen.');
    }
}
