<?php

declare(strict_types=1);

namespace App\Models\Primeur;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrimeurCustomer extends Model
{
    protected $table = 'primeur_customers';

    protected $fillable = [
        'primeur_id', 'suchname', 'name1', 'name2', 'name3',
        'strasse', 'hausnr', 'plz', 'ort',
        'vorwahl', 'telefon', 'telefon2', 'fax', 'email',
        'kundennummer', 'kundennummer2', 'kundengruppe',
        'preisgruppe', 'zahlungsart', 'aktiv',
        'anleg_time', 'update_time',
    ];

    protected $casts = [
        'aktiv' => 'boolean',
        'anleg_time' => 'datetime',
        'update_time' => 'datetime',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(PrimeurOrder::class, 'kunden_id', 'primeur_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([$this->name1, $this->name2, $this->name3])));
    }
}
