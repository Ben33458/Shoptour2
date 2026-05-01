<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pricing\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Administration → Ansicht
 * Manages default and available shop display settings for the frontend.
 */
class AdminShopDisplaySettingsController extends Controller
{
    public const VIEW_MODES = [
        'grid_large'    => 'Grid mit Bildern (groß, 3–4 Spalten)',
        'grid_compact'  => 'Kompaktes Grid (klein, 4–5 Spalten)',
        'list_images'   => 'Liste mit Vorschaubild',
        'list_no_images'=> 'Reine Textliste (ohne Bilder)',
        'table'         => 'Tabellenansicht',
    ];

    public const ITEMS_PER_PAGE_OPTIONS = [24, 48, 96];

    public const SORT_OPTIONS = [
        'name'       => 'Name A–Z',
        'preis'      => 'Preis aufsteigend',
        'preis-desc' => 'Preis absteigend',
    ];

    public const DESCRIPTION_MODES = [
        'short' => 'Kurztext',
        'full'  => 'Vollständig',
        'none'  => 'Ausblenden',
    ];

    public function edit(): View
    {
        $settings = [
            'available_views'         => json_decode(AppSetting::get('shop.display.available_views', '["grid_large","grid_compact","list_images","list_no_images","table"]'), true),
            'default_view'            => AppSetting::get('shop.display.default_view', 'grid_large'),
            'default_items_per_page'  => (int) AppSetting::get('shop.display.default_items_per_page', '24'),
            'default_sort'            => AppSetting::get('shop.display.default_sort', 'name'),
            'show_article_number'     => AppSetting::get('shop.display.show_article_number', '0') === '1',
            'show_deposit_separately' => AppSetting::get('shop.display.show_deposit_separately', '1') === '1',
            'description_mode'        => AppSetting::get('shop.display.description_mode', 'short'),
            'hide_unavailable'        => AppSetting::get('shop.display.hide_unavailable', '0') === '1',
            'show_stammsortiment_badge' => AppSetting::get('shop.display.show_stammsortiment_badge', '1') === '1',
            'show_new_badge'          => AppSetting::get('shop.display.show_new_badge', '1') === '1',
        ];

        return view('admin.settings.shop-display', [
            'settings'            => $settings,
            'viewModes'           => self::VIEW_MODES,
            'itemsPerPageOptions' => self::ITEMS_PER_PAGE_OPTIONS,
            'sortOptions'         => self::SORT_OPTIONS,
            'descriptionModes'    => self::DESCRIPTION_MODES,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'available_views'           => ['required', 'array', 'min:1'],
            'available_views.*'         => ['required', 'string', 'in:' . implode(',', array_keys(self::VIEW_MODES))],
            'default_view'              => ['required', 'string', 'in:' . implode(',', array_keys(self::VIEW_MODES))],
            'default_items_per_page'    => ['required', 'integer', 'in:' . implode(',', self::ITEMS_PER_PAGE_OPTIONS)],
            'default_sort'              => ['required', 'string', 'in:' . implode(',', array_keys(self::SORT_OPTIONS))],
            'description_mode'          => ['required', 'string', 'in:' . implode(',', array_keys(self::DESCRIPTION_MODES))],
        ]);

        // Ensure default_view is always in available_views
        if (! in_array($validated['default_view'], $validated['available_views'], true)) {
            $validated['available_views'][] = $validated['default_view'];
        }

        AppSetting::set('shop.display.available_views', json_encode($validated['available_views']));
        AppSetting::set('shop.display.default_view', $validated['default_view']);
        AppSetting::set('shop.display.default_items_per_page', (string) $validated['default_items_per_page']);
        AppSetting::set('shop.display.default_sort', $validated['default_sort']);
        AppSetting::set('shop.display.show_article_number', $request->boolean('show_article_number') ? '1' : '0');
        AppSetting::set('shop.display.show_deposit_separately', $request->boolean('show_deposit_separately') ? '1' : '0');
        AppSetting::set('shop.display.description_mode', $validated['description_mode']);
        AppSetting::set('shop.display.hide_unavailable', $request->boolean('hide_unavailable') ? '1' : '0');
        AppSetting::set('shop.display.show_stammsortiment_badge', $request->boolean('show_stammsortiment_badge') ? '1' : '0');
        AppSetting::set('shop.display.show_new_badge', $request->boolean('show_new_badge') ? '1' : '0');

        return back()->with('success', 'Ansichtseinstellungen gespeichert.');
    }
}
