<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * WP-20: Polymorphic contact / Ansprechpartner.
 *
 * Can be attached to any model that adds a morphMany('contacts') relation,
 * e.g. Customer and Supplier.
 *
 * @property int         $id
 * @property string      $contactable_type
 * @property int         $contactable_id
 * @property string      $name
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $role
 * @property int         $sort_order
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Contact extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'email',
        'role',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function contactable(): MorphTo
    {
        return $this->morphTo();
    }
}
