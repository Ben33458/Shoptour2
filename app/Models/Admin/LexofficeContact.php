<?php

declare(strict_types=1);

namespace App\Models\Admin;

use App\Models\Pricing\Customer;
use App\Models\Supplier\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LexofficeContact extends Model
{
    protected $fillable = [
        'company_id',
        'lexoffice_uuid',
        'version',
        'archived',
        'is_customer',
        'is_vendor',
        'customer_number',
        'vendor_number',
        'company_name',
        'salutation',
        'first_name',
        'last_name',
        'primary_email',
        'primary_phone',
        'note',
        'raw_json',
        'customer_id',
        'supplier_id',
        'synced_at',
    ];

    protected $casts = [
        'archived'    => 'boolean',
        'is_customer' => 'boolean',
        'is_vendor'   => 'boolean',
        'raw_json'    => 'array',
        'synced_at'   => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** Display name: company name or "Vorname Nachname". */
    public function getDisplayNameAttribute(): string
    {
        if ($this->company_name) {
            return $this->company_name;
        }
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? '')) ?: '—';
    }
}
