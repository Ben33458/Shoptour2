<?php
namespace App\Models\Employee;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvailabilityBlock extends Model
{
    protected $fillable = ['employee_id', 'date', 'type', 'from_time', 'to_time', 'reason'];
    protected $casts = ['date' => 'date'];

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
}
