<?php

declare(strict_types=1);

namespace App\Models\Primeur;

use Illuminate\Database\Eloquent\Model;

class PrimeurCashSession extends Model
{
    protected $table = 'primeur_cash_sessions';

    protected $fillable = [
        'source_record_id', 'source_file', 'datum', 'kassen_nr',
        'session_start', 'session_end', 'benutzer',
        'anfangsbestand', 'endbestand', 'kassenbestand',
        'belegbetrag', 'anzahl_belege',
    ];

    protected $casts = [
        'datum' => 'date',
        'session_start' => 'datetime',
        'session_end' => 'datetime',
    ];
}
