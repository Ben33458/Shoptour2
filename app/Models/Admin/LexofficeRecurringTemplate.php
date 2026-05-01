<?php

declare(strict_types=1);

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class LexofficeRecurringTemplate extends Model
{
    protected $fillable = [
        'company_id',
        'lexoffice_uuid',
        'version',
        'name',
        'voucher_type',
        'frequency',
        'start_date',
        'end_date',
        'next_execution_date',
        'last_execution_date',
        'total_net_amount',
        'total_gross_amount',
        'currency',
        'lexoffice_contact_id',
        'raw_json',
        'synced_at',
    ];

    protected $casts = [
        'raw_json'            => 'array',
        'synced_at'           => 'datetime',
        'start_date'          => 'date',
        'end_date'            => 'date',
        'next_execution_date' => 'date',
        'last_execution_date' => 'date',
    ];
}
