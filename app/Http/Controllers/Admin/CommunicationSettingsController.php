<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Communications\GmailSyncState;
use App\Services\Communications\GmailClient;
use App\Services\Communications\GmailImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommunicationSettingsController extends Controller
{
    public function __construct(private GmailImportService $importService) {}

    public function index(): View
    {
        $syncState = GmailSyncState::where('company_id', auth()->user()->company_id)->first();
        return view('admin.communications.settings', compact('syncState'));
    }

    public function gmailConnect(): RedirectResponse
    {
        return redirect(GmailClient::authUrl());
    }

    public function gmailCallback(Request $request): RedirectResponse
    {
        $code = $request->get('code');
        if (!$code) {
            return redirect()->route('admin.communications.settings')
                ->with('error', 'OAuth-Fehler: Kein Code erhalten.');
        }

        try {
            $tokens = GmailClient::exchangeCode($code);

            // Bestehenden Eintrag aktualisieren oder neuen anlegen (nie Duplikate)
            $syncState = GmailSyncState::firstOrNew(['company_id' => auth()->user()->company_id]);
            $syncState->setEncryptedAccessToken($tokens['access_token']);
            if (isset($tokens['refresh_token'])) {
                $syncState->setEncryptedRefreshToken($tokens['refresh_token']);
            }
            $syncState->token_expires_at = now()->addSeconds(($tokens['expires_in'] ?? 3600) - 60);
            $syncState->sync_status = GmailSyncState::STATUS_IDLE;
            $syncState->save();

            $client       = new GmailClient($syncState);
            $emailAddress = $client->getEmailAddress();

            $syncState->email_address = $emailAddress;
            $syncState->save();

            return redirect()->route('admin.communications.settings')
                ->with('success', "Gmail verbunden: {$emailAddress}");

        } catch (\Throwable $e) {
            return redirect()->route('admin.communications.settings')
                ->with('error', 'Gmail-Verbindung fehlgeschlagen: ' . $e->getMessage());
        }
    }

    public function gmailSync(): RedirectResponse
    {
        $syncState = GmailSyncState::where('company_id', auth()->user()->company_id)->first();

        if (!$syncState) {
            return back()->with('error', 'Kein Gmail-Konto verbunden.');
        }

        try {
            $count = $this->importService->importNew($syncState, manual: true);
            return back()->with('success', "{$count} E-Mail(s) aus dem Posteingang importiert.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Import fehlgeschlagen: ' . $e->getMessage());
        }
    }

    public function gmailDisconnect(): RedirectResponse
    {
        GmailSyncState::where('company_id', auth()->user()->company_id)->delete();
        return redirect()->route('admin.communications.settings')
            ->with('success', 'Gmail-Verbindung getrennt.');
    }
}
