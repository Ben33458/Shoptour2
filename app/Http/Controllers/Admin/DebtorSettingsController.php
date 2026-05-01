<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pricing\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DebtorSettingsController extends Controller
{
    private array $keys = [
        'dunning.postal_service_enabled',
        'dunning.postal_service_email',
        'dunning.sender_name',
        'dunning.sender_email',
        'dunning.reply_to',
        'dunning.cc',
        'dunning.bcc',
        'dunning.days_to_level1',
        'dunning.days_level1_to_level2',
        'dunning.interest_enabled',
        'dunning.base_rate_bps',
        'dunning.b2b_flat_fee_enabled',
        'dunning.test_mode',
        'dunning.test_email',
        'dunning.max_send_per_run',
        'dunning.bank_iban',
        'dunning.bank_bic',
        'dunning.bank_name',
        'dunning.company_name',
        'dunning.company_address',
    ];

    /**
     * GET /admin/einstellungen/mahnwesen
     */
    public function edit(): View
    {
        $settings = [];
        foreach ($this->keys as $key) {
            $settings[$key] = AppSetting::get($key);
        }

        return view('admin.settings.dunning', compact('settings'));
    }

    /**
     * POST /admin/einstellungen/mahnwesen
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'dunning.postal_service_enabled'    => 'nullable|boolean',
            'dunning.postal_service_email'       => 'nullable|email|max:255',
            'dunning.sender_name'                => 'nullable|string|max:255',
            'dunning.sender_email'               => 'nullable|email|max:255',
            'dunning.reply_to'                   => 'nullable|email|max:255',
            'dunning.cc'                         => 'nullable|email|max:255',
            'dunning.bcc'                        => 'nullable|email|max:255',
            'dunning.days_to_level1'             => 'nullable|integer|min:1|max:90',
            'dunning.days_level1_to_level2'      => 'nullable|integer|min:1|max:180',
            'dunning.interest_enabled'           => 'nullable|boolean',
            'dunning.base_rate_bps'              => 'nullable|integer|min:0|max:5000',
            'dunning.b2b_flat_fee_enabled'       => 'nullable|boolean',
            'dunning.test_mode'                  => 'nullable|boolean',
            'dunning.test_email'                 => 'nullable|email|max:255',
            'dunning.max_send_per_run'           => 'nullable|integer|min:1|max:500',
            'dunning.bank_iban'                  => 'nullable|string|max:34',
            'dunning.bank_bic'                   => 'nullable|string|max:11',
            'dunning.bank_name'                  => 'nullable|string|max:255',
            'dunning.company_name'               => 'nullable|string|max:255',
            'dunning.company_address'            => 'nullable|string|max:500',
        ]);

        // $data['dunning'] is a nested array because the form uses dunning[key] names
        $dunning = $data['dunning'] ?? [];

        $boolKeys = [
            'postal_service_enabled',
            'interest_enabled',
            'b2b_flat_fee_enabled',
            'test_mode',
        ];

        foreach ($this->keys as $fullKey) {
            // fullKey = 'dunning.sender_name' → shortKey = 'sender_name'
            $shortKey = substr($fullKey, strlen('dunning.'));

            if (in_array($shortKey, $boolKeys, true)) {
                // Checkbox: present = '1', absent = '0'
                $value = isset($dunning[$shortKey]) ? '1' : '0';
            } else {
                $value = isset($dunning[$shortKey]) && $dunning[$shortKey] !== ''
                    ? (string) $dunning[$shortKey]
                    : null;
            }

            AppSetting::set($fullKey, $value);
        }

        return back()->with('success', 'Mahnwesen-Einstellungen gespeichert.');
    }
}
