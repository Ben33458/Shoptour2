<?php

namespace App\Models\Employee;

use App\Models\Catalog\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissingProductReport extends Model
{
    protected $fillable = [
        'employee_id', 'product_id', 'artikelnummer', 'produktname',
        'info_text', 'status', 'admin_note',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'open'     => 'Offen',
            'ordered'  => 'Bestellt',
            'done'     => 'Erledigt',
            'rejected' => 'Abgelehnt',
            default    => $this->status,
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'open'     => '#d97706',
            'ordered'  => '#2563eb',
            'done'     => '#16a34a',
            'rejected' => '#dc2626',
            default    => '#64748b',
        };
    }
}
