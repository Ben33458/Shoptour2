<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pricing\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Central settings page for all API integrations.
 * Stores all keys in the app_settings table (AppSetting model).
 */
class AdminIntegrationsSettingsController extends Controller
{
    public function index(): View
    {
        $settings = [
            'lexoffice' => [
                'enabled' => AppSetting::get('lexoffice.enabled', '0') === '1',
                'api_key' => AppSetting::get('lexoffice.api_key', ''),
            ],
            'ninox' => [
                'api_key'    => AppSetting::get('ninox.api_key',    ''),
                'team_id'    => AppSetting::get('ninox.team_id',    ''),
                'db_id_kehr' => AppSetting::get('ninox.db_id_kehr', ''),
                'db_id_alt'  => AppSetting::get('ninox.db_id_alt',  ''),
            ],
            'getraenkedb' => [
                'api_url' => AppSetting::get('getraenkedb.api_url', ''),
                'api_key' => AppSetting::get('getraenkedb.api_key', ''),
            ],
            'wawi' => [
                'sync_token' => AppSetting::get('wawi.sync_token', ''),
            ],
        ];

        return view('admin.settings.integrations', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            // Lexoffice
            'lexoffice_enabled' => ['nullable', 'boolean'],
            'lexoffice_api_key' => ['nullable', 'string', 'max:500'],
            // Ninox
            'ninox_api_key'    => ['nullable', 'string', 'max:200'],
            'ninox_team_id'    => ['nullable', 'string', 'max:100'],
            'ninox_db_id_kehr' => ['nullable', 'string', 'max:100'],
            'ninox_db_id_alt'  => ['nullable', 'string', 'max:100'],
            // GetränkeDB
            'getraenkedb_api_url' => ['nullable', 'url', 'max:300'],
            'getraenkedb_api_key' => ['nullable', 'string', 'max:200'],
            // WaWi
            'wawi_sync_token' => ['nullable', 'string', 'max:200'],
        ]);

        AppSetting::set('lexoffice.enabled', $request->boolean('lexoffice_enabled') ? '1' : '0');
        AppSetting::set('lexoffice.api_key',  $validated['lexoffice_api_key']  ?? '');

        AppSetting::set('ninox.api_key',    $validated['ninox_api_key']    ?? '');
        AppSetting::set('ninox.team_id',    $validated['ninox_team_id']    ?? '');
        AppSetting::set('ninox.db_id_kehr', $validated['ninox_db_id_kehr'] ?? '');
        AppSetting::set('ninox.db_id_alt',  $validated['ninox_db_id_alt']  ?? '');

        AppSetting::set('getraenkedb.api_url', $validated['getraenkedb_api_url'] ?? '');
        AppSetting::set('getraenkedb.api_key', $validated['getraenkedb_api_key'] ?? '');

        AppSetting::set('wawi.sync_token', $validated['wawi_sync_token'] ?? '');

        return redirect()
            ->route('admin.settings.integrations')
            ->with('success', 'Einstellungen gespeichert.');
    }
}
