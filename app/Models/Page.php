<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A CMS page (Impressum, AGB, Datenschutz, etc.).
 *
 * @property int    $id
 * @property string $slug
 * @property string $title
 * @property string $content  HTML, edited via WYSIWYG
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Page extends Model
{
    protected $fillable = ['slug', 'title', 'content'];
}
