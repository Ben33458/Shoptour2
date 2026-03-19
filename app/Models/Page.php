<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A CMS page (Impressum, AGB, Heimdienst, Festservice, etc.).
 *
 * menu:
 *   footer → Footer-Navigation (rechtliche Pflichtseiten)
 *   main   → Hauptmenü / prominente Seiten
 *   none   → Nicht im Menü (nur über direkten Link erreichbar)
 *
 * @property int    $id
 * @property string $slug
 * @property string $menu          footer|main|none
 * @property int    $sort_order
 * @property bool   $active
 * @property string $title
 * @property string $content       HTML, edited via WYSIWYG
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Page extends Model
{
    protected $fillable = ['slug', 'title', 'content', 'menu', 'sort_order', 'active'];

    protected $casts = [
        'active'     => 'boolean',
        'sort_order' => 'integer',
    ];

    /** Menü-Optionen für Dropdowns */
    public const MENUS = [
        'main'   => 'Hauptmenü (z.B. Heimdienst, Festservice)',
        'footer' => 'Footer (rechtliche Seiten)',
        'none'   => 'Nicht im Menü',
    ];
}
